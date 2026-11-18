Written against `main` after v0.8.9.

Every error is a standard WordPress REST error: a JSON body with `code`, `message` and `data.status`. Match on `code`, not on the message.

## Credentials

| Code | Status | When |
| --- | --- | --- |
| `uamswp_migration_api_unauthorized` | 401 | No valid credential: no key, a wrong key, an unset secret, or no signed-in user. Deliberately identical in every case. |
| `uamswp_migration_api_forbidden` | 403 | A signed-in WordPress user without the capability on the site being read. |

## Site

| Code | Status | When |
| --- | --- | --- |
| `uamswp_migration_api_not_multisite` | 400 | `site` given on a single-site install. |
| `uamswp_migration_api_invalid_site` | 400 | `site` is not a whole number of 1 or more. |
| `uamswp_migration_api_unknown_site` | 404 | No site with that ID on the network. |
| `uamswp_migration_api_unavailable_site` | 409 | The site is marked archived, deleted or spam. |
| `uamswp_migration_api_registration_is_not_site_scoped` | 400 | `site` given to `/post-types` or `/taxonomies`. |

## Content

| Code | Status | When |
| --- | --- | --- |
| `uamswp_migration_api_unknown_post_type` | 404 | The post type is not one this plugin exposes. |
| `uamswp_migration_api_type_not_registered_here` | 404 | The post type is exposed but not registered on the site being read. |
| `uamswp_migration_api_unknown_taxonomy` | 404 | The taxonomy is not one this plugin exposes. |
| `uamswp_migration_api_taxonomy_not_registered_here` | 404 | The taxonomy is exposed but not registered on the site being read. |
| `uamswp_migration_api_file_not_attachment` | 400 | `file` given on a post type other than `attachment`. |
| `uamswp_migration_api_bad_modified_after` | 400 | `modified_after` is not a timestamp the route can read; returned inside `rest_invalid_param`. |
| `uamswp_migration_api_not_found` | 404 | No post, term or form with that ID (a post of another type counts as not found), or a trashed form. |

## Forms and redirects

| Code | Status | When |
| --- | --- | --- |
| `uamswp_migration_api_forms_plugin_not_active` | 404 | The form plugin is not loaded in the process handling the request. |
| `uamswp_migration_api_feed_query_failed` | 500 | Gravity Forms failed to read feeds for a reason other than a missing table or an empty result. |
| `uamswp_migration_api_redirect_plugin_not_loaded` | 404 | The redirect plugin is not loaded. |
| `uamswp_migration_api_redirect_plugin_not_active` | 404 | The redirect plugin is loaded but not active on the site being read. |
| `uamswp_migration_api_redirect_tables_missing` | 500 | The redirect plugin is active but its tables do not exist. |
| `uamswp_migration_api_redirect_read_failed` | 500 | A database error while reading redirect rules. |

## From WordPress itself

| Code | Status | When |
| --- | --- | --- |
| `rest_invalid_param` | 400 | `page` or `per_page` below 1 or not a number, an `include` value that is not a number, more than 100 `file` values, or an invalid `modified_after`. |
| `rest_no_route` | 404 | A path that matches no route, including a type, taxonomy or ID with characters the route does not allow. |
