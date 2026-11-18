Written against `main` after v0.8.9.

A migration pages through a site while editors keep working on it. This page covers how to read a whole collection without missing or repeating a record, and how to re-pull only what changed since the last run.

## Read a whole collection

1. Request page 1 with the page size you want: `GET /posts/<type>?per_page=200`. The default is 50; anything above 200 is clamped to 200 rather than refused.
2. Read `X-WP-Total` and `X-WP-TotalPages` from the response headers.
3. Request `page=2`, `page=3` and so on up to `X-WP-TotalPages`. A page past the last one returns an empty array, not an error.

**Every collection is ordered by ID ascending, never by date.** IDs never change, so a record edited during the run stays on the page it was on. Date ordering would let an edited record move to another page mid-run and be read twice or skipped. Terms are ordered by `term_id`; forms, feeds and redirect rules by their own ID.

**Note:** the totals are counted when each page is served. A record created during the run gets a higher ID than every record already read, so it lands at the end and is picked up by the last page or by the next incremental pull.

## Re-pull only what changed

`GET /posts/<type>` takes `modified_after`, which returns only records whose `post_modified_gmt` is strictly after the value:

```bash
curl -s -H "X-UAMSWP-Migration-Key: $KEY" \
  "$SITE/wp-json/uamswp-migration/v1/posts/page?modified_after=2026-11-01T00:00:00Z"
```

- **Accepted forms:** `YYYY-MM-DD`; `YYYY-MM-DD HH:MM` or `YYYY-MM-DDTHH:MM`, with optional seconds and fractional seconds; an optional zone of `Z` or an offset such as `-06:00`. A value with no zone is read as GMT.
- **Rejected:** relative strings (`now`, `yesterday`), a bare year, and impossible dates or times. The response is `400` `rest_invalid_param`, with `uamswp_migration_api_bad_modified_after` in its details. An empty value means no filter.
- **Records that were never published are always included.** WordPress leaves their GMT modified date zeroed, so the filter cannot place them in time; they come back on every pull rather than being lost.
- **Only post routes take it.** Terms and forms have no modified date in their tables, so their routes do not offer the parameter; re-read them whole.

**Warning:** `modified_after` has not yet been verified against a site being edited during the pull. See [known gaps](https://github.com/UAMS-Web/aether-wordpress-migration-api/blob/main/docs/known-gaps.md).

## Narrow a collection to known records

- `include=12,34,56` returns only those IDs. If `include` is present and none of its values is a valid ID, the collection is empty: it never falls back to every record.
- `status=publish,draft` limits the statuses. By default every registered status except `auto-draft` is served, including drafts, private posts and the trash.
- On attachments only, `file=2024/05/photo.jpg` (up to 100 values) finds attachments by their path relative to the uploads folder. A generated size such as `photo-750x629.jpg` resolves to its original attachment. `include` and `file` together return the union.

## Reference

- [Endpoints](Endpoints.md): the parameters of every route.
- [Errors](Errors.md): `rest_invalid_param` and the post-type errors.
