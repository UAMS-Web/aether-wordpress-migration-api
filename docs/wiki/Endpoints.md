Written against `main` after v0.8.9.

Every route is `GET` only, lives under `/wp-json/uamswp-migration/v1`, and needs a credential ([Giving a consumer access](Giving-a-consumer-access.md)). Every route takes `site` ([Reading another site on a network](Reading-another-site-on-a-network.md)). Routes marked *paginated* take `page` and `per_page` and return `X-WP-Total` and `X-WP-TotalPages` ([Reading a live site in pages](Reading-a-live-site-in-pages.md)).

The fields of each record are on [Response shapes](Response-shapes.md); the error codes on [Errors](Errors.md).

## Common parameters

| Parameter | Type | Default | Effect |
| --- | --- | --- | --- |
| `site` | integer, 1 or more | the site handling the request | Reads that site of the network. Refused on a single-site install. |
| `page` | integer, 1 or more | `1` | The page to return. |
| `per_page` | integer, 1 or more | `50` | Records per page. Values above `200` are clamped to `200`. |

## Index and discovery

| Route | Paginated | Returns |
| --- | --- | --- |
| `/` | no | The plugin name and a version number (not yet the plugin's release: [#98](https://github.com/UAMS-Web/aether-wordpress-migration-api/issues/98)), the site that answered, the exposed post types and taxonomies, the pagination defaults, and each route family with its parameters and an `available` flag for the form and redirect plugins. |
| `/sites` | no | Every site on the network (one entry on a single site), by ID, with the WXR channel fields and record counts. |
| `/post-types` | no | Every registered post type, with whether it is exposed and its counts by status. Refuses `site` naming another site. |
| `/taxonomies` | no | Every registered taxonomy, with whether it is exposed and its term count. Refuses `site` naming another site. |
| `/options` | no | The site's options table, with secrets withheld, plus ACF options-page values and the active plugins. |
| `/authors` | yes | Every user who authored a post that is not an auto-draft. Also returns `X-UAMSWP-Orphaned-Authors` (the count of author IDs that match no user) and `X-UAMSWP-Orphaned-Author-Ids` (up to 200 of those IDs). |

## Content

| Route | Paginated | Returns |
| --- | --- | --- |
| `/posts/<type>` | yes | Post records of an exposed post type registered for the site whose URL was called, lowest ID first. |
| `/posts/<type>/<id>` | no | One post record. |
| `/terms/<taxonomy>` | yes | Term records of an exposed taxonomy registered for the site whose URL was called, empty terms included, lowest ID first. |
| `/terms/<taxonomy>/<id>` | no | One term record. |

`/posts/<type>` also takes:

| Parameter | Type | Default | Effect |
| --- | --- | --- | --- |
| `status` | list of strings | every registered status except `auto-draft` | Only these statuses. Unknown values are dropped; if none remain, every status is served. |
| `modified_after` | timestamp | none | Only records modified strictly after it, plus records never published. |
| `include` | list of integers | none | Only these IDs. Present but with no valid ID, the collection is empty. |
| `file` | list of strings, up to 100 | none | Attachments only: the attachments at these paths under the uploads folder. A generated size resolves to its original. |

## Forms

Each form route answers `404` `uamswp_migration_api_forms_plugin_not_active` when its plugin is not loaded. Trashed forms are not served. Form entries and submissions are never served.

| Route | Paginated | Returns |
| --- | --- | --- |
| `/forms/gravity` | yes | Gravity Forms form definitions, as `GFAPI` returns them. |
| `/forms/gravity/<id>` | no | One Gravity Forms form. |
| `/forms/gravity/feeds` | yes | Every add-on feed of a form that exists and is not trashed, by feed ID. |
| `/forms/gravity/<id>/feeds` | no | That form's feeds. |
| `/forms/formidable` | yes | Formidable forms, with their fields and actions. |
| `/forms/formidable/<id>` | no | One Formidable form. |
| `/forms/ninja` | yes | Ninja Forms forms, with their fields and actions. |
| `/forms/ninja/<id>` | no | One Ninja Forms form. |

## Redirects

Each redirect route answers `404` when its plugin is not loaded or not active on the site being read.

| Route | Paginated | Returns |
| --- | --- | --- |
| `/redirects/redirection` | yes | Redirection rules, with their group. |
| `/redirects/pretty-links` | yes | Pretty Links rows, without the click counts. |

## Example

```bash
curl -s -D - -H "X-UAMSWP-Migration-Key: $KEY" \
  "https://example.test/wp-json/uamswp-migration/v1/terms/category?per_page=100&page=2"
```
