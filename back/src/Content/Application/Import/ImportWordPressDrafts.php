<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

use Cms\Content\Application\CreateEntry;
use Cms\Content\Application\CreatePage\CreatePage;
use Cms\Content\Application\UpdatePage\UpdatePage;
use Cms\Content\Domain\EntryRepository;
use Cms\Content\Domain\EntrySlug;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageSeo;
use Cms\Content\Domain\PageTitle;
use Cms\Media\Application\MediaAssetView;
use Cms\Publishing\Application\RegisterImportedRedirect;
use Cms\Schema\Application\ContentTypeDefinition;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\UpdateContentType;
use Cms\Schema\Domain\ContentTypeNotFound;
use Cms\Settings\Application\GetSiteSettings;
use Cms\Taxonomy\Application\ImportWordPressTaxonomies;

/**
 * @phpstan-import-type Attachment from ImportWordPressMedia
 * @phpstan-import-type MediaOutcome from ImportWordPressMedia
 * @phpstan-import-type Field from WordPressPostImages
 * @phpstan-import-type Segment from SegmentWordPressImages
 * @phpstan-import-type Author from ImportWordPressAuthors
 * @phpstan-import-type AuthorReference from ImportWordPressAuthors
 * @phpstan-import-type AuthorOutcome from ImportWordPressAuthors
 * @phpstan-type TermReference array{taxonomy:string,slug:string}
 * @phpstan-type Term array{taxonomy:string,slug:string,name:string,parent:string}
 * @phpstan-type SourceItem array{id:string,type:string,status:string,title:string,slug:string,html:string,link:string,passwordProtected:bool,terms:list<TermReference>,thumbnailId:?string,authorLogin:?string,restrictedSource?:bool}
 * @phpstan-type ReportItem array{id:string,type:string,status:string,sourcePath:?string,targetPath:?string,state:string,reason?:string,images?:array{inline:int,missingInline:int,featured:string},author?:string,redirect?:string,restrictedSource?:bool}
 * @phpstan-type DraftPlan array{title:string,slug:string,segments:list<Segment>,featured:?string,post:?array{fields:list<Field>,values:array<string,string>}}
 * @phpstan-type TaxonomyOutcomes array{terms:list<array{taxonomy:string,slug:string,state:string}>,assignments:list<array{entryId:string,taxonomy:string,slug:string,state:string}>}
 * @phpstan-type ImportReport array{items:list<ReportItem>,media:list<MediaOutcome>,taxonomy:TaxonomyOutcomes,authors:list<AuthorOutcome>}
 */
