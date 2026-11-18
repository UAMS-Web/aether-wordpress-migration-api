Written against `main` after v0.8.9.

At the end of this page you will have a token for one site, proof that the API accepts it and refuses everything else, and the first page of one post type.

The examples use `https://example.test` for the site and `KEY` for the token. Replace both with your own values, and keep the token out of tickets, chat logs and your shell history.

1. **Activate the plugin.** On a multisite network, network-activate it. It needs PHP 8.2 or later and WordPress 6.8 or later; on an older PHP it shows an admin notice and stays inactive.
2. **Issue a token.** Open **Settings, Migration API token** (in the network admin on a multisite) and press **Generate token**. The token is shown once, on that page. Copy it now: only a hash of it is stored. [Giving a consumer access](Giving-a-consumer-access.md) covers the page in full.
3. **Verify it with three requests.**

   ```bash
   SITE=https://example.test
   KEY=...

   curl -s -o /dev/null -w 'correct key : %{http_code}\n' -H "X-UAMSWP-Migration-Key: $KEY"        "$SITE/wp-json/uamswp-migration/v1/"
   curl -s -o /dev/null -w 'no header   : %{http_code}\n'                                          "$SITE/wp-json/uamswp-migration/v1/"
   curl -s -o /dev/null -w 'wrong key   : %{http_code}\n' -H "X-UAMSWP-Migration-Key: definitely-wrong" "$SITE/wp-json/uamswp-migration/v1/"
   ```

   Expect `200`, `401`, `401`. The third request is the one that proves the right key is required, not just any key.
4. **Read what this deployment serves.**

   ```bash
   curl -s -H "X-UAMSWP-Migration-Key: $KEY" "$SITE/wp-json/uamswp-migration/v1/"
   ```

   The response names the plugin, the site that answered, the post types and taxonomies it exposes, the pagination defaults, and each route family with an `available` flag for the form and redirect plugins.
5. **Read the first page of posts.**

   ```bash
   curl -s -D - -H "X-UAMSWP-Migration-Key: $KEY" "$SITE/wp-json/uamswp-migration/v1/posts/post?per_page=10"
   ```

   The headers carry `X-WP-Total`, `X-WP-TotalPages` and `X-UAMSWP-Site`; the body is an array of post records, lowest ID first.

**Note:** if `curl` fails on certificate verification, do not add `-k`. The first request carries the key, so `--insecure` hands it to whatever answers. Fix the trust store instead.

Next:

- [Reading a live site in pages](Reading-a-live-site-in-pages.md) to read a whole post type and to re-pull only what changed.
- [Reading another site on a network](Reading-another-site-on-a-network.md) to read a site other than the one handling the request.
- [Endpoints](Endpoints.md) for every route.
