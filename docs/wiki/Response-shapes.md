Written against `main` after v0.8.9.

Values are returned raw: as stored, un-resolved and un-composed. Nothing is rendered, merged or resolved to a display value. This page lists the fields of each record; the routes that return them are on [Endpoints](Endpoints.md).

## Post record

| Field | Holds |
| --- | --- |
| `id`, `type`, `slug`, `status`, `title` | The post's own columns. |
| `author` | `{id, login, display_name}`; `login` and `display_name` are `null` when the author matches no user. |
| `date_created`, `date_created_gmt`, `date_modified`, `date_modified_gmt` | The post's dates, local and GMT. |
| `content`, `excerpt` | Stored content, not rendered. |
| `parent`, `menu_order`, `guid`, `is_sticky`, `ping_status`, `comment_status`, `post_password` | The remaining columns WXR carries. |
| `link` | The permalink, as a string (empty when WordPress has none). `null` when WordPress threw while computing it, as it does for a record whose status object a plugin has replaced with one that hides `_builtin`; the rest of the record is served unchanged. |
| `terms` | A map of taxonomy to `[{term_id, slug, name, parent, description}]`, for exposed taxonomies only. |
| `attachment` | `null` except on attachments: `{url, mime_type, file, alt, metadata}`, with `file` relative to the uploads folder. |
| `meta` | Every postmeta row exactly as stored, as `key: [values]`. Underscore-prefixed keys are included. A TablePress table also carries `_tablepress_export_table_id`, which the API adds. |
| `acf` | `{keys, names, name_collisions, defaulted: {keys, names}}`: ACF values read through ACF itself, by field key and by field name. |
| `identifiers` | `null`, or a map of configured identifier names to a string (or a list of strings), for the post types configured to carry them. |

**Both meta shapes are on every record.** `meta` is what WXR would serialize, so an importer written for WXR runs unchanged. `acf` is read through ACF's own value function, which is the only route to values a site keeps in ACF's custom tables rather than in postmeta.

## Term record

`id`, `taxonomy`, `name`, `slug`, `description`, `parent`, `parent_slug`, `term_taxonomy_id`, `count`, `link`, `meta`, and `acf` in the same shape as a post's.

## Author record

`id`, `login`, `email`, `display_name`, `first_name`, `last_name`, `nicename`.

## Options

| Field | Holds |
| --- | --- |
| `options` | Option name to value, with serialized values decoded. Withheld options are left out. |
| `acf` | ACF options-page values by page, with withheld fields removed. |
| `denylist` | `{reviewed_against, current, stale, added, removed}`: `reviewed_against` and `current` count the plugins the denylist was reviewed against and the plugins active now; `added` and `removed` list the difference; `stale` is `true` when an active plugin is missing from the reviewed list (a removed one does not set it). |
| `denied` | `{total, by_name, by_shape}`: how many values were withheld. Counts only, never names. |
| `plugins` | `[{path, scope, header}]`: each active plugin, `site` or `network`, with its name, version and text domain; `header` is `null` when the active path is not installed. |

An option is withheld when its name matches the denylist or its value looks like a credential (a token, a key or a long encoded run), at any depth inside a decoded value. ACF field-key references (`_options_<field>`) are never withheld. See [Configuration and hooks](Configuration-and-hooks.md).

## Forms

- **Gravity Forms:** the form array exactly as `GFAPI` returns it. A feed is `{id, form_id, addon_slug, meta_readable, is_active, feed_order, event_type, meta, denied}`, with credential-like values withheld from `meta`.
- **Formidable:** `{id, form_key, name, description, parent_form_id, status, is_template, default_template, logged_in, editable, created_at, options, fields, actions}`. Each field carries its `options` and `field_options`; each action carries `settings`, with credential-like values withheld, and `denied`.
- **Ninja Forms:** `{id, key, title, settings, fields, actions}`, each field and action flattened to its ID and settings.

## Redirects

- **Redirection:** `{id, url, match_url, regex, match_type, match_data, action_type, action_code, action_data, action_data_parsed, title, position, enabled, group_id, group}`, where `group` is `{id, name, module_id, enabled}` or `null`.
- **Pretty Links:** the plugin's own row, without `clicks` and `uniques`.
