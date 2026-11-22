<?php

/**
 * A record whose permalink lookup throws is still served (#118).
 */

declare(strict_types=1);

/**
 * A post-status object shaped like WP Statuses' `WP_Statuses_Core_Status`.
 *
 * It keeps `_builtin` private, so core's `is_post_status_viewable()` throws
 * an `Error` when it reads the property. The public flags are the ones that
 * let core reach that read.
 */
final class PrivateBuiltinStatus
{
    public string $name = 'draft';

    public string $label = 'Draft';

    public bool $internal = false;

    public bool $protected = false;

    public bool $private = false;

    public bool $public = false;

    public bool $publicly_queryable = false;

    public bool $exclude_from_search = true;

    public bool $show_in_admin_all_list = true;

    public bool $show_in_admin_status_list = true;

    /**
     * Keep `_builtin` private, the shape that makes core's read throw.
     *
     * @param  bool  $_builtin  Whether the status is built in, as core records it.
     */
    public function __construct(private readonly bool $_builtin = true) {}

    /**
     * Read `_builtin` from inside the class, where its visibility allows it.
     */
    public function isBuiltin(): bool
    {
        return $this->_builtin;
    }
}

/**
 * The records of a collection response, keyed by id.
 *
 * @return array<int, array<string, mixed>>
 */
function permalinkRecordsById(WP_REST_Response $response): array
{
    $data = $response->get_data();

    assert(is_array($data), 'a collection response did not return an array');

    $byId = [];

    foreach ($data as $record) {
        assert(is_array($record), 'a collection record was not an array');

        $id = $record['id'] ?? null;

        assert(is_int($id), 'a collection record had no integer id');

        /** @var array<string, mixed> $record */
        $byId[$id] = $record;
    }

    return $byId;
}

/**
 * Run a callback with the draft status replaced, restoring it afterwards.
 *
 * @template T
 *
 * @param  callable(): T  $callback  The work to run while the status is replaced.
 * @return T
 */
function withPrivateBuiltinDraft(callable $callback): mixed
{
    global $wp_post_statuses;

    assert(is_array($wp_post_statuses), 'post statuses are not registered');

    $original = $wp_post_statuses['draft'];
    $wp_post_statuses['draft'] = new PrivateBuiltinStatus;

    try {
        return $callback();
    } finally {
        $wp_post_statuses['draft'] = $original;
    }
}

/**
 * Dispatch a GET against this plugin's namespace.
 *
 * @param  array<string, mixed>  $params  Query parameters.
 */
function permalinkGet(string $route, array $params = []): WP_REST_Response
{
    $request = new WP_REST_Request('GET', '/uamswp-migration/v1'.$route);

    foreach ($params as $name => $value) {
        $request->set_param($name, $value);
    }

    return rest_do_request($request);
}

beforeEach(function (): void {
    actAsSuperAdmin();
});

it('makes core throw on the permalink of a page in the replaced status', function (): void {
    // The control: without it, a pass below could mean the replacement never
    // reached the code path that throws.
    $draft = makePost(['post_type' => 'page', 'post_status' => 'draft']);

    expect(fn (): mixed => withPrivateBuiltinDraft(fn () => get_permalink($draft)))
        ->toThrow(Error::class, 'Cannot access private property');
});

it('serves a page whose permalink throws, beside an ordinary one, in the collection', function (): void {
    $draft = makePost(['post_type' => 'page', 'post_status' => 'draft']);
    $published = makePost(['post_type' => 'page', 'post_status' => 'publish']);

    $response = withPrivateBuiltinDraft(fn (): WP_REST_Response => permalinkGet('/posts/page', ['per_page' => 100]));
    $byId = permalinkRecordsById($response);

    expect($response->get_status())->toBe(200)
        ->and($byId)->toHaveKeys([$draft, $published])
        ->and($byId[$draft]['link'])->toBeNull()
        ->and($byId[$published]['link'])->toBe(get_permalink($published))
        // Every other field is served too: the record is whole, not a stub.
        ->and(array_keys($byId[$draft]))->toBe(array_keys($byId[$published]))
        ->and($byId[$draft]['title'])->toBe(get_post($draft)?->post_title)
        ->and($byId[$draft]['status'])->toBe('draft');
});

it('serves a page whose permalink throws as a single record', function (): void {
    $draft = makePost(['post_type' => 'page', 'post_status' => 'draft']);

    $response = withPrivateBuiltinDraft(fn (): WP_REST_Response => permalinkGet("/posts/page/{$draft}"));

    expect($response->get_status())->toBe(200)
        ->and(payload($response, 'id'))->toBe($draft)
        ->and(payload($response, 'link'))->toBeNull();
});

it('still fails the request when a read other than the permalink throws', function (): void {
    makePost(['post_type' => 'page', 'post_status' => 'publish']);

    $throw = static function (bool $isSticky): bool {
        throw new Error('a read other than the permalink failed');
    };

    // The `is_sticky` filter runs only inside the record builder, beside the
    // permalink, so a guard wider than the permalink call would swallow it.
    // @phpstan-ignore return.missing (the callback exists to throw, so it never returns)
    add_filter('is_sticky', $throw);

    try {
        expect(fn (): WP_REST_Response => permalinkGet('/posts/page'))
            ->toThrow(Error::class, 'a read other than the permalink failed');
    } finally {
        remove_filter('is_sticky', $throw);
    }
});
