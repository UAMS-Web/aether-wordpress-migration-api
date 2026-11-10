<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
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

/*
 * The importer's own prefix, `UAMSWeb\Aether\WordPressImporter\`, is a prefix
 * of this package's. With both installed, Composer resolves a class by the
 * longest matching prefix, so a `MigrationApi\` class must come from this
 * package even when the importer's tree holds a file at the same relative
 * path. The importer is not a dependency here, so its tree is a fixture
 * carrying one class of its own and a decoy at `MigrationApi/Token.php`.
 */

test('a class from each package loads side by side with the importer prefix', function (): void {
    $importerPrefix = 'UAMSWeb\\Aether\\WordPressImporter\\';
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'aether-side-by-side-'.bin2hex(random_bytes(6));
    $probe = 'Probe'.bin2hex(random_bytes(6));

    mkdir($root.'/Console', 0777, true);
    mkdir($root.'/MigrationApi', 0777, true);
    file_put_contents(
        $root.'/Console/'.$probe.'.php',
        "<?php\nnamespace UAMSWeb\\Aether\\WordPressImporter\\Console;\nfinal class {$probe} {}\n",
    );
    file_put_contents($root.'/MigrationApi/Token.php', "<?php\nthrow new \\LogicException('decoy loaded');\n");

    $loader = new ClassLoader;
    $loader->addPsr4($importerPrefix, $root);
    $loader->addPsr4(MIGRATION_API_PREFIX, dirname(__DIR__, 2).'/includes');

    try {
        $importerClass = $importerPrefix.'Console\\'.$probe;

        expect($loader->loadClass($importerClass))->toBeTrue()
            ->and(class_exists($importerClass, false))->toBeTrue()
            ->and(realpath((string) $loader->findFile(Token::class)))
            ->toBe(realpath(dirname(__DIR__, 2).'/includes/Token.php'));
    } finally {
        unlink($root.'/Console/'.$probe.'.php');
        unlink($root.'/MigrationApi/Token.php');
        rmdir($root.'/Console');
        rmdir($root.'/MigrationApi');
        rmdir($root);
    }
});
