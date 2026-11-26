<?php

/**
 * The shared-secret path in a process whose secret is defined as a non-string.
 */

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\MigrationApi\Token;

/*
 * Pins the refusal when `UAMSWP_MIGRATION_API_SECRET` is defined as something
 * other than a string (#138). `wp-config.php` can define it as anything, and
 * `hash_equals()` raises a `TypeError` for a non-string, so the guard in
 * `Auth::secretAccepts()` is what keeps a misconfigured secret a refusal rather
 * than an error. A constant cannot change once set, so this state runs only in
 * an invocation with `UAMSWP_MIGRATION_API_TESTS_NON_STRING_SECRET=1`, which
 * defines it as the integer `1`:
 *
 *     UAMSWP_MIGRATION_API_TESTS_NON_STRING_SECRET=1 \
 *       tests/Integration/vendor/bin/pest --configuration=tests/Integration/phpunit.xml \
 *       --test-directory=. tests/Integration/Feature/NonStringSecretTest.php
 *
 * In every other run, `composer test` and the chain included, the constant is
 * undefined or a string, and every test here skips.
 */

/**
 * Dispatch an otherwise-anonymous request carrying a credential header.
 */
function nonStringSecretRequest(?string $credential): WP_REST_Response
{
    wp_set_current_user(0);

    $request = new WP_REST_Request('GET', '/uamswp-migration/v1/');

    if ($credential !== null) {
        $request->set_header('X-UAMSWP-Migration-Key', $credential);
    }

    return rest_do_request($request);
}

beforeEach(function (): void {
    $this->markTestSkipped('Control for #147: forced to skip so the shared-secret mutation job must fail.');
    if (! defined('UAMSWP_MIGRATION_API_SECRET') || is_string(constant('UAMSWP_MIGRATION_API_SECRET'))) {
        $this->markTestSkipped('The secret is not defined as a non-string; run with UAMSWP_MIGRATION_API_TESTS_NON_STRING_SECRET=1.');
    }
});

it('authorizes nothing with a non-string secret, while a valid token still passes', function (): void {
    // The control: the same anonymous request is admitted by a token, so a
    // refusal below is about the secret rather than the request.
    $token = Token::issue()['token'];

    // `'1'` is the constant's value as a string: the header a loose
    // comparison would accept, and the one most likely to reach it.
    expect(nonStringSecretRequest($token)->get_status())->toBe(200)
        ->and(nonStringSecretRequest('1')->get_status())->toBe(401)
        ->and(nonStringSecretRequest('any-value-at-all')->get_status())->toBe(401)
        ->and(nonStringSecretRequest('')->get_status())->toBe(401)
        ->and(nonStringSecretRequest(null)->get_status())->toBe(401);
});
