<?php

/**
 * Score the shared-secret mutants of `includes/Auth.php` across the secret states.
 *
 * `composer mutate` runs in the process where `UAMSWP_MIGRATION_API_SECRET` is
 * undefined, and `--covered-only` drops the lines only the other states reach,
 * so no other mutation run scores the shared-secret path (#147). The constant
 * cannot change once set, so each state is a process of its own:
 *
 *     none         none of the three set                               SharedSecretTest.php
 *     secret       WP_TESTS_MIGRATION_SECRET=shared-secret-for-tests   SharedSecretTest.php
 *     empty        UAMSWP_MIGRATION_API_TESTS_EMPTY_SECRET=1           EmptySecretTest.php
 *     non-string   UAMSWP_MIGRATION_API_TESTS_NON_STRING_SECRET=1      NonStringSecretTest.php
 *
 * Each runs `--mutate --path=includes/Auth.php` without `--covered-only`, over
 * that state's test file. The `none` state is the default process, run over
 * the one test file whose refusal reaches the `defined()` guard, which no other
 * state reaches. A mutant counts as killed when any state kills it,
 * since no one state reaches every line. A state that leaves a mutant untested
 * is expected; a mutant that no state kills is not.
 *
 * The lines scored are the shared-secret lines: `secretMatches()`,
 * `secretAccepts()`, and the `if` in `authorize()` that calls
 * `secretMatches()`. They are found in the source by name, so they follow the
 * code as it moves. Every other line of the file is scored by `composer mutate`.
 *
 * Usage:
 *
 *     php scripts/mutate-secret-states.php
 *     php scripts/mutate-secret-states.php --source=<file> --log=<state>=<file> ...
 *
 * The first form runs the four processes and needs the Integration setup, a
 * coverage driver, and a WordPress install the suite can boot. The second reads
 * saved Pest output instead of running anything, one `--log` per state, against
 * `--source` in place of `includes/Auth.php`; it is how the script's own tests
 * drive it. Exits 0 when every shared-secret mutant is killed in at least one
 * state, 1 when one is not, and 2 when it could not score: a process that
 * failed or printed no mutation summary, no shared-secret line found, no
 * mutant on those lines, or states that disagree on which mutants exist.
 */

declare(strict_types=1);

/** The file mutated, relative to the repository root. */
const SECRET_SOURCE = 'includes/Auth.php';

/**
 * Each state: the variables its process sets, and the test file it runs.
 *
 * Every state sets each of these variables, so a value the caller exported cannot
 * leak into a state that does not name it.
 *
 * @return array<string, array{env: array<string, string>, test: string}>
 */
function secretStates(): array
{
    $none = [
        'WP_TESTS_MIGRATION_SECRET' => '',
        'UAMSWP_MIGRATION_API_TESTS_EMPTY_SECRET' => '',
        'UAMSWP_MIGRATION_API_TESTS_NON_STRING_SECRET' => '',
        'UAMSWP_MIGRATION_API_TESTS_SINGLE_SITE' => '',
    ];

    return [
        'none' => [
            'env' => $none,
            'test' => 'tests/Integration/Feature/SharedSecretTest.php',
        ],
        'secret' => [
            'env' => ['WP_TESTS_MIGRATION_SECRET' => 'shared-secret-for-tests'] + $none,
            'test' => 'tests/Integration/Feature/SharedSecretTest.php',
        ],
        'empty' => [
            'env' => ['UAMSWP_MIGRATION_API_TESTS_EMPTY_SECRET' => '1'] + $none,
            'test' => 'tests/Integration/Feature/EmptySecretTest.php',
        ],
        'non-string' => [
            'env' => ['UAMSWP_MIGRATION_API_TESTS_NON_STRING_SECRET' => '1'] + $none,
            'test' => 'tests/Integration/Feature/NonStringSecretTest.php',
        ],
    ];
}

/**
 * Print a message to stderr and exit 2: the run could not score.
 */
function secretStatesRefuse(string $message): never
{
    fwrite(STDERR, 'mutate-secret-states: '.$message."\n");

    exit(2);
}

/**
 * The index of the `}` that closes the block opened at or after `$from`.
 *
 * @param  list<PhpToken>  $tokens
 */
