#!/bin/sh
set -eu

project_name=${1:?Run with CMS_TEST_WORDPRESS_IMPORT=1 ./scripts/test-e2e.sh}
test "$project_name" = morfira-e2e
fixture=back/tests/Content/Fixtures/wordpress-import.xml
container_fixture=/tmp/morfira-wordpress-import.xml
uploads=/tmp/morfira-wordpress-uploads

compose() {
    docker compose -p "$project_name" -f compose.prod.yaml -f compose.e2e.yaml "$@"
}

import() {
    import_source=$1
    shift
    compose exec -T app php bin/cms cms:import:wordpress "$container_fixture" --source="$import_source" --media-dir="$uploads" "$@"
}

db_query() {
    compose exec -T database sh -c 'MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb --batch --skip-column-names -u "$MARIADB_USER" "$MARIADB_DATABASE"' <<SQL
$1
SQL
}

page_query="SELECT COUNT(*) FROM content_pages WHERE slug IN ('wp-import-about', 'wp-import-edited-about')"
schema_query="SELECT COUNT(*) FROM schema_content_types WHERE api_name = 'wordpress-posts'"
entry_query="SELECT COUNT(*) FROM content_entries WHERE slug IN ('wp-import-news', 'wp-import-edited-news')"
ledger_query="SELECT COUNT(*) FROM content_wordpress_import_items WHERE source_key = 'wordpress-e2e'"
media_query="SELECT COUNT(*) FROM media_assets WHERE original_name IN ('sample.png', 'sample-two.png')"
taxonomy_query="SELECT COUNT(*) FROM taxonomy_vocabularies WHERE slug IN ('wordpress-categories', 'wordpress-tags')"
author_query="SELECT COUNT(*) FROM content_wordpress_import_authors WHERE source_key = 'wordpress-e2e'"
redirect_query="SELECT COUNT(*) FROM published_import_redirects"
identity_before=$(db_query "SELECT COUNT(*) FROM identity_users")

compose cp "$fixture" "app:$container_fixture"
compose exec -T app php -r '
    mkdir($argv[1]."/2026/11", 0700, true);
    $image = imagecreatetruecolor(64, 48);
    if (!imagepng($image, $argv[1]."/2026/11/sample.png") || !imagepng($image, $argv[1]."/2026/11/sample-two.png")) exit(1);
' "$uploads"
media_before=$(compose exec -T app sh -c 'find public/media -type f | wc -l')
report=$(import wordpress-e2e)
printf '%s\n' "$report" | jq -e '
    (.items | length) == 2 and
    ([.items[] | select(.state == "planned") | .targetPath] | sort) == ["/content/wordpress-posts/wp-import-news", "/wp-import-about"] and
    ([.media[] | .state] | sort) == ["invalid", "planned", "planned"] and
    (.items[0].images == {"inline": 1, "missingInline": 1, "featured": "linked"}) and
    (.items[1].images == {"inline": 1, "missingInline": 0, "featured": "linked"}) and
    ([.items[].redirect] == ["planned", "planned"]) and .items[1].author == "linked" and
    (.authors == [{"state": "planned"}]) and
    ([.taxonomy.terms[] | select(.state == "planned")] | length) == 3 and
    ([.taxonomy.assignments[] | select(.state == "planned")] | length) == 2
' >/dev/null
test "$(db_query "$page_query")" = 0
test "$(db_query "$schema_query")" = 0
test "$(db_query "$entry_query")" = 0
test "$(db_query "$ledger_query")" = 0
test "$(db_query "$media_query")" = 0
test "$(db_query "$taxonomy_query")" = 0
test "$(db_query "$author_query")" = 0
test "$(db_query "$redirect_query")" = 0
test "$(compose exec -T app sh -c 'find public/media -type f | wc -l')" = "$media_before"

report=$(import wordpress-e2e --apply)
printf '%s\n' "$report" | jq -e '
    (.items | length) == 2 and
    ([.items[] | select(.state == "imported") | .type] | sort) == ["page", "post"] and
    ([.media[] | .state] | sort) == ["imported", "imported", "invalid"] and
    ([.items[].redirect] == ["registered", "registered"]) and .authors[0].state == "imported"
