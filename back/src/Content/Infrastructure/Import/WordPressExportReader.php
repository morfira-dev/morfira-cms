<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Import;

use Cms\Content\Application\Import\WordPressExportSource;
use DOMDocument;
use DOMElement;

/**
 * @phpstan-type Term array{taxonomy:string,slug:string,name:string,parent:string}
 * @phpstan-type TermReference array{taxonomy:string,slug:string}
 * @phpstan-type Attachment array{id:string,path:string,url:string,alt:string,eligible:bool,isPrivate?:bool}
 * @phpstan-import-type Author from \Cms\Content\Application\Import\ImportWordPressAuthors
 * @phpstan-type ExportItem array{id: string, type: string, status: string, title: string, slug: string, html: string, link: string, passwordProtected: bool, terms:list<TermReference>, thumbnailId:?string, authorLogin:?string, restrictedSource?:bool}
 * @phpstan-import-type Export from WordPressExportSource
 */
final class WordPressExportReader implements WordPressExportSource
{
    private const MAX_ELEMENTS = 100_000;
    private const CONTENT_NAMESPACE = 'http://purl.org/rss/1.0/modules/content/';
    private const CREATOR_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    private const WORDPRESS_NAMESPACES = [
        '1.2' => 'http://wordpress.org/export/1.2/',
        '1.1' => 'http://wordpress.org/export/1.1/',
        '1.0' => 'http://wordpress.org/export/1.0/',
    ];

    /** @return Export */
    public function read(string $path): array
    {
        if (str_contains($path, '://') || str_contains($path, "\0") || !is_file($path)) {
            throw new \InvalidArgumentException('Provide a local WordPress export file.');
        }

        $xml = @file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);
        if (false === $xml) {
            throw new \RuntimeException('The WordPress export could not be read.');
        }
        if (strlen($xml) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('The WordPress export exceeds the 8 MiB limit.');
        }

        $document = new DOMDocument();
        // Do not request DTD loading, validation, XInclude, or entity substitution.
        if ('' === $xml || !$document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new \InvalidArgumentException('The WordPress export contains invalid XML.');
        }
        if (null !== $document->doctype) {
            throw new \InvalidArgumentException('The WordPress export must not contain a document type declaration.');
        }
        if ($document->getElementsByTagName('*')->length > self::MAX_ELEMENTS) {
            throw new \InvalidArgumentException('The WordPress export contains too many XML elements.');
        }

        $rss = $document->documentElement;
        if (null === $rss || 'rss' !== $rss->localName || null !== $rss->namespaceURI) {
            throw new \InvalidArgumentException('Expected a WordPress WXR RSS document.');
        }
        $channel = $this->child($rss, 'channel');
        if (null === $channel) {
            throw new \InvalidArgumentException('The WordPress export is missing its RSS channel.');
        }

