Written against `main` after v0.8.9.

- [Every request answers 401, including one with the right key](#every-request-answers-401-including-one-with-the-right-key)
- [A signed-in user gets 403](#a-signed-in-user-gets-403)
- [A request answers 200 with another site's content](#a-request-answers-200-with-another-sites-content)
- [A post type answers 404 on one site but works on another](#a-post-type-answers-404-on-one-site-but-works-on-another)
- [A form route answers 404 although the plugin is installed](#a-form-route-answers-404-although-the-plugin-is-installed)
- [`/post-types` answers 400 with `site`](#post-types-answers-400-with-site)
- [`modified_after` answers 400](#modified_after-answers-400)
- [An option is missing from `/options`](#an-option-is-missing-from-options)
- [A front-gated site refuses the token](#a-front-gated-site-refuses-the-token)

## Every request answers 401, including one with the right key

**Cause:** the API answers every failed credential with the same `401` `uamswp_migration_api_unauthorized`, so a token pasted with a stray space, a token from another network, a shared secret whose constant is not defined, and no key at all look the same.

**Fix:** generate a new token on the site's own token page, paste it again, and run the three requests on [Giving a consumer access](Giving-a-consumer-access.md#verify-with-three-requests-not-one). For the shared secret, check that `UAMSWP_MIGRATION_API_SECRET` is defined above the `/* That's all, stop editing! */` line. Rotated recently? The previous token stops working an hour after the new one is issued.

## A signed-in user gets 403

`uamswp_migration_api_forbidden`: "You do not have permission to read migration data for this site."

**Cause:** the user is signed in but lacks `manage_network_options` (multisite) or `manage_options` (single site) on the site being read. With `site`, that is the named site, not the one handling the request.

**Fix:** use the token instead of a user, or grant the capability on the site being read.

## A request answers 200 with another site's content

**Cause:** `site` was dropped from the request, so the site handling it answered.

**Fix:** compare `X-UAMSWP-Site` with the site you meant to read, on every response. See [Reading another site on a network](Reading-another-site-on-a-network.md).

## A post type answers 404 on one site but works on another

`uamswp_migration_api_type_not_registered_here`.

**Cause:** the post type is exposed by configuration but not registered on that site, usually because the plugin or theme that registers it is not active there.

**Fix:** none needed if the site has no such content. `GET /` on that site lists the post types it does expose. `uamswp_migration_api_unknown_post_type` instead means the type is not in the configuration at all ([Configuration and hooks](Configuration-and-hooks.md)).

## A form route answers 404 although the plugin is installed

`uamswp_migration_api_forms_plugin_not_active`.

**Cause:** the form plugin is not loaded in the PHP process handling the request. A plugin active only on the site named in `site`, and not on the site whose URL was called, is not loaded.

**Fix:** call the route on the URL of the site where the plugin is active. `GET /` shows `available: false` for a plugin the process cannot read. The redirect routes check the site being read, and answer `uamswp_migration_api_redirect_plugin_not_active` when the plugin is not active there.

## `/post-types` answers 400 with `site`

`uamswp_migration_api_registration_is_not_site_scoped`.

**Cause:** post types and taxonomies are registered once per PHP process, so switching sites does not change what is registered.

**Fix:** call `/post-types` and `/taxonomies` on the target site's own URL, without `site`.

## `modified_after` answers 400

`rest_invalid_param`, with `uamswp_migration_api_bad_modified_after` in its details.

**Cause:** the value is relative (`now`), partial (`2026`) or not a real date or time.

**Fix:** send `YYYY-MM-DD`, `YYYY-MM-DD HH:MM:SS` (read as GMT) or an ISO 8601 timestamp such as `2026-11-01T00:00:00Z`. See [Reading a live site in pages](Reading-a-live-site-in-pages.md#re-pull-only-what-changed).

## An option is missing from `/options`

**Cause:** its name matched the denylist, or its value looked like a credential. Either way it is counted in `denied` and left out.

**Fix:** if the value is not a secret, add an override in `config/local.php` ([Configuration and hooks](Configuration-and-hooks.md)). If `denylist.stale` is `true`, plugins were installed or removed since the denylist was reviewed, and the denylist itself needs a review.

## A front-gated site refuses the token

**Cause:** another plugin closes the site's REST API before routing. This plugin reports a valid key to that gate, but a gate that decided first is never overruled.

**Fix:** see [What answering the authentication filter means for a site with its own gate](Giving-a-consumer-access.md#what-answering-the-authentication-filter-means-for-a-site-with-its-own-gate).
