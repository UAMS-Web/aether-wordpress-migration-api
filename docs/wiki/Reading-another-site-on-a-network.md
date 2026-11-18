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

## Two routes read the site you call, not the one you name

`GET /post-types` and `GET /taxonomies` describe what is registered in the PHP process handling the request, and registration does not change when the API switches sites. They refuse `site` with `400` `uamswp_migration_api_registration_is_not_site_scoped`. Call them on the target site's own URL instead.

The form and redirect routes have a related limit: a form plugin that is not loaded in the process handling the request cannot be read through `site`, and the error message says so. See [Troubleshooting](Troubleshooting.md).

## Reference

- [Endpoints](Endpoints.md): `site` on every route.
- [Errors](Errors.md): the site errors.
