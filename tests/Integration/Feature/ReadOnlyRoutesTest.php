<?php

/**
 * Every route in this plugin's namespace reads; none writes (#80).
 */

declare(strict_types=1);

use const UAMSWeb\Aether\WordPressImporter\MigrationApi\REST_NAMESPACE;

/*
 * The plugin is read-only by construction: `Controller::registerReadRoute()`
 * is the one place it registers a route, and it passes `'methods' => 'GET'`.
 * Nothing held that construction in place. A controller that called
 * `register_rest_route()` itself, or a change to the helper, could register a
 * write route with every test still green. These tests read the route table
 * the server actually built, so they see a route however it was registered.
 */

/**
 * Every route in this plugin's namespace, mapped to the methods it accepts
 * beyond `GET` and the `HEAD` WordPress derives from it.
 *
 * A route whose handlers accept nothing else maps to an empty list. The route
 * table stores each handler's methods as a map of method name to `true`, the
 * shape `register_rest_route()` normalizes `'methods'` into.
 *
 * @return array<string, list<string>>
 */
function migrationApiNonReadMethods(): array
{
    $prefix = '/'.REST_NAMESPACE;
    $found = [];

    foreach (rest_get_server()->get_routes() as $route => $handlers) {
        if (! is_string($route) || ($route !== $prefix && ! str_starts_with($route, $prefix.'/'))) {
            continue;
        }

        assert(is_array($handlers), "the route table holds no handler list for {$route}");

        $extra = [];

        foreach ($handlers as $handler) {
            $methods = is_array($handler) ? ($handler['methods'] ?? []) : [];

            foreach (array_keys(is_array($methods) ? $methods : []) as $method) {
                if (! in_array($method, ['GET', 'HEAD'], true)) {
                    $extra[] = (string) $method;
                }
            }
        }

        $found[$route] = $extra;
    }

    return $found;
}

it('accepts GET and nothing else on every route in the namespace', function (): void {
    $routes = migrationApiNonReadMethods();

    // The two index routes and a resource route, so an enumeration that
    // matched nothing cannot pass as a clean one.
    expect($routes)->toHaveKey('/uamswp-migration/v1')
        ->and($routes)->toHaveKey('/uamswp-migration/v1/')
        ->and($routes)->toHaveKey('/uamswp-migration/v1/options');

    expect(array_filter($routes))->toBe([]);
});

it('flags a route in the namespace that accepts POST', function (): void {
    // The control for the test above: the same enumeration, given a write
    // route, has to name it.
    register_rest_route(REST_NAMESPACE, '/__write-test', [
        [
            'methods' => 'POST',
            'callback' => static fn (): WP_REST_Response => new WP_REST_Response(['reached' => true]),
            'permission_callback' => '__return_true',
        ],
    ]);

    expect(array_filter(migrationApiNonReadMethods()))->toBe([
        '/uamswp-migration/v1/__write-test' => ['POST'],
    ]);
});

it('answers a POST to a namespace route with no route rather than a handler', function (): void {
    // As a super admin, so the namespace gate passes and the answer is the
    // route table's.
    actAsSuperAdmin();

    $response = rest_do_request(new WP_REST_Request('POST', '/uamswp-migration/v1/options'));

    expect($response->get_status())->toBe(404)
        ->and(errorCode($response))->toBe('rest_no_route');
});