' >/dev/null
test "$(db_query "$page_query")" = 1
test "$(db_query "$schema_query")" = 1
test "$(db_query "$entry_query")" = 1
test "$(db_query "$ledger_query")" = 4
test "$(db_query "$media_query")" = 2
test "$(db_query "$taxonomy_query")" = 2
test "$(db_query "$author_query")" = 1
test "$(db_query "$redirect_query")" = 2
test "$(db_query "SELECT COUNT(*) FROM identity_users")" = "$identity_before"
test "$(db_query "SELECT COUNT(*) FROM content_entries AS post
    JOIN content_entries AS author ON author.id = JSON_UNQUOTE(JSON_EXTRACT(post.values_json, '\$.wordpressAuthor'))
    JOIN schema_content_types AS schema_type ON schema_type.id = author.content_type_id
    WHERE post.slug = 'wp-import-news' AND schema_type.api_name = 'wordpress-authors'
    AND author.title = 'Migration Writer'
    AND JSON_UNQUOTE(JSON_EXTRACT(post.values_json, '\$.wordpressAuthorName')) = 'Migration Writer'")" = 1
test "$(db_query "SELECT COUNT(*) FROM media_assets WHERE original_name = 'sample.png' AND default_alt = 'Sample alt'")" = 1
test "$(db_query "SELECT COUNT(*) FROM content_pages AS page
    JOIN media_assets AS inline_asset ON inline_asset.id = JSON_UNQUOTE(JSON_EXTRACT(page.blocks, '\$[1].data.assetId'))
    JOIN media_assets AS featured ON featured.id = JSON_UNQUOTE(JSON_EXTRACT(page.seo, '\$.openGraphImageAssetId'))
    WHERE page.slug = 'wp-import-about' AND inline_asset.original_name = 'sample.png' AND featured.original_name = 'sample-two.png'
    AND JSON_UNQUOTE(JSON_EXTRACT(page.blocks, '\$[1].data.alt')) = 'Page image'
    AND JSON_UNQUOTE(JSON_EXTRACT(page.blocks, '\$[2].data.content')) LIKE '%Page caption%Page tail%'")" = 1
test "$(db_query "SELECT COUNT(*) FROM content_entries AS entry
    JOIN media_assets AS inline_asset ON inline_asset.id = JSON_UNQUOTE(JSON_EXTRACT(entry.values_json, '\$.wordpressInlineImage1'))
    JOIN media_assets AS featured ON featured.id = JSON_UNQUOTE(JSON_EXTRACT(entry.values_json, '\$.wordpressFeaturedImage'))
    WHERE entry.slug = 'wp-import-news' AND inline_asset.original_name = 'sample-two.png' AND featured.original_name = 'sample.png'
    AND JSON_UNQUOTE(JSON_EXTRACT(entry.values_json, '\$.wordpressBodyPart2')) LIKE '%Post tail%'")" = 1
test "$(db_query "SELECT COUNT(*) FROM schema_content_types WHERE api_name = 'wordpress-posts'
    AND JSON_UNQUOTE(JSON_EXTRACT(fields, '\$[0].key')) = 'wordpressFeaturedImage'
    AND JSON_UNQUOTE(JSON_EXTRACT(fields, '\$[1].key')) = 'body'
    AND JSON_UNQUOTE(JSON_EXTRACT(fields, '\$[2].key')) = 'wordpressInlineImage1'
    AND JSON_UNQUOTE(JSON_EXTRACT(fields, '\$[3].key')) = 'wordpressBodyPart2'")" = 1
test "$(db_query "SELECT COUNT(*) FROM taxonomy_terms AS child JOIN taxonomy_terms AS parent ON child.parent_id = parent.id WHERE child.slug = 'updates' AND parent.slug = 'news'")" = 1
test "$(db_query "SELECT COUNT(*) FROM taxonomy_entry_terms AS assignment JOIN content_entries AS entry ON assignment.entry_id = entry.id JOIN taxonomy_terms AS term ON assignment.term_id = term.id WHERE entry.slug = 'wp-import-news' AND term.slug IN ('updates', 'release')")" = 2
media_after=$(compose exec -T app sh -c 'find public/media -type f | wc -l')
test "$media_after" -gt "$media_before"
test "$(db_query "SELECT COUNT(*) FROM content_pages WHERE slug = 'wp-import-about' AND title = 'Imported About' AND status = 'draft' AND blocks LIKE '%Page body%' AND blocks NOT LIKE '%<script%'")" = 1
test "$(db_query "SELECT COUNT(*) FROM content_entries AS entry JOIN schema_content_types AS schema_type ON schema_type.id = entry.content_type_id WHERE entry.slug = 'wp-import-news' AND entry.title = 'Imported News' AND schema_type.api_name = 'wordpress-posts' AND JSON_UNQUOTE(JSON_EXTRACT(entry.values_json, '$.body')) LIKE '%Post body%' AND entry.values_json NOT LIKE '%<script%'")" = 1

