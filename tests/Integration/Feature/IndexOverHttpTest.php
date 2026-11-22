<?php

/**
 * The namespace index answers on the route an HTTP request reaches (#109).
 */

declare(strict_types=1);

/*
 * `rest_api_loaded()` strips the trailing slash from every HTTP route, so
 * `/wp-json/uamswp-migration/v1/` dispatches to `/uamswp-migration/v1`, the
 * index core generates. Internal dispatch keeps the slash, which is how
 * `IndexVersionTest` passed while no consumer could read the version. These
 * tests dispatch the route HTTP actually resolves to.
 */

it('reports the plugin version on the route an HTTP request reaches', function (): void {
    actAsSuperAdmin();

    $response = rest_do_request(new WP_REST_Request('GET', '/uamswp-migration/v1'));
    // Parsed with a pattern of the test's own, not through the reader it checks.
    $matched = preg_match('/^\s*\*\s*Version:\s*(\S+)\s*$/m', (string) file_get_contents(dirname(__DIR__, 3).'/aether-wordpress-migration-api.php'), $groups);
    $version = $matched === 1 ? $groups[1] : '';

    expect($version)->toMatch('/^\d+\.\d+\.\d+/')
        ->and($response->get_status())->toBe(200)
        ->and(payload($response, 'version'))->toBe($version)
        ->and(payload($response, 'name'))->toBe('UAMSWP Migration API');
});

it('serves the whole plugin index on the HTTP root and keeps core route list', function (): void {
    actAsSuperAdmin();

    $http = rest_do_request(new WP_REST_Request('GET', '/uamswp-migration/v1'))->get_data();
    $internal = rest_do_request(new WP_REST_Request('GET', '/uamswp-migration/v1/'))->get_data();

    assert(is_array($http) && is_array($internal));

    // Every key the internal route serves, with the same value, plus core's
    // `namespace` and `routes`, which other tests and readers rely on.
    expect(array_intersect_key($http, $internal))->toBe($internal)
        ->and($http)->toHaveKey('namespace', 'uamswp-migration/v1')
        ->and($http)->toHaveKey('routes');
});

it('leaves another namespace index as core builds it', function (): void {
    actAsSuperAdmin();

    $response = rest_do_request(new WP_REST_Request('GET', '/wp/v2'));
    $data = $response->get_data();

    assert(is_array($data));

    expect($response->get_status())->toBe(200)
        ->and($data['namespace'] ?? null)->toBe('wp/v2')
        ->and($data)->not->toHaveKey('version');
});
