<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Import;

use DOMDocument;
use DOMElement;

/**
 * @phpstan-type Term array{taxonomy:string,slug:string,name:string,parent:string}
 * @phpstan-type TermReference array{taxonomy:string,slug:string}
 * @phpstan-type Attachment array{id:string,path:string,url:string,alt:string,eligible:bool}
 * @phpstan-type ExportItem array{id: string, type: string, status: string, title: string, slug: string, html: string, link: string, passwordProtected: bool, terms:list<TermReference>}
 */
final class WordPressExportReader
{
    private const MAX_BYTES = 8 * 1024 * 1024;
    private const MAX_ELEMENTS = 100_000;
    private const MAX_ITEMS = 5_000;
    private const CONTENT_NAMESPACE = 'http://purl.org/rss/1.0/modules/content/';
    private const WORDPRESS_NAMESPACES = [
        '1.2' => 'http://wordpress.org/export/1.2/',
        '1.1' => 'http://wordpress.org/export/1.1/',
        '1.0' => 'http://wordpress.org/export/1.0/',
    ];

    /** @return array{items:list<ExportItem>,skippedTypes:array<string,int>,attachments:list<Attachment>,terms:list<Term>} */
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
        $itemTerms = [];
        $withheld = [];
        $skippedTypes = [];
        $seen = [];
        $count = 0;
        foreach ($channel->childNodes as $node) {
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
                'terms' => $type === 'post' && !$protected && in_array($status, ['publish', 'draft', 'pending', 'future'], true)
                    ? $this->postTerms($node, $itemTerms)
                    : [],
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

        return ['items' => $items, 'skippedTypes' => $skippedTypes, 'attachments' => $media, 'terms' => [...$terms, ...array_values($itemTerms)]];
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