report=$(import wordpress-e2e-collision)
printf '%s\n' "$report" | jq -e '
    (.items | length) == 2 and all(.items[]; .state == "invalid" and .reason == "Draft slug already exists.")
' >/dev/null
test "$(db_query "SELECT COUNT(*) FROM content_wordpress_import_items WHERE source_key = 'wordpress-e2e-collision'")" = 0

compose exec -T app php -r 'if (file_put_contents($argv[1], stream_get_contents(STDIN)) === false) exit(1);' "$container_fixture" <<XML
$(sed -e 's/wp-import-about/wp-import-edited-about/g' -e 's/wp-import-news/wp-import-edited-news/g' -e 's/Imported About/Edited About/g' -e 's/Imported News/Edited News/g' "$fixture")
XML
report=$(import wordpress-e2e --apply)
printf '%s\n' "$report" | jq -e '
    (.items | length) == 2 and
    ([.items[] | select(.state == "already_imported") | .type] | sort) == ["page", "post"]
' >/dev/null
test "$(db_query "$page_query")" = 1
test "$(db_query "$schema_query")" = 1
test "$(db_query "$entry_query")" = 1
test "$(db_query "$ledger_query")" = 4
test "$(db_query "$media_query")" = 2
test "$(db_query "$author_query")" = 1
test "$(db_query "$redirect_query")" = 2
test "$(db_query "SELECT COUNT(*) FROM content_page_revisions AS revision JOIN content_pages AS page ON page.id = revision.page_id WHERE page.slug = 'wp-import-about' AND page.title = 'Imported About'")" = 2
test "$(db_query "SELECT COUNT(*) FROM content_entry_revisions AS revision JOIN content_entries AS entry ON entry.id = revision.entry_id WHERE entry.slug = 'wp-import-news' AND entry.title = 'Imported News'")" = 1

report=$(import wordpress-e2e-rollback)
printf '%s\n' "$report" | jq -e '
    (.items | length) == 2 and all(.items[]; .state == "planned")
' >/dev/null
db_query "DELIMITER //
CREATE TRIGGER wordpress_e2e_reject_entry BEFORE INSERT ON content_entries FOR EACH ROW
BEGIN
    IF NEW.slug = 'wp-import-edited-news' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic import write failure';
    END IF;
END//
DELIMITER ;"
if import wordpress-e2e-rollback --apply >/dev/null 2>&1; then
    printf '%s\n' 'WordPress import unexpectedly succeeded after an injected database write failure.' >&2
    exit 1
fi
test "$(db_query "$page_query")" = 1
test "$(db_query "$entry_query")" = 1
test "$(db_query "SELECT COUNT(*) FROM content_wordpress_import_items WHERE source_key = 'wordpress-e2e-rollback'")" = 0

compose cp "$fixture" "app:$container_fixture"
db_query "DELIMITER //
CREATE TRIGGER wordpress_e2e_reject_media BEFORE INSERT ON media_assets FOR EACH ROW
BEGIN
    IF NEW.original_name = 'sample-two.png' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic media import write failure';
    END IF;
END//
DELIMITER ;"
if import wordpress-e2e-media-rollback --apply >/dev/null 2>&1; then
    printf '%s\n' 'WordPress import unexpectedly succeeded after an injected media write failure.' >&2
    exit 1
fi
test "$(db_query "$media_query")" = 2
test "$(db_query "SELECT COUNT(*) FROM content_wordpress_import_items WHERE source_key = 'wordpress-e2e-media-rollback'")" = 0
test "$(compose exec -T app sh -c 'find public/media -type f | wc -l')" = "$media_after"

for route in /wp-import-about /api/v1/content/pages/wp-import-about /content/wordpress-posts/wp-import-news /api/v1/content/entries/wordpress-posts/wp-import-news; do
    test "$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' "$CMS_PUBLIC_SITE_URL$route")" = 404
done
for route in '/?page_id=451' /2026/11/wp-import-news/; do
    test "$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' "$CMS_PUBLIC_SITE_URL$route")" = 404
done

session_cookie=$(mktemp "${TMPDIR:-/tmp}/morfira-import-e2e.XXXXXX")
trap 'rm -f -- "$session_cookie"' EXIT
request() {
    curl --fail --silent --show-error -b "$session_cookie" -c "$session_cookie" "$@"
}
csrf=$(request "$CMS_PUBLIC_SITE_URL/api/v1/auth/csrf" | jq -er .token)
request -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' \
    --data '{"email":"owner@morfira.test","password":"Morfira-beta-2026!"}' "$CMS_PUBLIC_SITE_URL/api/v1/auth/login" >/dev/null
