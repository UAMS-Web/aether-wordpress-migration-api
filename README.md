# aether-wordpress-migration-api

Read-only migration API for the UAMS WordPress network. Serves raw, current content to the [Statamic importer](https://github.com/UAMS-Web/wordpress-importer) in place of hand-made WordPress XML (WXR) exports and plugin export files.

**Status: built.** Every endpoint this README describes is served, and the plugin ships as versioned pre-releases. The migration it serves is tracked by [`wordpress-importer#358`](https://github.com/UAMS-Web/wordpress-importer/issues/358) (epic).

## Why this exists

The importer originally sourced from WXR exports. Three findings retired that approach:

1. **Large sites cannot export.** WordPress' exporter builds the entire XML in one request; on `uamshealth.com` it does not reliably complete. Pagination solves this by construction.
2. **WXR carries stale ACF values for `location` and `provider`.** Find-a-Doc disables `store_acf_values_in_core_meta`, so those values live only in ACF custom database tables while postmeta sits frozen at whatever it held when the filter was enabled. Reading through `acf_get_value()` returns current values with no write to production.
3. **WXR has no element for options.** Zero of 259 captured exports contain `<wp:option>`. Every site migrated to date has silently dropped its settings.

Form definitions (Gravity Forms, Formidable) and MinervaKB configuration have the same defect from a different direction — they come from export files a human produces by hand, stale the moment they are committed.

## Design principles

- **Strict superset.** Everything a WXR export emits, this API emits — mapped element for element — plus the gaps above.
- **Raw, not curated.** Values are returned un-resolved and un-composed. No rendering, no system+local merge, no hardcoded section composition. The existing `wp/v2` syndication view is what happens otherwise, and it is lossy for migration.
- **Read through each plugin's own API, never raw SQL.** `acf_get_value()` for ACF, `GFAPI` for Gravity Forms, the `Frm*` models for Formidable. The plugin's API is the only version-safe reader of its own schema.
- **Never filter underscore-prefixed keys.** ACF field-key references (`_fieldname`, `_options_<field>`), navigation (`_menu_item_*`) and featured images (`_thumbnail_id`) all live behind that prefix. A blanket `NOT LIKE '\_%'` filter silently breaks all three.
- **Both meta shapes on every record.** `meta` carries raw flat postmeta exactly as WXR would serialize it, so existing importers run unchanged; `acf` carries structured values, which is the only shape available for `location` and `provider`.

## Scope

Post types, taxonomy terms, options, authors, attachments, Gravity Forms / Formidable / Ninja Forms definitions, and Redirection / Pretty Links redirect rules — network-wide, configuration-driven, paginated, authenticated, serving non-`publish` statuses and incremental re-pulls via `modified_after`.

## Documentation

The [wiki](https://github.com/UAMS-Web/aether-wordpress-migration-api/wiki) holds the long-form documentation: a first read, the task guides, the reference for every route, response field, error code, configuration key and filter, and troubleshooting. Its pages are written in [`docs/wiki/`](docs/wiki/) and published from there, so edit them through a pull request.

## Giving a consumer access

Every route needs one of three credentials: a token issued from this plugin's own admin page (**Settings, Migration API token**), a WordPress user holding the capability on the site being read, or a shared secret in `wp-config.php`. There is no anonymous path. **Use the token**, and verify it with three requests, not one: **every failed key returns an identical `401`**, so only a wrong-key request that is refused proves the right key is required.

The procedure, the three-request check and what the plugin's answer to the authentication filter means for a site with its own REST gate are on [Giving a consumer access](https://github.com/UAMS-Web/aether-wordpress-migration-api/wiki/Giving-a-consumer-access) ([source](docs/wiki/Giving-a-consumer-access.md)).

## Running the tests

The suite has two halves. `tests/Unit` runs in a process with no WordPress at all; `tests/Integration/Feature` boots real WordPress through `wp-phpunit` and dispatches real REST requests. `composer test` runs both, plus a third process covering the shared-secret branch that only exists when its constant is undefined.

```bash
composer install
composer setup:integration
mysql -u root -h 127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS uamswp_migration_api_tests DEFAULT CHARACTER SET utf8mb4;"
composer test
```

ACF comes with `composer setup:integration` — the free copy, pinned to the same minor as the PRO the network runs. If this checkout sits inside a WordPress install that has ACF PRO alongside it, the suite loads that instead and picks up the PRO-only field types (`repeater`, `flexible_content`, `clone`); otherwise those few tests skip and say so. See `docs/DECISIONS.md` D-030.

That is the whole setup. WordPress is located from this checkout's own position inside `wp-content/plugins/`, and the database defaults suit a stock Herd MySQL, so there is nothing machine-specific to configure. Override any of it with `WP_TESTS_ABSPATH`, `WP_TESTS_DB_NAME`, `WP_TESTS_DB_USER`, `WP_TESTS_DB_PASSWORD` or `WP_TESTS_DB_HOST`.

**A checkout outside a WordPress install** works too, given a WordPress root on the same machine to boot (D-113). Point the suites at it with `WP_TESTS_ABSPATH=/path/to/wordpress composer test`, and the local CI chain with `LOCAL_CI_WP_ABSPATH=/path/to/wordpress npm run ci:local -- --branch <branch>`. The chain creates its slot beside the checkout and installs that slot's dependencies itself. Without the variable, the chain's `pest` job stops and names it.

**The test database is dropped and rebuilt on every run.** `tests/Integration/bootstrap.php` refuses to start if the name matches the database the surrounding WordPress install uses, because the installer drops every table it finds with no prompt and no dry run.

| Command | What it runs |
| --- | --- |
| `composer test` | Both suites, then the shared-secret process, then the suite that boots WordPress and the shared-secret process again on a single site |
| `composer test:unit` | Only the WordPress-free suite |
| `composer test:integration` | Only the suite that boots WordPress, as a network |
| `composer test:integration:single-site` | The same suite, as a single-site install |
| `composer mutate` | Mutation testing over the pure-logic classes |
| `composer ci` | PHPStan, Pint, then all of the above |

The suites run on **PHP 8.4**; the plugin supports **PHP 8.2**, the oldest version production runs. `tests/Unit` runs on Pest 5 from the root setup. The suite that boots WordPress has its own Composer setup in `tests/Integration/` (Pest 3 and PHPUnit 11), installed by `composer setup:integration`, because WordPress' test bootstrap needs a polyfill library that does not support the PHPUnit Pest 5 requires. The CI chain's `lint-floor` job parses every shipped file with PHP 8.2, and its `floor-functions` job fails on a shipped call to a function PHP 8.2 lacks that only a development polyfill defines. See `docs/DECISIONS.md` D-092.

## Running CI

```bash
npm install
npm run ci:local -- --branch <branch>
```

That runs the shared local CI runner over [`local-ci.json`](local-ci.json): the same eighteen checks GitHub runs (the load guard, the PHP 8.2 floor lint, the PHP 8.2 check for polyfilled functions, the glyph audit, the handed-back workflow check, the download-archive check, cspell, then the eight jobs of the shared chain `ci.yml` calls (`composer validate`, the synced-files check, the no-emoji sweep, the aggregate-needs check, Pint, Rector, PHPStan and the mutation-annotation check), then the mutation-annotation breadth check, Pest and Node's own test runner), plus a local-only install step, as a cost-ordered chain that stops at the first failure. The pushed branch is checked out into the dotted sibling worktree `.uamswp-migration-api-local-ci`, so the checkout you are working in never moves, and the `pest` job uses its own `uamswp_migration_api_ci` database so it cannot collide with a suite you are running by hand. The first run in a new worktree needs `composer install` and `composer setup:integration` there once; after that, the runner installs from the locks on every run.

`composer ci` is a **subset**: three of the checks, run in place. Use it while iterating; use the chain before opening a pull request.

The same jobs run on GitHub Actions on every pull request, and `main` requires three checks: `CI`, which fails unless every job in `ci.yml` succeeded, the call to the shared chain included, `Attribution footer` and `Closing keywords`. The local chain is for validating a branch before it is pushed; it does not gate a merge. `.github/workflows/ci.yml` and `local-ci.json` are hand-maintained mirrors: a pre-commit hook and `tests/Unit/CiParityTest.php` keep them in step.

## Temporary by design

This plugin exists for the WordPress → Statamic migration. When that completes it is deactivated and deleted — one plugin removed, rather than dead code unpicked from `UAMSWP-Find-a-Doc`.

## Renamed to `aether-wordpress-migration-api`

The repository is `UAMS-Web/aether-wordpress-migration-api`, and the old `UAMS-Web/uamswp-migration-api` address redirects to it for the web, `git` and the API. Composer does not redirect a package name, so a consumer changes what it names:

- **Composer package:** `uams-web/uamswp-migration-api` becomes `uams-web/aether-wordpress-migration-api`.
- **PHP namespace:** `UAMSWP\MigrationAPI` becomes `UAMSWeb\Aether\WordPressImporter\MigrationApi`, mapped to `includes/` by `autoload.psr-4`.
- **Plugin file, header and text domain:** the main file is `aether-wordpress-migration-api.php`, its header names `Aether WordPress Migration API`, and its text domain is `aether-wordpress-migration-api` (#93). A WordPress install keys an active plugin by its folder and main file, so an install updated past this change no longer finds the file it activated; moving installs to the new file and folder is #94.
- **Unchanged:** the folder name `uamswp-migration-api` on existing installs, the REST namespace `uamswp-migration/v1`, the `UAMSWP_MIGRATION_API_*` constants and the settings page slug.
- **A checkout** points at the new address with `git remote set-url origin https://github.com/UAMS-Web/aether-wordpress-migration-api.git`.
- **A site deployed with WP Pusher** keeps pointing at the old repository name, which GitHub redirects, and keeps its plugin folder. After the first release under the new name, check that one site updates in place.

## Credits

Developed and maintained by UAMS Web Services.

Developers:

- Josh Daugherty
- Brent Passmore
