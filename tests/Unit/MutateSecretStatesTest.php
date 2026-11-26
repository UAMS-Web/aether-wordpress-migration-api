<?php

declare(strict_types=1);

/*
 * Drives `scripts/mutate-secret-states.php` against saved Pest output, asserting
 * on the exit code and the lines it prints (#147).
 *
 * The script's own run starts four mutation processes that boot WordPress, so
 * these tests use its `--log` form instead: each state's output is a fixture,
 * and `--source` points at a fixture class whose shared-secret lines are known.
 * Driven as a subprocess: the script is a CLI entry point that calls `exit()`.
 */

/**
 * A class shaped like `Auth`, with its shared-secret lines at known numbers.
 *
 * `authorize()`'s `if` on `secretMatches()` is lines 7 to 9, `secretMatches()`
 * lines 15 to 18, and `secretAccepts()` lines 20 to 27. Line 11 is in
 * `authorize()` but outside that `if`, and line 31 is in another method.
 */
function mutateSecretSource(): string
{
    return <<<'PHP'
        <?php
        final class Auth
        {
            public static function authorize($request): bool
            {
                // line 6
                if (self::secretMatches($request)) {
                    return true;
                }

                return self::tokenMatches($request);
            }

            // line 14
            private static function secretMatches($request): bool
            {
                return self::secretAccepts((string) $request);
            }

            private static function secretAccepts(string $presented): bool
            {
                if ($presented === '') {
                    return false;
                }

                return hash_equals('x', "{$presented}");
            }

            private static function tokenMatches($request): bool
            {
                return $request === 'token';
            }
        }
        PHP;
}

/**
 * Pest's mutation output for one state, from `<mark> <line>:<mutator>` rows.
 *
 * @param  list<string>  $rows
 */
function mutateSecretLog(array $rows, bool $summary = true): string
{
    $output = "   RUN  includes/Auth.php\n";

    foreach ($rows as $row) {
        [$mark, $mutant] = explode(' ', $row, 2);
        [$line, $mutator] = explode(':', $mutant, 2);
        $output .= "  \e[32;1m{$mark}\e[39;22m \e[90mLine {$line}: {$mutator}\e[39m\n";
    }

    return $output.($summary ? "\n  Mutations: 1 untested, 2 uncovered, 3 tested\n  Score:     50.00%\n" : "\n  Tests:    1 passed\n");
}

/**
 * Run the script on fixture logs, one per state.
 *
 * @param  array<string, string>  $logs  Output keyed by state, in the script's order.
 * @return array{0: int, 1: string} The exit code and the combined output.
 */
function mutateSecretRun(array $logs, ?string $source = null): array
{
    $directory = sys_get_temp_dir().'/mutate-secret-'.bin2hex(random_bytes(6));
    mkdir($directory);
    file_put_contents($directory.'/Auth.php', $source ?? mutateSecretSource());

    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(\dirname(__DIR__, 2).'/scripts/mutate-secret-states.php')
        .' '.escapeshellarg('--source='.$directory.'/Auth.php');

    foreach ($logs as $state => $log) {
        file_put_contents($directory.'/'.$state.'.log', $log);
        $command .= ' '.escapeshellarg('--log='.$state.'='.$directory.'/'.$state.'.log');
    }

    $output = [];
    $exitCode = 0;
    exec($command.' 2>&1', $output, $exitCode);

    array_map(unlink(...), glob($directory.'/*') ?: []);
    rmdir($directory);

    return [$exitCode, implode("\n", $output)];
}

/**
 * The same mutants in each of the four states, with each state's marks.
 *
 * @param  array<string, list<string>>  $marks  Per state, one mark per mutant in `$mutants` order.
 * @param  list<string>  $mutants
 * @return array<string, string>
 */
function mutateSecretLogs(array $mutants, array $marks): array
{
    $logs = [];

    foreach (['none', 'secret', 'empty', 'non-string'] as $state) {
        $logs[$state] = mutateSecretLog(array_map(
            static fn (string $mutant, string $mark): string => $mark.' '.$mutant,
            $mutants,
            $marks[$state] ?? array_fill(0, \count($mutants), '-'),
        ));
    }

    return $logs;
}

