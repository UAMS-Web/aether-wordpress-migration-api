Written against `main` after v0.8.9.

On a multisite network, every route reads one site. This page covers choosing that site with `site`, and checking which site answered.

## Choose the site

Add `site=<blog id>` to any route:

```bash
curl -s -H "X-UAMSWP-Migration-Key: $KEY" \
  "$SITE/wp-json/uamswp-migration/v1/posts/page?site=3"
```

`GET /sites` lists every site on the network with its ID, lowest first.

- **Omit it** and the request is served by whichever site handled it, which is the site whose URL you called.
- **The capability is checked on the site being read**, not on the site handling the request, so a signed-in user needs it there.
- **On a single-site install** `site` is refused with `400` `uamswp_migration_api_not_multisite`. Leave it out.
- **An ID that is not on the network** returns `404` `uamswp_migration_api_unknown_site`; a site marked archived, deleted or spam returns `409` `uamswp_migration_api_unavailable_site`.

## Check which site answered

Every successful response carries `X-UAMSWP-Site`, the blog ID of the site that answered it. **Check it.** A consumer that drops `site` from a request still gets a well-formed `200`, from the wrong site, and the header is the only thing in the response that says so.

Error responses over HTTP carry it too, and a refusal names the site being read, since the site is chosen before the credential is checked.

## What `site` does not switch

Post types and taxonomies are registered once, for the site whose URL was called, and switching sites does not register them again. So under `site`:

- **`/posts/<type>` and `/terms/<taxonomy>`** serve a type or taxonomy only if the site whose URL was called registers it. A type that only the named site registers answers `404` `uamswp_migration_api_type_not_registered_here` (or `uamswp_migration_api_taxonomy_not_registered_here`), and the message says to request it from that site's own URL.
- **`/post-types` and `/taxonomies`** describe the registration of the site whose URL was called, so they refuse `site` naming another site with `400` `uamswp_migration_api_registration_is_not_site_scoped`. Call them on the target site's own URL instead.
- **The form routes** read a form plugin only if it is loaded for the site whose URL was called, and the error message says so. The redirect routes check the named site.

When a type, taxonomy or plugin differs between sites, call each site on its own URL. See [Troubleshooting](Troubleshooting.md).

## Reference

- [Endpoints](Endpoints.md): `site` on every route.
- [Errors](Errors.md): the site errors.
