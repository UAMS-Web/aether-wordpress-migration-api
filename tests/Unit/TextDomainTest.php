<?php

declare(strict_types=1);

/*
 * Holds the text domain to the main plugin file's name (#93).
 *
 * WordPress expects a plugin's text domain to be its slug, and nothing in the
 * plugin translates anything today, so a domain left on an old name has no
 * runtime effect and no behavioral test can see it. A static scan is the only
 * place a stale domain shows: the header's `Text Domain` and the domain
 * argument of every translation call, read with the tokenizer so a comment
 * naming a function is not a call.
 */

/**
 * The translation functions, each with the zero-based position of its domain argument.
 */
const TEXT_DOMAIN_FUNCTIONS = [
    '__' => 1, '_e' => 1, 'esc_html__' => 1, 'esc_html_e' => 1, 'esc_attr__' => 1, 'esc_attr_e' => 1,
    '_x' => 2, '_ex' => 2, 'esc_html_x' => 2, 'esc_attr_x' => 2, '_n_noop' => 2,
    '_n' => 3, '_nx_noop' => 3,
    '_nx' => 4,
];

/**
 * The repository root.
 */
function textDomainRoot(): string
{
    return \dirname(__DIR__, 2);
}

/**
 * The repo-relative paths of root PHP files carrying a `Plugin Name:` header.
 *
 * @return list<string>
 */
function textDomainMainFiles(): array
{
    $found = [];

    foreach (glob(textDomainRoot().'/*.php') ?: [] as $path) {
        if (preg_match('/^\s*\*\s*Plugin Name:/m', (string) file_get_contents($path)) === 1) {
            $found[] = basename($path);
        }
    }

    sort($found);

    return $found;
}

/**
 * One header field of a plugin file, or null when the file does not carry it.
 */
function textDomainHeader(string $source, string $field): ?string
{
    if (preg_match('/^\s*\*\s*'.preg_quote($field, '/').':\s*(.+?)\s*$/m', $source, $match) !== 1) {
        return null;
    }

    return $match[1];
}

/**
 * The main file and every PHP file under `includes/`, keyed by repo-relative path.
 *
 * @return array<string, string>
 */
function textDomainSources(string $mainFile): array
{
    $root = textDomainRoot();
    $sources = [$mainFile => (string) file_get_contents($root.'/'.$mainFile)];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/includes', FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $entry) {
        if ($entry instanceof SplFileInfo && $entry->isFile() && $entry->getExtension() === 'php') {
            $sources[substr($entry->getPathname(), \strlen($root) + 1)] = (string) file_get_contents($entry->getPathname());
        }
    }

    ksort($sources);

    return $sources;
}

/**
 * Every translation call in a source, with its domain argument.
 *
 * The domain is the argument at the function's domain position when that
 * argument is one string literal, and null otherwise: a missing domain, a
 * variable or a constant all come back null, so the caller reports them
 * rather than skipping them.
 * Method and static calls (`->__()`, `::__()`) and declarations are not
 * WordPress' functions and are left out.
 *
 * @return list<array{line: int, function: string, domain: ?string}>
 */
function textDomainCalls(string $source): array
{
    $tokens = array_values(array_filter(
        token_get_all($source),
        static fn (array|string $token): bool => ! \is_array($token) || ! \in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
    ));
    $calls = [];
    $count = \count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (! \is_array($token) || ! \in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
            continue;
        }

        $name = ltrim($token[1], '\\');
        $next = $tokens[$i + 1] ?? null;
        $previous = $tokens[$i - 1] ?? null;

        if (! isset(TEXT_DOMAIN_FUNCTIONS[$name]) || $next !== '(') {
            continue;
        }

        if (\is_array($previous) && \in_array(strtolower($previous[1]), ['->', '?->', '::', 'function'], true)) {
            continue;
        }

        // Walk the argument list, splitting it at the commas of its own depth.
        $depth = 0;
        $arguments = [[]];

        for ($j = $i + 1; $j < $count; $j++) {
            $part = $tokens[$j];
            $text = \is_array($part) ? $part[1] : $part;

            if (\in_array($text, ['(', '[', '{'], true) || (\is_array($part) && \in_array($part[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;

                if ($depth === 1) {
                    continue;
                }
            } elseif (\in_array($text, [')', ']', '}'], true)) {
                $depth--;

                if ($depth === 0) {
                    break;
                }
            } elseif ($text === ',' && $depth === 1) {
                $arguments[] = [];

                continue;
            }

            $arguments[\count($arguments) - 1][] = $part;
        }

        $domainTokens = $arguments[TEXT_DOMAIN_FUNCTIONS[$name]] ?? [];
        $domain = null;

        if (\count($domainTokens) === 1 && \is_array($domainTokens[0]) && $domainTokens[0][0] === T_CONSTANT_ENCAPSED_STRING) {
            $domain = substr($domainTokens[0][1], 1, -1);
        }

        $calls[] = ['line' => $token[2], 'function' => $name, 'domain' => $domain];
    }

    return $calls;
}

it('has exactly one main plugin file, and its header names it', function (): void {
    expect(textDomainMainFiles())->toBe(['aether-wordpress-migration-api.php']);

    $source = (string) file_get_contents(textDomainRoot().'/aether-wordpress-migration-api.php');

    expect(textDomainHeader($source, 'Plugin Name'))->toBe('Aether WordPress Migration API')
        ->and(textDomainHeader($source, 'Text Domain'))->toBe('aether-wordpress-migration-api')
        ->and(file_exists(textDomainRoot().'/uamswp-migration-api.php'))->toBeFalse();
});

it("passes the main file's name as the domain of every translation call", function (): void {
    $mainFile = textDomainMainFiles()[0] ?? '';
    $slug = basename($mainFile, '.php');
    $wrong = [];
    $seen = 0;

    foreach (textDomainSources($mainFile) as $path => $source) {
        foreach (textDomainCalls($source) as $call) {
            $seen++;

            if ($call['domain'] !== $slug) {
                $wrong[] = \sprintf('%s:%d %s() domain %s', $path, $call['line'], $call['function'], var_export($call['domain'], true));
            }
        }
    }

    // A scan that found no calls has checked nothing: the plugin has dozens.
    expect($seen)->toBeGreaterThan(10)
        ->and($wrong)->toBe([]);
});

it('reads the domain argument the way the scan above relies on', function (): void {
    // The control: a source whose answers are known, including each shape the
    // scan must not get wrong. It is synthetic, so it says so; the real files
    // are what the test above reads.
    $source = <<<'PHP'
        <?php
        __('One', 'right');
        \esc_html__('Two', 'wrong');
        _x('Three', 'context', 'right');
        _n(
            'Four',
            'Fours',
            count([1, 2]),
            'right',
        );
        __('No domain');
        __('Variable', $domain);
        sprintf(esc_html__('Nested %s', 'right'), 'x');
        $object->__('Method', 'ignored');
        Thing::__('Static', 'ignored');
        // __('Comment', 'ignored');
        PHP;

    $calls = array_map(
        static fn (array $call): string => $call['function'].'='.var_export($call['domain'], true),
        textDomainCalls($source),
    );

    expect($calls)->toBe([
        "__='right'",
        "esc_html__='wrong'",
        "_x='right'",
        "_n='right'",
        '__=NULL',
        '__=NULL',
        "esc_html__='right'",
    ]);
});