        $namespace = $this->wordpressNamespace($channel);
        $items = [];
        $attachments = [];
        $terms = [];
        $authors = [];
        $itemTerms = [];
        $withheld = [];
        $skippedTypes = [];
        $seen = [];
        $count = 0;
        foreach ($channel->childNodes as $node) {
            if ($node instanceof DOMElement && $node->namespaceURI === $namespace && $node->localName === 'author') {
                $author = $this->author($node, $namespace);
                if ($author !== null) {
                    $authorKey = 'author:'.$author['login'];
                    if (isset($authors[$authorKey]) && ($authors[$authorKey]['externalId'] ?? null) !== ($author['externalId'] ?? null)) {
                        unset($authors[$authorKey]['externalId']);
                    }
                    $authors[$authorKey] ??= $author;
                }
                continue;
            }
            if ($node instanceof DOMElement && $node->namespaceURI === $namespace && in_array($node->localName, ['category', 'tag'], true)) {
                $category = $node->localName === 'category';
                $term = [
                    'taxonomy' => $category ? 'category' : 'post_tag',
                    'slug' => trim($this->text($node, $category ? 'category_nicename' : 'tag_slug', $namespace)),
                    'name' => trim($this->text($node, $category ? 'cat_name' : 'tag_name', $namespace)),
                    'parent' => $category ? trim($this->text($node, 'category_parent', $namespace)) : '',
                ];
                $terms[] = $term;
                continue;
            }
            if (!$node instanceof DOMElement || 'item' !== $node->localName || null !== $node->namespaceURI) {
                continue;
            }
            if (++$count > self::MAX_ITEMS) {
                throw new \InvalidArgumentException('The WordPress export contains more than 5,000 items.');
            }

            $id = trim($this->text($node, 'post_id', $namespace));
            if (1 !== preg_match('/\A[1-9][0-9]{0,19}\z/', $id) || isset($seen[$id])) {
                throw new \InvalidArgumentException('The WordPress export contains an invalid or duplicate item identifier.');
            }
            $seen[$id] = true;

            $type = trim($this->text($node, 'post_type', $namespace));
            if ($type === 'attachment') {
                $attachments[] = $this->attachment($node, $namespace, $id);
                continue;
            }
            if (!in_array($type, ['page', 'post'], true)) {
                $type = 1 === preg_match('/\A[a-z_-][a-z0-9_-]{0,19}\z/', $type) ? $type : '(unknown)';
                $skippedTypes[$type] = ($skippedTypes[$type] ?? 0) + 1;
                continue;
            }

            $status = trim($this->text($node, 'status', $namespace));
            $protected = $this->text($node, 'post_password', $namespace) !== '';
            $eligible = !$protected && in_array($status, ['publish', 'draft', 'pending', 'future'], true);
            if ($protected || in_array($status, ['private', 'trash'], true)) {
                $withheld[$id] = true;
            }
            $items[] = [
                'id' => $id,
                'type' => $type,
                'status' => $status,
                'title' => trim($this->text($node, 'title')),
                'slug' => trim($this->text($node, 'post_name', $namespace)),
                'html' => $this->text($node, 'encoded', self::CONTENT_NAMESPACE),
                'link' => trim($this->text($node, 'link')),
                'passwordProtected' => $protected,
                'terms' => $type === 'post' && $eligible
                    ? $this->postTerms($node, $itemTerms)
                    : [],
                'thumbnailId' => $eligible ? $this->thumbnailId($node, $namespace) : null,
                'authorLogin' => $eligible ? $this->login($this->optionalText($node, 'creator', self::CREATOR_NAMESPACE)) : null,
            ];
        }

        ksort($skippedTypes);

        foreach ($terms as $term) {
            unset($itemTerms[$term['taxonomy'].':'.$term['slug']]);
        }

        $media = [];
        foreach ($attachments as $attachment) {
            $attachment['eligible'] = $attachment['eligible'] && !isset($withheld[$attachment['parentId']]);
            unset($attachment['parentId']);
            $media[] = $attachment;
        }