csrf=$(request "$CMS_PUBLIC_SITE_URL/api/v1/auth/csrf" | jq -er .token)
page_id=$(db_query "SELECT id FROM content_pages WHERE slug = 'wp-import-about'")
post_id=$(db_query "SELECT id FROM content_entries WHERE slug = 'wp-import-news'")
request -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' --data '{"version":2}' \
    "$CMS_PUBLIC_SITE_URL/api/v1/admin/pages/$page_id/publish" >/dev/null
request -H "X-CSRF-Token: $csrf" -H 'Content-Type: application/json' --data '{"version":1}' \
    "$CMS_PUBLIC_SITE_URL/api/v1/admin/entries/$post_id/publish" >/dev/null
redirect() {
    curl --silent --show-error --output /dev/null --write-out '%{http_code} %{redirect_url}' "$CMS_PUBLIC_SITE_URL$1"
}
test "$(redirect '/?page_id=451')" = "301 $CMS_PUBLIC_SITE_URL/wp-import-about"
test "$(redirect /2026/11/wp-import-news/)" = "301 $CMS_PUBLIC_SITE_URL/content/wordpress-posts/wp-import-news"
curl --fail --silent --show-error "$CMS_PUBLIC_SITE_URL/api/v1/content/entries/wordpress-posts/wp-import-news" | jq -e \
    '.values.wordpressAuthorName == "Migration Writer" and (.values.wordpressAuthor | type) == "string"' >/dev/null
request -X DELETE -H "X-CSRF-Token: $csrf" "$CMS_PUBLIC_SITE_URL/api/v1/admin/pages/$page_id/publish" >/dev/null
request -X DELETE -H "X-CSRF-Token: $csrf" "$CMS_PUBLIC_SITE_URL/api/v1/admin/entries/$post_id/publish" >/dev/null
test "$(redirect '/?page_id=451')" = '404 '
test "$(redirect /2026/11/wp-import-news/)" = '404 '

compose exec -T app php -r 'if (file_put_contents($argv[1], stream_get_contents(STDIN)) === false) exit(1);' "$container_fixture" <<'XML'
<rss xmlns:wp="http://wordpress.org/export/1.2/" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel><wp:wxr_version>1.2</wp:wxr_version><item>
    <title>Reused asset</title><wp:post_id>456</wp:post_id><wp:post_name>wp-import-reused-asset</wp:post_name>
    <wp:post_type>post</wp:post_type><wp:status>draft</wp:status>
    <wp:postmeta><wp:meta_key>_thumbnail_id</wp:meta_key><wp:meta_value>453</wp:meta_value></wp:postmeta>
    <content:encoded><![CDATA[<p>Intro</p><img src="https://wordpress.example/wp-content/uploads/2026/11/sample-two.png" class="wp-image-454"><p>Tail</p>]]></content:encoded>
  </item></channel>
</rss>
XML
report=$(import wordpress-e2e --apply)
printf '%s\n' "$report" | jq -e '
    (.media | length) == 0 and .items[0].state == "imported" and
    .items[0].images == {"inline": 1, "missingInline": 0, "featured": "linked"}
' >/dev/null
test "$(db_query "$media_query")" = 2
test "$(compose exec -T app sh -c 'find public/media -type f | wc -l')" = "$media_after"

rest_import() {
    compose exec -T -e "CMS_REST_FIXTURE_EDITED=${CMS_REST_FIXTURE_EDITED:-0}" -e "CMS_REST_FIXTURE_MEDIA=${CMS_REST_FIXTURE_MEDIA:-}" \
        app php tests/Content/Fixtures/wordpress-rest-import.php \
        --api-url=https://wordpress.example/wp-json/wp/v2 --source=wordpress-rest-e2e "$@"
}
rest_post_query="SELECT COUNT(*) FROM content_entries WHERE slug = 'imported-rest-post'"
rest_ledger_query="SELECT COUNT(*) FROM content_wordpress_import_items WHERE source_key = 'wordpress-rest-e2e'"
rest_author_query="SELECT COUNT(*) FROM content_wordpress_import_authors WHERE source_key = 'wordpress-rest-e2e'"
rest_media_query="SELECT COUNT(*) FROM media_assets WHERE original_name = 'rest-fixture.png'"

if failure=$(rest_import '--media-host=*.wordpress.example' 2>&1); then
    printf '%s\n' 'REST import unexpectedly accepted a wildcard media hostname.' >&2
    exit 1
fi
printf '%s\n' "$failure" | grep -F 'WordPress media hosts must be exact public DNS hostnames.' >/dev/null
if failure=$(rest_import --media-host=cdn.wordpress.example --media-dir="$uploads" 2>&1); then
    printf '%s\n' 'REST import unexpectedly combined a media hostname and local uploads.' >&2
    exit 1
