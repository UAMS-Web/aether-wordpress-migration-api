Written against `main` after v0.8.9.

The plugin has no settings screen beyond the token page. What it serves is decided by a configuration file, one constant and four filters.

## Configuration file

[`config/defaults.php`](https://github.com/UAMS-Web/aether-wordpress-migration-api/blob/main/config/defaults.php) holds the defaults. An optional `config/local.php` beside it, which is not tracked, replaces any of its top-level keys, and the `uamswp_migration_api_config` filter can change the result.

| Key | Type | Default | Effect |
| --- | --- | --- | --- |
| `post_types` | list of strings | the post types the migration reads | Post types `/posts/<type>` serves. A type not registered on a site is skipped there. Revisions, ACF's own field definitions and oEmbed caches are left out on purpose. |
| `taxonomies` | list of strings | the taxonomies the migration reads | Taxonomies `/terms/<taxonomy>` serves and post records carry in `terms`. |
| `option_denylist` | list of patterns | transients, and names that end in or contain key, token, secret, password, credential, license or salt | Options left out of `/options`. Patterns use SQL `LIKE` wildcards and match the whole name, ignoring case. |
| `option_denylist_nested_keys` | list of patterns | a shorter list of the same kind | Keys withheld inside a decoded option or form setting. |
| `option_denylist_reviewed_against` | list of plugin slugs | the plugins the denylist was last reviewed against | Compared with the installed plugins to set `denylist.stale` on `/options`. |
| `identifiers` | map of post type to identifier definitions | a few post types | The plain-string identifiers a post record carries in `identifiers`. |
| `per_page_default` | integer | `50` | The page size when `per_page` is omitted. |
| `per_page_max` | integer | `200` | The largest page size; larger values are clamped to it. |

Values that look like credentials are withheld whatever their option is called, so an option the denylist misses still does not hand out a token. See [Response shapes](Response-shapes.md).

## Constant

| Constant | Set in | Effect |
| --- | --- | --- |
| `UAMSWP_MIGRATION_API_SECRET` | `wp-config.php` | Turns on the shared-secret credential. Undefined or empty, that path accepts nothing. See [Giving a consumer access](Giving-a-consumer-access.md). |

## Filters

| Filter | Filters | Default |
| --- | --- | --- |
| `uamswp_migration_api_config` | The whole configuration array. A result that is not an array is ignored. | `config/defaults.php` merged with `config/local.php` |
| `uamswp_migration_api_capability` | The capability a signed-in user needs, on the site being read. It also gates the token page. | `manage_network_options` on a multisite, `manage_options` on a single site |
| `uamswp_migration_api_token_rotation_window` | Seconds the previous token keeps working after a new one is issued. Negative values count as `0`. | `3600` |
| `uamswp_migration_api_controllers` | The list of route controllers registered. Entries that are not controllers are dropped. | the eight built-in controllers |

## Example

A `config/local.php` that serves one extra post type and smaller pages:

```php
<?php

$defaults = require __DIR__.'/defaults.php';

return [
    'post_types' => [...$defaults['post_types'], 'example_type'],
    'per_page_default' => 25,
];
```
