Written against `main` after v0.8.9.

The plugin is developed in [`UAMS-Web/aether-wordpress-migration-api`](https://github.com/UAMS-Web/aether-wordpress-migration-api) by UAMS Web Services. Its consumer is the [Statamic importer](https://github.com/UAMS-Web/wordpress-importer), and the migration it serves is tracked in [`wordpress-importer#358`](https://github.com/UAMS-Web/wordpress-importer/issues/358).

## Temporary by design

The plugin exists for the WordPress to Statamic migration. When that completes it is deactivated and deleted. It creates no tables and writes one network option, the token's hash.

## What it does not do yet

[`docs/known-gaps.md`](https://github.com/UAMS-Web/aether-wordpress-migration-api/blob/main/docs/known-gaps.md) lists what the plugin does not serve (form entries, comments) and what has not yet been proven on a real install. Read it before calling a behavior verified.

## Decisions

Decisions that outlive their ticket are in [`docs/DECISIONS.md`](https://github.com/UAMS-Web/aether-wordpress-migration-api/blob/main/docs/DECISIONS.md), numbered `D-001` onward.

## Report a problem

Open an issue in the [repository](https://github.com/UAMS-Web/aether-wordpress-migration-api/issues). Say which route, which parameters, the status and error `code` you got, and the `X-UAMSWP-Site` header of the response. Leave out the token, host names and anything copied from a site's options.

## Contributing and releases

[`CONTRIBUTING.md`](https://github.com/UAMS-Web/aether-wordpress-migration-api/blob/main/CONTRIBUTING.md) covers the path from an issue to a merge. Releases are listed on the [releases page](https://github.com/UAMS-Web/aether-wordpress-migration-api/releases), and this wiki is republished from [`docs/wiki/`](https://github.com/UAMS-Web/aether-wordpress-migration-api/tree/main/docs/wiki) when one is published.