fi
printf '%s\n' "$failure" | grep -F -- '--media-host requires --api-url without --media-dir.' >/dev/null

if failure=$(CMS_REST_FIXTURE_MEDIA=transient-failure rest_import --apply 2>&1); then
    printf '%s\n' 'REST import unexpectedly succeeded after an injected media download failure.' >&2
    exit 1
fi
printf '%s\n' "$failure" | grep -F 'Fixture media download failed. Apply was rolled back.' >/dev/null
test "$(db_query "$rest_ledger_query")" = 0
test "$(db_query "$rest_post_query")" = 0
test "$(db_query "$rest_media_query")" = 0
test "$(compose exec -T app sh -c 'find public/media -type f | wc -l')" = "$media_after"

report=$(CMS_REST_FIXTURE_MEDIA=reject-download rest_import --media-host=other.wordpress.example)
printf '%s\n' "$report" | jq -e '
    .input == "rest" and .mode == "dry-run" and .items[0].state == "planned" and
    .items[0].images == {"inline": 0, "missingInline": 1, "featured": "missing"} and
    (.media | length) == 1 and .media[0].state == "invalid"
' >/dev/null

rest_import_with_cdn() {
    rest_import --media-host=cdn.wordpress.example --media-host=other.wordpress.example "$@"
}
report=$(CMS_REST_FIXTURE_MEDIA=reject-download rest_import_with_cdn)
printf '%s\n' "$report" | jq -e '
    .input == "rest" and .mode == "dry-run" and .items[0].state == "planned" and
    .items[0].author == "linked" and .items[0].redirect == "planned" and
    .items[0].images == {"inline": 1, "missingInline": 0, "featured": "linked"} and
    (.media | length) == 1 and .media[0].state == "planned"
' >/dev/null
test "$(db_query "$rest_post_query")" = 0
test "$(db_query "$rest_ledger_query")" = 0
test "$(db_query "$rest_author_query")" = 0
test "$(db_query "$rest_media_query")" = 0
test "$(compose exec -T app sh -c 'find public/media -type f | wc -l')" = "$media_after"

report=$(rest_import_with_cdn --apply)
printf '%s\n' "$report" | jq -e '
    .items[0].state == "imported" and .authors[0].state == "imported" and
    .items[0].images == {"inline": 1, "missingInline": 0, "featured": "linked"} and
    .media[0].state == "imported" and (.media[0].assetId | type) == "string"
' >/dev/null
rest_asset_id=$(printf '%s\n' "$report" | jq -er .media[0].assetId)
test "$(db_query "$rest_post_query")" = 1
test "$(db_query "$rest_ledger_query")" = 2
test "$(db_query "$rest_author_query")" = 1
test "$(db_query "$rest_media_query")" = 1
test "$(db_query "SELECT COUNT(*) FROM media_assets WHERE id = '$rest_asset_id' AND mime_type = 'image/webp' AND default_alt = 'REST fixture image'")" = 1
test "$(db_query "SELECT COUNT(*) FROM content_entries WHERE slug = 'imported-rest-post'
    AND JSON_UNQUOTE(JSON_EXTRACT(values_json, '\$.wordpressInlineImage1')) = '$rest_asset_id'
    AND JSON_UNQUOTE(JSON_EXTRACT(values_json, '\$.wordpressFeaturedImage')) = '$rest_asset_id'")" = 1
rest_media_after=$(compose exec -T app sh -c 'find public/media -type f | wc -l')
test "$rest_media_after" -gt "$media_after"

report=$(CMS_REST_FIXTURE_EDITED=1 CMS_REST_FIXTURE_MEDIA=reject-download rest_import_with_cdn --apply)
printf '%s\n' "$report" | jq -e --arg asset "$rest_asset_id" '
    .items[0].state == "already_imported" and .authors[0].state == "already_imported" and
    .media[0].state == "already_imported" and .media[0].assetId == $asset
' >/dev/null
test "$(db_query "$rest_post_query")" = 1
test "$(db_query "SELECT title FROM content_entries WHERE slug = 'imported-rest-post'")" = 'Imported REST post'
test "$(db_query "$rest_ledger_query")" = 2
test "$(db_query "$rest_author_query")" = 1
test "$(db_query "$rest_media_query")" = 1
test "$(compose exec -T app sh -c 'find public/media -type f | wc -l')" = "$rest_media_after"
test "$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' "$CMS_PUBLIC_SITE_URL/content/wordpress-posts/imported-rest-post")" = 404

printf '%s\n' 'WordPress import production CLI journey passed.'
