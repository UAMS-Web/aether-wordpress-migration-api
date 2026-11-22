<?php

/**
 * A record whose permalink cannot be computed is still served (#118).
 */

declare(strict_types=1);

/**
 * A post-status object as WP Statuses 1.3.0-alpha builds it.
 *
 * `_builtin` is private, so core's `is_post_status_viewable()` reading
 * `$post_status->_builtin` raises an `Error` from outside the class, which is
 * what made `get_permalink()` throw on news.uams.edu.
 */
final class PrivateBuiltinStatus
{
    public string $label = 'Draft';

    public bool $internal = false;

    public bool $protected = false;

    public bool $public = false;

    public bool $publicly_queryable = false;

    public function __construct(public string $name, private bool $_builtin = true) {}

    /**
     * What the plugin's own accessor would return in place of the property.
     */
    public function isBuiltin(): bool
    {
        return $this->_builtin;
    }
}

/**
 * Dispatch an authenticated GET against a route in this namespace.
 *
 * @param  array<string, mixed>  $params  Query parameters.
 */
function permalinkThrowsGet(string $route, array $params = []): WP_REST_Response
{
    $request = new WP_REST_Request('GET', '/uamswp-migration/v1'.$route);

    foreach ($params as $name => $value) {
        $request->set_param($name, $value);
    }

    $response = rest_do_request($request);

    assert($response->get_status() === 200, 'the route did not answer 200');

    return $response;
}

/**
 * Run a callback while `draft` is a status object with a private `_builtin`.
 *
 * Restored afterwards, because the status table is process-wide state that the
 * per-test transaction rollback does not reach.
 */
function withPrivateBuiltinDraft(callable $callback): void
{
    $original = $GLOBALS['wp_post_statuses'];

    assert(is_array($original) && isset($original['draft']), 'the status table has no draft entry');

    $GLOBALS['wp_post_statuses'] = [...$original, 'draft' => new PrivateBuiltinStatus('draft')];

    try {
        $callback();
    } finally {
        $GLOBALS['wp_post_statuses'] = $original;
    }
}

beforeEach(function (): void {
    actAsSuperAdmin();
});

it('serves a record whose permalink throws, with link null, beside an ordinary one', function (): void {
    $published = makePost(['post_type' => 'page', 'post_status' => 'publish']);
    $draft = makePost(['post_type' => 'page', 'post_status' => 'draft']);

    // The control: with the ordinary status objects the draft's permalink is
    // computed and nothing throws.
    $ordinary = get_permalink($draft);

    expect(is_string($ordinary) && $ordinary !== '')->toBeTrue();

    withPrivateBuiltinDraft(function () use ($published, $draft): void {
        // The control that the fixture reproduces the failure: the bare read throws.
        expect(fn () => get_permalink($draft))->toThrow(Error::class, '_builtin');

        $response = permalinkThrowsGet('/posts/page', ['status' => 'any']);
        $data = $response->get_data();

        assert(is_array($data));

        $byId = [];

        foreach ($data as $record) {
            assert(is_array($record) && is_int($record['id']));
            $byId[$record['id']] = $record;
        }

        expect($byId)->toHaveKeys([$published, $draft])
            ->and($byId[$draft]['link'])->toBeNull()
            ->and($byId[$draft]['status'])->toBe('draft')
            ->and($byId[$draft]['title'])->toBe(get_post($draft)?->post_title)
            ->and($byId[$published]['link'])->toBe(get_permalink($published));
    });
});

it('serves the single-record route for a record whose permalink throws', function (): void {
    $draft = makePost(['post_type' => 'page', 'post_status' => 'draft']);

    withPrivateBuiltinDraft(function () use ($draft): void {
        $record = permalinkThrowsGet('/posts/page/'.$draft)->get_data();

        assert(is_array($record));

        expect($record['id'])->toBe($draft)
            ->and($record['link'])->toBeNull();
    });
});

it('keeps an empty string distinct from an unavailable permalink', function (): void {
    $id = makePost(['post_type' => 'page', 'post_status' => 'publish']);

    // A filter that makes core return an empty permalink: the field stays a
    // string, so null means "could not be computed" and nothing else.
    add_filter('page_link', static fn (): string => '');

    $record = permalinkThrowsGet('/posts/page/'.$id)->get_data();

    remove_all_filters('page_link');

    assert(is_array($record));

    expect($record['link'])->toBe('');
});

it('still fails the request for an error raised by any other read', function (): void {
    makePost(['post_type' => 'page', 'post_status' => 'publish']);

    // `is_sticky()` reads `get_option()`, a read the guard does not cover.
    add_filter('pre_option_sticky_posts', static fn (mixed $value): mixed => $value ?: throw new Error('raised outside the permalink read'));

    expect(fn () => rest_do_request(new WP_REST_Request('GET', '/uamswp-migration/v1/posts/page')))
        ->toThrow(Error::class, 'raised outside the permalink read');

    remove_all_filters('pre_option_sticky_posts');
});
