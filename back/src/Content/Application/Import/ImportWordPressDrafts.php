<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

use Cms\Content\Application\CreateEntry;
use Cms\Content\Application\CreatePage\CreatePage;
use Cms\Content\Application\RichTextSanitizer;
use Cms\Content\Application\UpdatePage\UpdatePage;
use Cms\Content\Domain\EntryRepository;
use Cms\Content\Domain\EntrySlug;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageSeo;
use Cms\Content\Domain\PageTitle;
use Cms\Media\Application\MediaAssetView;
use Cms\Schema\Application\ContentTypeDefinition;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Domain\ContentTypeNotFound;
use Cms\Settings\Application\GetSiteSettings;
use Cms\Taxonomy\Application\ImportWordPressTaxonomies;

/**
 * @phpstan-import-type Attachment from ImportWordPressMedia
 * @phpstan-import-type MediaOutcome from ImportWordPressMedia
 * @phpstan-type TermReference array{taxonomy:string,slug:string}
 * @phpstan-type Term array{taxonomy:string,slug:string,name:string,parent:string}
 * @phpstan-type SourceItem array{id:string,type:string,status:string,title:string,slug:string,html:string,link:string,passwordProtected:bool,terms:list<TermReference>}
 * @phpstan-type ReportItem array{id:string,type:string,status:string,sourcePath:?string,targetPath:?string,state:string,reason?:string}
 * @phpstan-type TaxonomyOutcomes array{terms:list<array{taxonomy:string,slug:string,state:string}>,assignments:list<array{entryId:string,taxonomy:string,slug:string,state:string}>}
 * @phpstan-type ImportReport array{items:list<ReportItem>,media:list<MediaOutcome>,taxonomy:TaxonomyOutcomes}
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
        private RichTextSanitizer $sanitizer,
        private ImportWordPressTaxonomies $taxonomies,
        private ImportWordPressMedia $media,
    ) {
    }

    /**
     * @param list<SourceItem> $items
     * @param list<Term> $terms
     * @param list<Attachment> $attachments
     * @return ImportReport
     */
    public function __invoke(string $source, array $items, bool $apply, array $terms, array $attachments, ?string $mediaDirectory): array
    {
        if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $source) !== 1 || strlen($source) > 80) {
            throw new \InvalidArgumentException('Source must be a lowercase identifier of at most 80 characters.');
        }

        /** @var \ArrayObject<int,MediaAssetView> $created */
        $created = new \ArrayObject();
        $run = function () use ($source, $items, $apply, $terms, $attachments, $mediaDirectory, $created): array {
            $assignments = [];
            $report = $this->process($source, $items, $apply, $assignments);
            $taxonomy = ($this->taxonomies)($terms, $assignments, $apply);

            return [
                'items' => $report,
                'taxonomy' => $taxonomy,
                'media' => ($this->media)($source, $attachments, $mediaDirectory, $apply, $created),
            ];
        };

        try {
            return $apply ? $this->ledger->transactional($run) : $run();
        } catch (\Throwable $error) {
            $this->media->compensate($created);
            throw $error;
        }
    }

    /**
     * @param list<SourceItem> $items
     * @param array<string,list<TermReference>> $assignments
     * @return list<ReportItem>
     */
    private function process(string $source, array $items, bool $apply, array &$assignments): array
    {
        $locale = ($this->settings)()->defaultLocale;
        $postSchema = $this->findPostSchema();
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
        foreach ($items as $item) {
            $entryId = null;
            $row = $this->processItem($item, $source, $locale, $apply, $occupied, $postTypeId, $compatibleSchema, $entryId);
            $report[] = $row;
            if ($item['type'] === 'post' && $item['terms'] !== []) {
                $assignmentKey = $apply ? $entryId : ($row['state'] === 'planned' ? 'wp-'.$item['id'] : null);
                if ($assignmentKey !== null) {
                    $assignments[$assignmentKey] = $item['terms'];
                }
            }
        }

        return $report;
    }

    /**
     * @param SourceItem $item
     * @param array<string,bool> $occupied
     * @return ReportItem
     */
    private function processItem(array $item, string $source, string $locale, bool $apply, array &$occupied, ?string &$postTypeId, bool $compatibleSchema, ?string &$entryId): array
    {
        $path = parse_url($item['link'], PHP_URL_PATH);
        $row = [
            'id' => $item['id'],
            'type' => $item['type'],
            'status' => $item['status'],
            'sourcePath' => is_string($path) && str_starts_with($path, '/') ? $path : null,
            'targetPath' => null,
        ];
        if ($this->ledger->contains($source, $item['id'])) {
            return $row + ['state' => 'already_imported'];
        }
        if ($item['passwordProtected'] || !in_array($item['status'], ['publish', 'draft', 'pending', 'future'], true)) {
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
            $html = trim($this->sanitizer->sanitize($item['html']));
            $visibleText = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($isPage && preg_match('/\A[\s\x{00A0}]*\z/u', $visibleText) === 1) {
                $html = '';
            }
            if (mb_strlen($html) > 20_000) {
                throw new \InvalidArgumentException('Sanitized content exceeds the 20,000 character limit.');
            }
        } catch (\InvalidArgumentException $error) {
            return $row + ['state' => 'invalid', 'reason' => $error->getMessage()];
        }

        $occupied[$row['targetPath']] = true;
        if (!$apply) {
            return $row + ['state' => 'planned'];
        }

        $this->ledger->claim($source, $item['id']);
        if ($isPage) {
            $page = ($this->createPage)($title, $slug, $locale);
            if ($html !== '') {
                ($this->updatePage)($page->id, $title, $slug, [[
                    'id' => PageId::new()->value,
                    'type' => 'richText',
                    'data' => ['content' => $html],
                ]], PageSeo::defaults(), $page->version);
            }
        } else {
            $postTypeId ??= ($this->createContentType)('WordPress posts', self::POST_TYPE, [[
                'id' => PageId::new()->value,
                'key' => 'body',
                'label' => 'Body',
                'type' => 'richText',
                'required' => false,
            ]])->id;
            $entryId = ($this->createEntry)($postTypeId, $title, $slug, $html === '' ? [] : ['body' => $html])->id;
        }

        return $row + ['state' => 'imported'];
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
