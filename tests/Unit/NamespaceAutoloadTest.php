<?php

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\MigrationApi\Config;
use UAMSWeb\Aether\WordPressImporter\MigrationApi\Token;

/*
 * The package's PSR-4 prefix resolves to `includes/`, and the pre-rename
 * prefix resolves to nothing.
 *
 * A class that moved namespace without `composer.json` following it cannot be
 * found by Composer, and a stale prefix left behind would let the old name keep working
 * in a consumer that never updated. Both directions are asserted.
 */

/**
 * The prefix `autoload.psr-4` maps to `includes/`, and the one it replaced.
 */
const MIGRATION_API_PREFIX = 'UAMSWeb\\Aether\\WordPressImporter\\MigrationApi\\';
const MIGRATION_API_OLD_PREFIX = 'UAMSWP\\MigrationAPI\\';

test('composer maps the new prefix to includes and nothing maps the old one', function (): void {
    $json = file_get_contents(dirname(__DIR__, 2).'/composer.json');

    expect($json)->toBeString();

    /** @var array{name: string, autoload: array{'psr-4': array<string, string>}} $composer */
    $composer = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);

    expect($composer['name'])->toBe('uams-web/aether-wordpress-migration-api')
        ->and($composer['autoload']['psr-4'])->toBe([MIGRATION_API_PREFIX => 'includes/']);
});

test('classes resolve under the new prefix', function (): void {
    expect(class_exists(Token::class))->toBeTrue()
        ->and(class_exists(Config::class))->toBeTrue()
        ->and(Token::class)->toBe(MIGRATION_API_PREFIX.'Token');
});

test('the pre-rename prefix resolves to no class', function (): void {
    expect(class_exists(MIGRATION_API_OLD_PREFIX.'Token'))->toBeFalse();
});
