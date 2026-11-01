<?php

/**
 * The binding that gives every Feature test WordPress' test case.
 */

declare(strict_types=1);

use UAMSWeb\Aether\WordPressImporter\MigrationApi\Tests\Support\WordPressTestCase;

/*
 * Passes in every ordinary run. Its job is to fail in a child process started
 * without `--test-directory`, which is how `composer mutate` starts each mutant
 * (#443): `tests/Integration/Mutate/MutantChildTestDirectoryTest.php` starts
 * such a child and runs this file alone.
 */
it('runs inside the WordPress test case, and so inside its rolled-back transaction', function (): void {
    expect($this)->toBeInstanceOf(WordPressTestCase::class);
});
