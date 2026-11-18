Written against `main` after v0.8.9.

Every route needs one of three credentials: a token issued from this plugin's own admin page, a WordPress user holding the capability on the site being read, or a shared secret in `wp-config.php`. There is no anonymous path: the API serves unpublished content and raw site settings.

**Use the token.** It is what the migration deploys, and it is the only one of the three that is scoped to a single network, revocable on its own, and carries no WordPress identity at all. The shared secret is one value across every network with no per-consumer revocation; an application password would grant `manage_network_options`, which is write-capable network administration, in order to perform a read.

**Read this part before anything else: every failed key returns an identical `401`.** An unset constant, a mismatched value, a typo, and a correctly refused anonymous request are indistinguishable from the outside. That is deliberate (the API declines to tell an unauthenticated caller which of those it is), but it means a misconfigured install looks exactly like a working one that you forgot to send a key to, and you cannot debug it by reading the response. The one different answer is a signed-in WordPress user who lacks the capability, who gets `403` `uamswp_migration_api_forbidden`.

## Issue a token from the admin page

**Settings, Migration API token**: in the network admin on a multisite (`/wp-admin/network/settings.php?page=uamswp-migration-api-token`) and under Settings on a single-site install. It needs `manage_network_options` on a multisite and `manage_options` otherwise. The page reads the same capability the API does, so the two cannot drift.

Press **Generate token**. The token is displayed once, on that page, in the response to the press. It is not stored and not recoverable: only a SHA-256 hash of it is written. If you lose it, generate another.

Paste it into the consumer's configuration for that source site. It travels in the `X-UAMSWP-Migration-Key` header, the same header as the shared secret below, so a consumer already configured for one needs no change to accept the other.

**Regenerating keeps the old token working for an hour.** That window exists so a rotation is not a synchronized edit: press regenerate, then update the consumer at any point before it closes, with no refused request in between. The page shows the previous token's identifier and when it stops being accepted. Filter `uamswp_migration_api_token_rotation_window` to change the duration in seconds; zero means no overlap.

The page shows an identifier for the current token, not the token itself, so that when several networks are in play you can tell which credential a consumer holds without having the credential.

## The shared secret, for the path that predates the token

Set the constant in `wp-config.php`, **above** the `/* That's all, stop editing! */` line:

```php
define( 'UAMSWP_MIGRATION_API_SECRET', '...' );
```

Generate it with something that is not a word, and never let it reach a repository, a ticket, a chat log or your shell history:

```bash
openssl rand -hex 32
```

The consumer presents it as the `X-UAMSWP-Migration-Key` header. Until the constant is defined as a non-empty string, the secret path is inert.

## Verify with three requests, not one

Whichever credential you set, verify it the same way. `KEY` below is the token or the shared secret.

```bash
SITE=https://example.test
KEY=...

curl -s -o /dev/null -w 'correct key : %{http_code}\n' -H "X-UAMSWP-Migration-Key: $KEY"        "$SITE/wp-json/uamswp-migration/v1/"
curl -s -o /dev/null -w 'no header   : %{http_code}\n'                                          "$SITE/wp-json/uamswp-migration/v1/"
curl -s -o /dev/null -w 'wrong key   : %{http_code}\n' -H "X-UAMSWP-Migration-Key: definitely-wrong" "$SITE/wp-json/uamswp-migration/v1/"
```

Expect `200`, `401`, `401`.

**If `curl` fails on certificate verification, do not reach for `-k`.** It is `--insecure`, and the first of those three requests carries the key, so an operator who points these at a real host and keeps the flag hands the credential to whatever answers. The commands above deliberately carry no such flag: they work against a local `.test` host because its certificate is trusted in the system keychain, which is the same reason a TLS failure elsewhere is telling you something real. Fix the trust store, or use a host whose certificate validates.

**The third request is the load-bearing one.** A guard that accepted any non-empty header would pass the first two and look perfect; the wrong-key check is the only one that distinguishes *"a key was accepted"* from *"the right key was required"*. [`tests/Integration/Feature/SharedSecretTest.php`](https://github.com/UAMS-Web/aether-wordpress-migration-api/blob/main/tests/Integration/Feature/SharedSecretTest.php) asserts the same thing (`refuses a wrong, empty or absent secret even when one is configured`), so the behavior is covered in CI as well as verifiable by hand.

## What answering the authentication filter means for a site with its own gate

The plugin reports `true` to `rest_authentication_errors` for a request that presents a recognized credential on a route in this namespace ([`uamswp-migration-api-archive#290`](https://github.com/UAMS-Web/uamswp-migration-api-archive/issues/290)). **That is a report, not an authorization**: the namespace gate still decides, on the site actually being read, and no WordPress user is signed in by it, so `is_user_logged_in()` stays false for the whole request.

**Why it has to say anything at all.** That filter's contract has three values, and silence is not neutral. Returning nothing leaves `null`, which means *no authentication method was attempted*. That is untrue of a request carrying `X-UAMSWP-Migration-Key`, and a plugin that front-gates the REST API reads `null` and refuses before routing. Measured on a network site that front-gates its REST API with a force-login plugin: a valid network token was refused there while the same token read four other sites on the same network, because that plugin answered first and this plugin had told WordPress nothing.

**The consequence to weigh before deploying to a gated site.** A site whose REST API is closed to the public now has a second credential that gets past that gate, for this namespace only, and only with a valid token or secret. That is the point of the change rather than a side effect, but it is worth stating plainly:

```
before   a front-gated site refused the token; the API was unreachable there
after    a valid token reaches this namespace's routes on that site
          -- every other route on the site is refused exactly as before
          -- an invalid or absent credential is refused exactly as before
          -- another gate's decision, either way, is never overruled
```

**If that is not wanted on a given site, the lever is the credential, not the filter.** Rotate or stop issuing the network token and the path closes with it; there is no configuration in this plugin that makes it answer `null` while still accepting the credential elsewhere.

## Two things worth knowing before you set the secret

- **Defining it does not affect the test suite.** `tests/Integration/bootstrap.php` reads the live `wp-config.php` for exactly one value, `DB_NAME`, to refuse a run that would drop the development database, and never loads it as PHP.
- **`composer test` runs a separate process specifically because the constant is undefined there.** The branch where no secret is configured must authorize nothing, however plausible the presented header, and it is only reachable while the constant stays unset. See [`AGENTS.md`](https://github.com/UAMS-Web/aether-wordpress-migration-api/blob/main/AGENTS.md) and [`docs/DECISIONS.md`](https://github.com/UAMS-Web/aether-wordpress-migration-api/blob/main/docs/DECISIONS.md) D-009.

## Reference

- [Endpoints](Endpoints.md): the routes these credentials open.
- [Errors](Errors.md): `uamswp_migration_api_unauthorized` and `uamswp_migration_api_forbidden`.
- [Configuration and hooks](Configuration-and-hooks.md): `UAMSWP_MIGRATION_API_SECRET`, `uamswp_migration_api_capability` and `uamswp_migration_api_token_rotation_window`.