it('passes when every shared-secret mutant is killed in some state, though no state kills them all', function (): void {
    $mutants = ['8:TrueToFalse', '22:IfNegated', '22:IfNegated', '26:FalseToTrue'];

    [$exitCode, $output] = mutateSecretRun(mutateSecretLogs($mutants, [
        'none' => ['-', '✓', '⨯', '-'],
        'secret' => ['✓', '⨯', '✓', '-'],
        'empty' => ['-', '⨯', '⨯', '⨯'],
        'non-string' => ['-', '-', '-', '✓'],
    ]));

    expect($exitCode)->toBe(0, $output)
        ->and($output)->toContain('shared-secret lines 7-9, 15-18, 20-27')
        ->and($output)->toContain('4 mutants on the shared-secret lines')
        ->and($output)->toContain('every shared-secret mutant is killed in at least one state');
});

it('fails, naming the mutant, when no state kills one', function (): void {
    $mutants = ['8:TrueToFalse', '23:FalseToTrue'];

    [$exitCode, $output] = mutateSecretRun(mutateSecretLogs($mutants, [
        'none' => ['✓', '-'],
        'secret' => ['✓', '⨯'],
        'empty' => ['-', 't'],
        'non-string' => ['-', '⨯'],
    ]));

    expect($exitCode)->toBe(1, $output)
        ->and($output)->toContain('1 mutant(s) killed by no state: 23:FalseToTrue#1');
});

it('ignores a mutant outside the shared-secret lines', function (): void {
    // Line 11 is in `authorize()` but past the `secretMatches()` `if`, and line
    // 31 is in another method: `composer mutate` scores both.
    $mutants = ['8:TrueToFalse', '11:RemoveEarlyReturn', '31:IdenticalToNotIdentical'];

    [$exitCode, $output] = mutateSecretRun(mutateSecretLogs($mutants, [
        'none' => ['✓', '⨯', '⨯'],
        'secret' => ['-', '⨯', '⨯'],
    ]));

    expect($exitCode)->toBe(0, $output)
        ->and($output)->toContain('1 mutants on the shared-secret lines')
        ->and($output)->not->toContain('RemoveEarlyReturn')
        ->and($output)->not->toContain('IdenticalToNotIdentical');
});

it('refuses a run that printed no mutation summary', function (): void {
    $logs = mutateSecretLogs(['8:TrueToFalse'], ['none' => ['✓']]);
    $logs['empty'] = mutateSecretLog([], summary: false);

    [$exitCode, $output] = mutateSecretRun($logs);

    expect($exitCode)->toBe(2, $output)
        ->and($output)->toContain('the empty run printed no mutation summary');
});

it('refuses states that report different mutants', function (): void {
    $logs = mutateSecretLogs(['8:TrueToFalse', '22:IfNegated'], ['none' => ['✓', '✓']]);
    $logs['secret'] = mutateSecretLog(['✓ 8:TrueToFalse']);

    [$exitCode, $output] = mutateSecretRun($logs);

    expect($exitCode)->toBe(2, $output)
        ->and($output)->toContain('the secret run reported different mutants');
});

it('refuses when no mutant is on the shared-secret lines', function (): void {
    [$exitCode, $output] = mutateSecretRun(mutateSecretLogs(['31:IdenticalToNotIdentical'], ['none' => ['✓']]));

    expect($exitCode)->toBe(2, $output)
        ->and($output)->toContain('no mutant was reported on the shared-secret lines');
});

it('refuses a source with no shared-secret method', function (): void {
    [$exitCode, $output] = mutateSecretRun(
        mutateSecretLogs(['8:TrueToFalse'], ['none' => ['✓']]),
        "<?php\nfinal class Auth\n{\n    public static function authorize(): bool\n    {\n        return true;\n    }\n}\n",
    );

    expect($exitCode)->toBe(2, $output)
        ->and($output)->toContain('no shared-secret line found');
});

it('refuses logs that do not name every state in order', function (): void {
    $logs = mutateSecretLogs(['8:TrueToFalse'], ['none' => ['✓']]);
    unset($logs['non-string']);

    [$exitCode, $output] = mutateSecretRun($logs);

    expect($exitCode)->toBe(2, $output)
        ->and($output)->toContain('pass one --log for each state, in order: none, secret, empty, non-string');
});
