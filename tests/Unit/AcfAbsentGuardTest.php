<?php

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\MigrationApi\ACF\FieldReader;

/*
 * Drives the arm of `FieldReader::read()` where ACF is not loaded (#111).
 *
 * Network activation does not make ACF load on every site: on go.uams.edu,
 * three redirect sites run without it, and `GET /posts/redirect_rule`
 * ended in `Call to undefined function acf_get_field_groups()` there. The
 * Integration suite cannot reach this arm, since ACF is loaded before any test
 * runs and a loaded function cannot be undefined.
 *
 * The Unit process loads neither WordPress nor ACF, so here the arm is the
 * ordinary state and the test runs in-process. Every payload with an `acf`
 * block (posts, terms, the options view) goes through `read()`, so this one
 * entry point covers all three. The precondition is asserted, not assumed: if
 * a later test declares an ACF function in this process, this test fails
 * loudly instead of silently testing the loaded arm.
 */

it('runs where ACF is not loaded', function (): void {
    expect(\function_exists('acf_get_field_groups'))
        ->toBeFalse('an ACF function is declared in the Unit process, so the ACF-absent arm is unreachable here');
});

it('returns the empty shape for a post when ACF is not loaded', function (): void {
    expect(FieldReader::forPost(42))->toBe([
        'keys' => [],
        'names' => [],
        'defaulted_keys' => [],
        'defaulted_names' => [],
        'collisions' => [],
        'identities' => [],
    ]);
});

it('returns the empty shape for a term when ACF is not loaded', function (): void {
    expect(FieldReader::read(['taxonomy' => 'category'], 'term_7'))->toBe([
        'keys' => [],
        'names' => [],
        'defaulted_keys' => [],
        'defaulted_names' => [],
        'collisions' => [],
        'identities' => [],
    ]);
});

it('returns the empty shape for an options page when ACF is not loaded', function (): void {
    expect(FieldReader::read(['options_page' => 'site-settings'], 'options'))->toBe([
        'keys' => [],
        'names' => [],
        'defaulted_keys' => [],
        'defaulted_names' => [],
        'collisions' => [],
        'identities' => [],
    ]);
});
