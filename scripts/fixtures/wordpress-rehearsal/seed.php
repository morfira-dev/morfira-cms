<?php

declare(strict_types=1);

namespace Morfira\Fixture\WordPress;

use RuntimeException;

if (PHP_SAPI !== 'cli' || !in_array($argv[1] ?? '', ['seed', 'export'], true)) {
    exit(1);
}

$site = getenv('REHEARSAL_WORDPRESS_URL');
if ($site === false || preg_match('~\Ahttp://localhost:[0-9]+\z~', $site) !== 1) {
    throw new RuntimeException('REHEARSAL_WORDPRESS_URL must be a localhost HTTP URL with a port.');
}

$_SERVER['HTTP_HOST'] = substr($site, strlen('http://'));
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['SERVER_PORT'] = parse_url($site, PHP_URL_PORT);
define('WP_INSTALLING', $argv[1] === 'seed');
require_once '/var/www/html/wp-load.php';

if ($argv[1] === 'export') {
    require_once ABSPATH . 'wp-admin/includes/export.php';
    export_wp(['content' => 'all']);
    exit;
}

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

function checked(mixed $value): mixed
{
    if (is_wp_error($value)) {
        throw new RuntimeException($value->get_error_message());
    }

    return $value;
}

if (!is_blog_installed()) {
    add_filter('pre_wp_mail', '__return_true');
    wp_install('Morfira migration rehearsal', 'rehearsal-owner', 'owner@example.test', false, '', wp_generate_password(32));
}

wp_installing(false);
update_option('siteurl', $site);
update_option('home', $site);
update_option('permalink_structure', '/%year%/%monthnum%/%postname%/');
flush_rewrite_rules(false);

$author = username_exists('rehearsal-author');
if (!$author) {
    $author = checked(wp_insert_user([
        'user_login' => 'rehearsal-author',
        'user_pass' => wp_generate_password(32),
        'user_email' => 'author@example.test',
        'display_name' => 'Rehearsal Author',
        'role' => 'author',
    ]));
}

function sourcePost(string $slug, string $type, array $fields): int
{
    $existing = get_page_by_path($slug, OBJECT, [$type]);
    if ($existing !== null) {
        $fields['ID'] = $existing->ID;
    }

    return checked(wp_insert_post(wp_slash($fields + ['post_name' => $slug, 'post_type' => $type]), true));
}

function sourceTerm(string $slug, string $name, string $taxonomy): int
{
    $existing = get_term_by('slug', $slug, $taxonomy);
    if ($existing !== false) {
        return $existing->term_id;
    }

    return checked(wp_insert_term($name, $taxonomy, ['slug' => $slug]))['term_id'];
}

$date = '2024-03-15 12:00:00';
$published = ['post_status' => 'publish', 'post_author' => $author, 'post_date' => $date];
$page = sourcePost('rehearsal-about', 'page', $published + [
    'post_title' => 'Rehearsal About',
    'post_content' => '<!-- wp:paragraph --><p>A source page links to the migration guide.</p><!-- /wp:paragraph -->',
]);
$guide = sourcePost('rehearsal-guide', 'page', $published + [
    'post_title' => 'Rehearsal Guide',
    'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading" id="read-more">Read more</h2><!-- /wp:heading -->'
        . '<!-- wp:paragraph --><p>Return to the <a href="' . esc_url(get_permalink($page)) . '">About page</a>.</p><!-- /wp:paragraph -->',
]);

$attachments = get_posts([
    'name' => 'rehearsal-landscape',
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'numberposts' => 1,
]);
$attachment = $attachments[0] ?? null;
if ($attachment === null) {
    $image = imagecreatetruecolor(640, 360);
    imagefill($image, 0, 0, imagecolorallocate($image, 35, 88, 109));
    imagefilledellipse($image, 375, 145, 118, 118, imagecolorallocate($image, 244, 188, 87));
    imagefilledpolygon($image, [0, 325, 222, 157, 403, 325], imagecolorallocate($image, 103, 153, 118));
    ob_start();
    imagepng($image);
    $pixels = ob_get_clean();
    $upload = wp_upload_bits('rehearsal-landscape.png', null, $pixels, '2024/03');
    if ($upload['error'] !== false) {
        throw new RuntimeException($upload['error']);
    }

    $media = checked(wp_insert_attachment([
        'post_title' => 'Rehearsal Landscape',
        'post_name' => 'rehearsal-landscape',
        'post_mime_type' => 'image/png',
        'post_status' => 'inherit',
        'post_author' => $author,
        'post_date' => $date,
    ], $upload['file'], $page, true));
    wp_update_attachment_metadata($media, wp_generate_attachment_metadata($media, $upload['file']));
} else {
    $media = $attachment->ID;
}
update_post_meta($media, '_wp_attachment_image_alt', 'Geometric mountain landscape');

$imageBlock = '<!-- wp:image {"id":' . $media . '} --><figure class="wp-block-image"><img src="'
    . esc_url(wp_get_attachment_url($media)) . '" alt="Geometric mountain landscape" class="wp-image-' . $media
    . '"/><figcaption class="wp-element-caption">A geometric landscape</figcaption></figure><!-- /wp:image -->';
sourcePost('rehearsal-about', 'page', $published + [
    'post_title' => 'Rehearsal About',
    'post_content' => '<!-- wp:paragraph --><p>Continue to the <a href="' . esc_url(get_permalink($guide) . '#read-more')
        . '">migration guide</a>.</p><!-- /wp:paragraph -->' . $imageBlock,
]);
set_post_thumbnail($page, $media);

$post = sourcePost('rehearsal-news', 'post', $published + [
    'post_title' => 'Rehearsal News',
    'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">WordPress migration</h2><!-- /wp:heading -->'
        . '<!-- wp:paragraph --><p>News for the <a href="' . esc_url(get_permalink($page)) . '">About page</a>.</p><!-- /wp:paragraph -->'
        . '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>A native Gutenberg quotation.</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->'
        . $imageBlock,
]);
set_post_thumbnail($post, $media);
checked(wp_set_post_terms($post, [sourceTerm('rehearsal-field-notes', 'Rehearsal Field Notes', 'category')], 'category'));
checked(wp_set_post_terms($post, [sourceTerm('rehearsal-migration', 'Rehearsal Migration', 'post_tag')], 'post_tag'));

$draft = sourcePost('rehearsal-draft', 'post', [
    'post_title' => 'Rehearsal Draft',
    'post_content' => '<!-- wp:paragraph --><p>This draft exists only in the WXR export.</p><!-- /wp:paragraph -->',
    'post_status' => 'draft',
    'post_author' => $author,
    'post_date' => $date,
]);

echo wp_json_encode([
    'wordpress' => get_bloginfo('version'),
    'site' => home_url(),
    'author' => $author,
    'pages' => [$page, $guide],
    'posts' => [$post, $draft],
    'media' => $media,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