final readonly class ImportWordPressDrafts
{
    private const string POST_TYPE = 'wordpress-posts';

    public function __construct(
        private WordPressImportLedger $ledger,
        private CreatePage $createPage,
        private UpdatePage $updatePage,
        private CreateEntry $createEntry,
        private CreateContentType $createContentType,
        private GetContentTypeDefinition $definitions,
        private GetSiteSettings $settings,
        private PageRepository $pages,
        private EntryRepository $entries,
        private SegmentWordPressImages $segmentImages,
        private ImportWordPressTaxonomies $taxonomies,
        private ImportWordPressMedia $media,
        private UpdateContentType $updateContentType,
        private ImportWordPressAuthors $authors,
        private RegisterImportedRedirect $registerRedirect,
    ) {
    }

    /**
     * @param list<SourceItem> $items
     * @param list<Term> $terms
     * @param list<Attachment> $attachments
     * @param list<Author> $authors
     * @param list<string> $allowedMediaHosts
     * @return ImportReport
     */
    public function __invoke(string $source, array $items, bool $apply, array $terms, array $attachments, ?string $mediaDirectory, array $authors, ?string $sourceUrl, ?string $apiUrl = null, array $allowedMediaHosts = [], ?WordPressRestCredentials $credentials = null): array
    {
        if (!self::validSourceKey($source)) {
            throw new \InvalidArgumentException('Source must be a lowercase identifier of at most 80 characters.');
        }

        /** @var \ArrayObject<int,MediaAssetView> $created */
        $created = new \ArrayObject();
        $credentials = $apiUrl === null ? null : $credentials;
        $run = function () use ($source, $items, $apply, $terms, $attachments, $mediaDirectory, $created, $authors, $sourceUrl, $apiUrl, $allowedMediaHosts, $credentials): array {
            $media = ($this->media)($source, $attachments, $mediaDirectory, $apply, $created, $apiUrl, $allowedMediaHosts, $credentials);
            $logins = [];
            foreach ($items as $item) {
                if ($item['type'] === 'post' && $item['authorLogin'] !== null) {
                    $logins[] = $item['authorLogin'];
                }
            }
            $importedAuthors = ($this->authors)($source, $authors, $logins, $apply);
            $assignments = [];
            $images = new WordPressImageIndex($attachments, $media, fn (string $id): ?array => $this->media->reusableImage($source, $id));
            $report = $this->process($source, $items, $apply, $assignments, $images, $importedAuthors['profiles'], $sourceUrl, $credentials !== null);
            $taxonomy = ($this->taxonomies)($terms, $assignments, $apply);

            return [
                'items' => $report,
                'taxonomy' => $taxonomy,
                'media' => $media,
                'authors' => $importedAuthors['outcomes'],
            ];
        };

        try {
            return $apply ? $this->ledger->transactional($run) : $run();
        } catch (\Throwable $error) {
            $this->media->compensate($created);
            throw $error;
        }
    }

    public static function validSourceKey(string $source): bool
    {
        return strlen($source) <= 80 && preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $source) === 1;
    }

    /**
     * @param list<SourceItem> $items
     * @param array<string,list<TermReference>> $assignments
     * @param array<string,AuthorReference> $authors
     * @return list<ReportItem>
     */
    private function process(string $source, array $items, bool $apply, array &$assignments, WordPressImageIndex $images, array $authors, ?string $sourceUrl, bool $includePrivate): array
    {
        $locale = ($this->settings)()->defaultLocale;
        $postSchema = $this->findPostSchema();
        $postFields = $postSchema->fields ?? [];
        $postTypeId = $postSchema?->id;
        $compatibleSchema = $postSchema === null || $this->supportsPostBody($postSchema);
        $occupied = [];
        foreach ($this->pages->all() as $page) {
            if ($page->locale === $locale) {
                $occupied['/'.$page->slug->value] = true;
            }
        }
        if ($postTypeId !== null) {
            foreach ($this->entries->all($postTypeId) as $entry) {
                $occupied[$this->postPath($entry->slug->value)] = true;
            }
        }

        $report = [];
        $drafts = [];
        $permalinks = [];
        foreach ($items as $index => $item) {
            $draft = null;
            $author = $item['type'] === 'post' && $item['authorLogin'] !== null ? ($authors[ImportWordPressAuthors::key($item['authorLogin'])] ?? null) : null;
            $row = $this->planItem($item, $source, $occupied, $postFields, $compatibleSchema, $draft, $images, $author, $includePrivate);
            $report[] = $row;
            if ($draft !== null) {
                $drafts[$index] = $draft;
            }
            if ($apply) {
                $path = $draft !== null && $item['status'] === 'publish' && !$item['passwordProtected'] && !($item['restrictedSource'] ?? false)
                    ? $row['targetPath']
                    : null;
                $permalinks[] = ['url' => $item['link'], 'path' => $path === '/home' ? '/' : $path];
            }
        }

        $links = WordPressInternalLinks::index($sourceUrl, $permalinks);
        foreach ($report as $index => $row) {
            if (!isset($drafts[$index])) {
                continue;
            }
            $item = $items[$index];
            $targetId = null;
            if ($apply) {
                $this->ledger->claim($source, $item['id']);
                $targetId = $this->createDraft($drafts[$index], $locale, $postSchema, $links);
                $row['state'] = 'imported';
            }
            $row['redirect'] = $this->redirect($item, $sourceUrl, $row['targetPath'], $targetId);
            $report[$index] = $row;
            if ($item['type'] === 'post' && $item['terms'] !== []) {
                $assignments[$targetId ?? 'wp-'.$item['id']] = $item['terms'];
            }
        }

        return $report;
    }

    /**
     * @param SourceItem $item
     * @param array<string,bool> $occupied
     * @param list<Field> $postFields
     * @param ?DraftPlan $draft
     * @param ?AuthorReference $author
     * @return ReportItem
     */
    private function planItem(array $item, string $source, array &$occupied, array &$postFields, bool $compatibleSchema, ?array &$draft, WordPressImageIndex $images, ?array $author, bool $includePrivate): array
    {
        $path = parse_url($item['link'], PHP_URL_PATH);
        $row = [
            'id' => $item['id'],
            'type' => $item['type'],
            'status' => $item['status'],
            'sourcePath' => is_string($path) && str_starts_with($path, '/') ? $path : null,
            'targetPath' => null,
        ];
        $restricted = $item['restrictedSource'] ?? false;
        if ($restricted) {
            $row['restrictedSource'] = true;
            $row['sourcePath'] = null;
        }
        if ($this->ledger->contains($source, $item['id'])) {
            return $row + ['state' => 'already_imported'];
        }
        $ordinaryEligible = !$restricted && !$item['passwordProtected'] && in_array($item['status'], ['publish', 'draft', 'pending', 'future'], true);
        $restrictedEligible = $restricted && $includePrivate && in_array($item['status'], ['publish', 'private', 'draft', 'pending', 'future'], true);
        if (!$ordinaryEligible && !$restrictedEligible) {
            return $row + ['state' => 'unsupported_status', 'reason' => 'Source status or password protection is outside the import policy.'];
        }

        $isPage = $item['type'] === 'page';
        $slug = $this->slug($item['slug'], $item['id']);
        $row['targetPath'] = $isPage ? '/'.$slug : $this->postPath($slug);
        if (isset($occupied[$row['targetPath']])) {
            return $row + ['state' => 'invalid', 'reason' => 'Draft slug already exists.'];
        }
        if (!$isPage && !$compatibleSchema) {
            return $row + ['state' => 'invalid', 'reason' => 'Existing wordpress-posts schema cannot accept the import body.'];
        }
        if ($isPage && in_array($slug, ['admin', 'health', 'ready', '404'], true)) {
            return $row + ['state' => 'invalid', 'reason' => 'Page slug is reserved.'];
        }

        try {
            $title = (new PageTitle($item['title']))->value;
            $segmented = ($this->segmentImages)($item['html'], $images->resolve(...), nativeCaptions: $isPage);
            $segments = $segmented['segments'];
            $featured = $images->byId($item['thumbnailId'])['assetId'] ?? null;
            foreach ($segments as $segment) {
                if ($segment['type'] === 'richText' && mb_strlen($segment['html']) > 20_000) {
                    throw new \InvalidArgumentException('A sanitized text segment exceeds the 20,000 character limit.');
                }
            }
            $post = $isPage ? null : WordPressPostImages::plan($segments, $featured, $postFields, $author);
        } catch (\InvalidArgumentException $error) {
            return $row + ['state' => 'invalid', 'reason' => $error->getMessage()];
        }

        $row['images'] = [
            'inline' => count(array_filter($segments, static fn (array $segment): bool => $segment['type'] === 'image')),
            'missingInline' => $segmented['missingImages'],
            'featured' => $featured !== null ? 'linked' : ($item['thumbnailId'] !== null ? 'missing' : 'none'),
        ];
        if (!$isPage) {
            $row['author'] = $author !== null ? 'linked' : ($item['authorLogin'] === null ? 'none' : 'missing');
        }
        $occupied[$row['targetPath']] = true;
        if ($post !== null) {
            $postFields = $post['fields'];
        }
        $draft = ['title' => $title, 'slug' => $slug, 'segments' => $segments, 'featured' => $featured, 'post' => $post];

        return $row + ['state' => 'planned'];
    }

    /**
     * @param DraftPlan $draft
     * @param array<string,string> $links
     */
    private function createDraft(array $draft, string $locale, ?ContentTypeDefinition &$postSchema, array $links): string
    {
        ['title' => $title, 'slug' => $slug, 'segments' => $segments, 'featured' => $featured, 'post' => $post] = $draft;
        if ($post === null) {
            $page = ($this->createPage)($title, $slug, $locale);
            if ($segments !== [] || $featured !== null) {
                ($this->updatePage)($page->id, $title, $slug, $this->pageBlocks($segments, $links), new PageSeo('', '', false, $featured), $page->version);
            }

            return $page->id;
        }

        $postSchema = $this->savePostSchema($postSchema, $post['fields']);
        $values = $post['values'];
        foreach ($postSchema->fields as $field) {
            if ($field['type'] === 'richText' && isset($values[$field['key']])) {
                $values[$field['key']] = WordPressInternalLinks::rewrite($values[$field['key']], $links);
            }
        }

        return ($this->createEntry)($postSchema->id, $title, $slug, $values)->id;
    }

    /** @param SourceItem $item */
    private function redirect(array $item, ?string $sourceUrl, ?string $targetPath, ?string $targetId): string
    {
        if ($item['status'] !== 'publish' || ($item['restrictedSource'] ?? false) || $item['link'] === '') {
            return 'none';
        }
        $source = $sourceUrl === null ? false : parse_url($sourceUrl);
        $link = parse_url($item['link']);
        if (!is_array($source) || !is_array($link) || !isset($source['host'], $link['host'])
            || strcasecmp($source['host'], $link['host']) !== 0 || ($source['port'] ?? null) !== ($link['port'] ?? null)) {
            return 'unsupported';
        }
        if (($link['path'] ?? '/') === $targetPath && !isset($link['query'])) {
            return 'same_route';
        }

        return ($this->registerRedirect)($item['link'], $item['type'] === 'page' ? 'page' : 'entry', $targetId);
    }

    /** @param list<Field> $fields */
    private function savePostSchema(?ContentTypeDefinition $schema, array $fields): ContentTypeDefinition
    {
        if ($schema === null) {
            $view = ($this->createContentType)('WordPress posts', self::POST_TYPE, $fields);
        } elseif ($schema->fields !== $fields) {
            $view = ($this->updateContentType)($schema->id, $schema->name, $schema->apiName, $fields, $schema->version);
        } else {
            return $schema;
        }

        return new ContentTypeDefinition($view->id, $view->name, $view->apiName, $view->version, $view->fields);
    }

    /**
     * @param list<Segment> $segments
     * @param array<string,string> $links
     * @return list<array<string,mixed>>
     */
    private function pageBlocks(array $segments, array $links): array
    {
        return array_map(static fn (array $segment): array => [
            'id' => PageId::new()->value,
            'type' => $segment['type'],
            'data' => $segment['type'] === 'image'
                ? ['assetId' => $segment['assetId'], 'alt' => $segment['alt']] + (isset($segment['caption']) ? ['caption' => $segment['caption']] : [])
                : ['content' => WordPressInternalLinks::rewrite($segment['html'], $links)],
        ], $segments);
    }

    private function slug(string $sourceSlug, string $id): string
    {
        $slug = strtolower(trim($sourceSlug));
        try {
            return (new EntrySlug($slug))->value;
        } catch (\InvalidArgumentException) {
            return 'wp-'.$id;
        }
    }

    private function postPath(string $slug): string
    {
        return '/content/'.self::POST_TYPE.'/'.$slug;
    }

    private function findPostSchema(): ?ContentTypeDefinition
    {
        try {
            return $this->definitions->byKey(self::POST_TYPE);
        } catch (ContentTypeNotFound) {
            return null;
        }
    }

    private function supportsPostBody(ContentTypeDefinition $schema): bool
    {
        $bodySupported = false;
        foreach ($schema->fields as $field) {
            if ($field['key'] === 'body') {
                $bodySupported = $field['type'] === 'richText' && !$field['required'];
            } elseif ($field['required']) {
                return false;
            }
        }

        return $bodySupported;
    }
}