        return [
            'items' => $items,
            'skippedTypes' => $skippedTypes,
            'attachments' => $media,
            'terms' => [...$terms, ...array_values($itemTerms)],
            'authors' => array_values($authors),
            'sourceUrl' => $this->sourceUrl($this->optionalText($channel, 'base_blog_url', $namespace))
                ?? $this->sourceUrl($this->optionalText($channel, 'link')),
        ];
    }

    /** @return Author|null */
    private function author(DOMElement $element, string $namespace): ?array
    {
        $login = $this->login($this->optionalText($element, 'author_login', $namespace));
        if ($login === null) {
            return null;
        }

        $id = trim($this->optionalText($element, 'author_id', $namespace));
        $identity = preg_match('/\A[1-9][0-9]{0,19}\z/', $id) === 1 ? ['externalId' => $id] : [];
        $display = $this->optionalText($element, 'author_display_name', $namespace);
        $parts = $this->optionalText($element, 'author_first_name', $namespace).' '.$this->optionalText($element, 'author_last_name', $namespace);
        foreach ([$display, $parts] as $candidate) {
            $name = trim(preg_replace('/\s+/u', ' ', strip_tags($candidate)) ?? '');
            if ($name !== '' && !str_contains($name, '@') && preg_match('/[\x00-\x1f\x7f]/', $name) !== 1) {
                return ['login' => $login, 'name' => mb_substr($name, 0, 200)] + $identity;
            }
        }

        return ['login' => $login, 'name' => $login] + $identity;
    }

    private function login(string $value): ?string
    {
        $login = trim($value);

        return preg_match('/\A[A-Za-z0-9_. -]{1,60}\z/', $login) === 1 ? $login : null;
    }

    private function sourceUrl(string $value): ?string
    {
        $url = trim($value);
        if (strlen($url) > 2_048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $scheme.'://'.strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '');
    }

    private function optionalText(DOMElement $element, string $name, ?string $namespace = null): string
    {
        try {
            return $this->text($element, $name, $namespace);
        } catch (\InvalidArgumentException) {
            return '';
        }
    }

    /** @return array{id:string,path:string,url:string,alt:string,eligible:bool,parentId:string} */
    private function attachment(DOMElement $item, string $namespace, string $id): array
    {
        $metadata = [];
        foreach ($item->childNodes as $node) {
            if (!$node instanceof DOMElement || $node->namespaceURI !== $namespace || $node->localName !== 'postmeta') {
                continue;
            }
            $key = $this->text($node, 'meta_key', $namespace);
            if (in_array($key, ['_wp_attached_file', '_wp_attachment_image_alt'], true) && !isset($metadata[$key])) {
                $metadata[$key] = trim($this->text($node, 'meta_value', $namespace));
            }
        }

        return [
            'id' => $id,
            'path' => $metadata['_wp_attached_file'] ?? '',
            'url' => trim($this->text($item, 'attachment_url', $namespace)),
            'alt' => $metadata['_wp_attachment_image_alt'] ?? '',
            'parentId' => trim($this->text($item, 'post_parent', $namespace)),
            'eligible' => in_array(trim($this->text($item, 'status', $namespace)), ['inherit', 'publish'], true)
                && $this->text($item, 'post_password', $namespace) === '',
        ];
    }

    private function thumbnailId(DOMElement $item, string $namespace): ?string
    {
        $thumbnailId = null;
        foreach ($item->childNodes as $node) {
            if (!$node instanceof DOMElement || $node->namespaceURI !== $namespace || $node->localName !== 'postmeta') {
                continue;
            }
            try {
                if ($this->text($node, 'meta_key', $namespace) !== '_thumbnail_id') {
                    continue;
                }
                $value = trim($this->text($node, 'meta_value', $namespace));
            } catch (\InvalidArgumentException) {
                // Optional metadata must not invalidate the draft import.
                continue;
            }
            if (preg_match('/\A[1-9][0-9]{0,19}\z/', $value) !== 1 || ($thumbnailId !== null && $thumbnailId !== $value)) {
                return null;
            }
            $thumbnailId = $value;
        }

        return $thumbnailId;
    }

    /**
     * @param array<string,Term> $definitions
     * @return list<TermReference>
     */
    private function postTerms(DOMElement $item, array &$definitions): array
    {
        $references = [];
        foreach ($item->childNodes as $node) {
            if (!$node instanceof DOMElement || $node->namespaceURI !== null || $node->localName !== 'category') {
                continue;
            }
            $taxonomy = $node->getAttribute('domain');
            $slug = trim($node->getAttribute('nicename'));
            if (!in_array($taxonomy, ['category', 'post_tag'], true) || $slug === '') {
                continue;
            }
            $key = $taxonomy.':'.$slug;
            $references[$key] = ['taxonomy' => $taxonomy, 'slug' => $slug];
            $name = trim($node->textContent);
            if ($name !== '') {
                $definitions[$key] ??= ['taxonomy' => $taxonomy, 'slug' => $slug, 'name' => $name, 'parent' => ''];
            }
        }

        return array_values($references);
    }

    private function wordpressNamespace(DOMElement $channel): string
    {
        foreach (self::WORDPRESS_NAMESPACES as $version => $namespace) {
            if ($version === trim($this->text($channel, 'wxr_version', $namespace))) {
                return $namespace;
            }
        }

        throw new \InvalidArgumentException('The WordPress export must declare a supported WXR version (1.0 through 1.2).');
    }

    private function text(DOMElement $parent, string $name, ?string $namespace = null): string
    {
        return $this->child($parent, $name, $namespace)->textContent ?? '';
    }

    private function child(DOMElement $parent, string $name, ?string $namespace = null): ?DOMElement
    {
        $element = null;
        foreach ($parent->childNodes as $node) {
            if (!$node instanceof DOMElement || $name !== $node->localName || $namespace !== $node->namespaceURI) {
                continue;
            }
            if (null !== $element) {
                throw new \InvalidArgumentException('The WordPress export contains a duplicate XML field.');
            }
            $element = $node;
        }

        return $element;
    }
}
