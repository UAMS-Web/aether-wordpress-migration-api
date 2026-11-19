<?php

/**
 * The namespace index reports the plugin header's version.
 */

declare(strict_types=1);

/**
 * The header's `Version:` value, parsed here with a pattern of the test's own
 * rather than through `get_file_data()`, so the assertion does not share the
 * reader it checks.
 */
function headerVersion(): string
{
    $path = dirname(__DIR__, 3).'/aether-wordpress-migration-api.php';
    $source = file_get_contents($path);

    if (! is_string($source) || preg_match('/^\s*\*\s*Version:\s*(\S+)\s*$/m', $source, $groups) !== 1) {
        throw new RuntimeException("No `Version:` header line could be read from {$path}.");
    }

    return $groups[1];
}

it('reports the version the plugin header declares', function (): void {
    actAsSuperAdmin();

    $response = rest_do_request(new WP_REST_Request('GET', '/uamswp-migration/v1/'));
    $header = headerVersion();

    // #98: the index reported a separate constant, 0.1.0, through every release.
    expect($response->get_status())->toBe(200)
        ->and($header)->toMatch('/^\d+\.\d+\.\d+/')
        ->and(payload($response, 'version'))->toBe($header);
});
