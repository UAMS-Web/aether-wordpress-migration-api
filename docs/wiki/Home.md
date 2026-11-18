The Aether WordPress Migration API is a read-only WordPress plugin that serves raw, current content to the Statamic importer, in place of hand-made WordPress XML (WXR) exports and plugin export files. It runs on a multisite network or a single-site install, and it is deleted when the migration completes.

This wiki holds the documentation for people who install it and for the consumers that read from it: a first read, task guides, the reference and troubleshooting. The [README](https://github.com/UAMS-Web/aether-wordpress-migration-api#readme) keeps the overview, why the plugin exists, and how to run its tests and CI.

## Getting started

- [Installation (README)](https://github.com/UAMS-Web/aether-wordpress-migration-api#readme): what the plugin is and what it needs.
- [Your first read](Your-first-read.md): issue a token, verify it, and page through one post type.

## Guides

- [Giving a consumer access](Giving-a-consumer-access.md): the three credentials, issuing and rotating a token, and the three-request check.
- [Reading a live site in pages](Reading-a-live-site-in-pages.md): pagination, ordering and incremental re-pulls with `modified_after`.
- [Reading another site on a network](Reading-another-site-on-a-network.md): the `site` parameter and the `X-UAMSWP-Site` header.

## Troubleshooting

- [Troubleshooting](Troubleshooting.md): one entry per symptom, with its cause and fix.

## Reference

- [Endpoints](Endpoints.md): every route, its parameters and what it returns.
- [Response shapes](Response-shapes.md): the fields of each record a route returns.
- [Errors](Errors.md): every error code, its status and when it is returned.
- [Configuration and hooks](Configuration-and-hooks.md): the configuration file, the constant and the filters.

## Project

- [Project](Project.md): where the plugin is developed and how to report a problem.