function secretStatesBlockEnd(array $tokens, int $from): ?int
{
    $depth = 0;
    $count = \count($tokens);

    for ($index = $from; $index < $count; $index++) {
        $text = $tokens[$index]->text;

        // `{$` and `${` open a brace inside a string and close with a plain `}`.
        if ($text === '{' || $tokens[$index]->id === T_CURLY_OPEN || $tokens[$index]->id === T_DOLLAR_OPEN_CURLY_BRACES) {
            $depth++;
        } elseif ($text === '}') {
            $depth--;

            if ($depth === 0) {
                return $index;
            }
        }
    }

    return null;
}

/**
 * The shared-secret lines of the source, as inclusive line ranges.
 *
 * @return list<array{0: int, 1: int}>
 */
function secretStatesLines(string $source): array
{
    $tokens = array_values(PhpToken::tokenize($source));
    $ranges = [];
    $count = \count($tokens);

    for ($index = 0; $index < $count; $index++) {
        if ($tokens[$index]->id !== T_FUNCTION) {
            continue;
        }

        $name = null;

        for ($next = $index + 1; $next < $count; $next++) {
            if ($tokens[$next]->id === T_STRING) {
                $name = $tokens[$next]->text;

                break;
            }

            if (! $tokens[$next]->isIgnorable()) {
                break;
            }
        }

        $end = secretStatesBlockEnd($tokens, $index);

        if ($name === null || $end === null) {
            continue;
        }

        if ($name === 'secretMatches' || $name === 'secretAccepts') {
            $ranges[] = [$tokens[$index]->line, $tokens[$end]->line];

            continue;
        }

        if ($name !== 'authorize') {
            continue;
        }

        // In `authorize()`, the `if` whose condition calls `secretMatches()`.
        for ($inner = $index; $inner < $end; $inner++) {
            if ($tokens[$inner]->id !== T_IF) {
                continue;
            }

            $blockEnd = secretStatesBlockEnd($tokens, $inner);

            if ($blockEnd === null) {
                continue;
            }

            for ($probe = $inner; $probe < $blockEnd; $probe++) {
                if ($tokens[$probe]->text === '{') {
                    break;
                }

                if ($tokens[$probe]->id === T_STRING && $tokens[$probe]->text === 'secretMatches') {
                    $ranges[] = [$tokens[$inner]->line, $tokens[$blockEnd]->line];

                    break 2;
                }
            }
        }
    }

    return $ranges;
}

/**
 * Each mutant one Pest mutation run reports, keyed `<line>:<mutator>#<n>`.
 *
 * `n` counts the same mutator on the same line, in the order Pest reports
 * them, which is the order it generates them in every run. The value is
 * `killed`, `untested`, `uncovered` or `timeout`. Null when the output has no mutation
 * summary, so a run that never got as far as mutating is not read as one that
 * reported nothing.
 *
 * @return array<string, string>|null
 */
function secretStatesParse(string $output): ?array
{
    $plain = (string) preg_replace('/\e\[[0-9;]*m/', '', $output);

    if (preg_match('/^\s*Mutations:\s.*\btested\b/m', $plain) !== 1) {
        return null;
    }

    // A timed-out mutant is not counted as killed: nothing showed a test fail.
    $statuses = ['✓' => 'killed', '⨯' => 'untested', '-' => 'uncovered', 't' => 'timeout'];
    $mutants = [];
    $seen = [];

    preg_match_all('/^\s*(✓|⨯|-|t)\s+Line (\d+): (\w+)\s*$/mu', $plain, $matches, PREG_SET_ORDER);

    foreach ($matches as [, $mark, $line, $mutator]) {
        $base = $line.':'.$mutator;
        $seen[$base] = ($seen[$base] ?? 0) + 1;
        $mutants[$base.'#'.$seen[$base]] = $statuses[$mark];
    }

    return $mutants;
}

/**
 * Whether a mutant key's line falls in one of the ranges.
 *
 * @param  list<array{0: int, 1: int}>  $ranges
 */
function secretStatesInRange(string $key, array $ranges): bool
{
    $line = (int) $key;

    foreach ($ranges as [$first, $last]) {
        if ($line >= $first && $line <= $last) {
            return true;
        }
    }

    return false;
}

/**
 * Run one state's mutation pass, echoing its output, and return the output.
 *
 * @param  array{env: array<string, string>, test: string}  $state
 */
