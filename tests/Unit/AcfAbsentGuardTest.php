<?php

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\MigrationApi\ACF\FieldReader;
use UAMSWeb\Aether\WordPressImporter\MigrationApi\Content\OptionsPayload;

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
 * block (posts, terms, the options view) goes through `read()`. The options
 * view also reaches ACF on its own, which the last test covers. The
 * precondition is asserted, not assumed: if a later test declares an ACF
 * function in this process, this test fails loudly instead of silently testing
 * the loaded arm.
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

/*
 * `GET /options` reaches ACF without going through `read()`: it finds the
 * options pages first, in `OptionsPayload::optionsPageSlugs()`. The first fix
 * guarded only `read()`, so posts were served on go.uams.edu's sites without
 * ACF and `/options` still raised a fatal there. Driving the private `acf()`
 * covers the whole ACF view of `/options`, both its calls included.
 */
it('builds an empty ACF view of the options when ACF is not loaded', function (): void {
    $acf = new ReflectionMethod(OptionsPayload::class, 'acf');

    expect($acf->invoke(null))->toBe(['pages' => [], 'by_name' => 0, 'by_shape' => 0]);
});
