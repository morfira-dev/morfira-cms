# WordPress migration rehearsal fixture

From the repository root, provision an isolated WordPress source:

```sh
sh scripts/fixtures/wordpress-rehearsal/provision.sh
```

The fixture serves `http://localhost:18120` on loopback and writes the following files to `/tmp/morfira-wordpress-rehearsal`:

- `wordpress-export.xml` is the native WordPress WXR export, including stock WordPress content.
- `uploads/` contains the WordPress uploads tree. `2024/03/rehearsal-landscape.png` is the generated original.
- `rest/` contains the live public pages, posts, media, categories, tags, and users collections.
- `inventory.json` records the installed version and the seeded item IDs.
- `compose.env` contains generated database settings for the isolated fixture. Keep it local.

The fixture includes two published pages with cross-links, a published Gutenberg post with a category and tag, an unpublished post, and an authored image with alt text, a caption, and featured-image references on a page and a post. The public REST API omits the unpublished content that the WXR export includes.

The services use memory-backed storage. A stop discards this fixture's active installation. The exported files remain available. To stop the services:

```sh
docker compose --env-file /tmp/morfira-wordpress-rehearsal/compose.env \
  -f scripts/fixtures/wordpress-rehearsal/compose.yaml stop
```

Set `REHEARSAL_OUTPUT_DIR`, `REHEARSAL_WORDPRESS_PORT`, or `REHEARSAL_WORDPRESS_SUBNET` to select a different output directory, port, or bridge subnet. Set a unique `COMPOSE_PROJECT_NAME` when running a second fixture concurrently.

The [official WordPress image](https://hub.docker.com/_/wordpress) is pinned to the [7.1.5 security release](https://wordpress.org/news/2026/11/wordpress-7-1-5-release/). [WordPress](https://wordpress.org/about/license/) is GPL-2.0-or-later and [MariaDB Server](https://mariadb.com/products/community-server/) is GPL-2.0. These are separately run rehearsal services. The Morfira runtime does not pull, install, or distribute either fixture image. The original fixture content and provisioning script live here under the repository license.

Morfira's REST importer requires an eligible public HTTPS origin. Use the loopback WordPress URL to inspect the local source and collect REST evidence. The WXR and uploads can be imported without a network source.