function secretStatesRun(string $root, string $name, array $state): string
{
    $pest = $root.'/tests/Integration/vendor/bin/pest';

    if (! is_file($pest)) {
        secretStatesRefuse('the Integration setup is not installed; run `composer setup:integration` first');
    }

    $command = [
        PHP_BINARY,
        $pest,
        '--configuration=tests/Integration/phpunit.xml',
        '--test-directory=.',
        '--mutate',
        '--path='.SECRET_SOURCE,
        $state['test'],
    ];

    echo "\n== {$name}: ".implode(' ', array_map(static fn (string $key, string $value): string => $key.'='.$value, array_keys($state['env']), $state['env']))."\n";

    $environment = getenv();

    foreach ($state['env'] as $key => $value) {
        $environment[$key] = $value;
    }

    // Pest writes its results to stdout; stderr passes through to the log.
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => STDERR], $pipes, $root, $environment);

    if (! \is_resource($process)) {
        secretStatesRefuse("the {$name} process could not start");
    }

    $output = '';

    while (($chunk = fgets($pipes[1])) !== false) {
        echo $chunk;
        $output .= $chunk;
    }

    fclose($pipes[1]);
    $status = proc_close($process);

    if ($status !== 0) {
        secretStatesRefuse("the {$name} process exited {$status}");
    }

    return $output;
}

$root = \dirname(__DIR__);
$sourcePath = $root.'/'.SECRET_SOURCE;
$logs = [];

foreach (\array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--source=')) {
        $sourcePath = substr($argument, \strlen('--source='));
    } elseif (str_starts_with($argument, '--log=')) {
        [$state, $file] = explode('=', substr($argument, \strlen('--log=')), 2) + [1 => ''];
        $logs[$state] = $file;
    } else {
        secretStatesRefuse("unknown argument `{$argument}`");
    }
}

$source = is_file($sourcePath) ? file_get_contents($sourcePath) : false;

if ($source === false) {
    secretStatesRefuse("cannot read {$sourcePath}");
}

$ranges = secretStatesLines($source);

if ($ranges === []) {
    secretStatesRefuse("no shared-secret line found in {$sourcePath}");
}

echo 'mutate-secret-states: shared-secret lines '.implode(', ', array_map(static fn (array $range): string => $range[0].'-'.$range[1], $ranges))."\n";

$states = secretStates();

if ($logs !== [] && array_keys($logs) !== array_keys($states)) {
    secretStatesRefuse('pass one --log for each state, in order: '.implode(', ', array_keys($states)));
}

/** @var array<string, array<string, string>> $results */
$results = [];

foreach ($states as $name => $state) {
    if ($logs !== []) {
        $output = is_file($logs[$name]) ? file_get_contents($logs[$name]) : false;

        if ($output === false) {
            secretStatesRefuse("cannot read the {$name} log {$logs[$name]}");
        }
    } else {
        $output = secretStatesRun($root, $name, $state);
    }

    $mutants = secretStatesParse($output);

    if ($mutants === null) {
        secretStatesRefuse("the {$name} run printed no mutation summary");
    }

    $results[$name] = array_filter($mutants, static fn (string $key): bool => secretStatesInRange($key, $ranges), ARRAY_FILTER_USE_KEY);
}

$keys = array_keys($results[array_key_first($states)] ?? []);

if ($keys === []) {
    secretStatesRefuse('no mutant was reported on the shared-secret lines');
}

foreach ($results as $name => $mutants) {
    if (array_keys($mutants) !== $keys) {
        secretStatesRefuse("the {$name} run reported different mutants on the shared-secret lines");
    }
}

echo "\nmutate-secret-states: ".\count($keys).' mutants on the shared-secret lines, by state ('.implode(', ', array_keys($states)).")\n";

$survivors = [];

foreach ($keys as $key) {
    $row = array_values(array_map(static fn (array $mutants): string => $mutants[$key], $results));
    $killed = \in_array('killed', $row, true);
    printf("  %-8s %-40s %s\n", $killed ? 'killed' : 'NOT', $key, implode(' ', $row));

    if (! $killed) {
        $survivors[] = $key;
    }
}

if ($survivors !== []) {
    echo "\nmutate-secret-states: ".\count($survivors).' mutant(s) killed by no state: '.implode(', ', $survivors)."\n";

    exit(1);
}

echo "\nmutate-secret-states: every shared-secret mutant is killed in at least one state\n";

exit(0);
