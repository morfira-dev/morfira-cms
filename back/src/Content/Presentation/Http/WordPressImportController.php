<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Http;

use Cms\Content\Application\Import\ImportWordPressExport;
use Cms\Content\Application\Import\WordPressExportSource;
use Cms\Content\Application\Import\WordPressImportClaimConflict;
use Cms\Content\Domain\EntrySlugAlreadyExists;
use Cms\Content\Domain\PageSlugAlreadyExists;
use Cms\Schema\Domain\ContentTypeFieldsConflict;
use Cms\Schema\Domain\ContentTypeKeyAlreadyExists;
use Cms\Schema\Domain\ContentTypeVersionConflict;
use Cms\Taxonomy\Domain\TaxonomyConflict;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class WordPressImportController
{
    private const int MAX_REQUEST_BYTES = 9 * 1024 * 1024;

    public function __construct(private Security $security, private ImportWordPressExport $import)
    {
    }

    #[Route('/api/v1/admin/imports/wordpress', name: 'content.imports.wordpress', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_OWNER')) {
            return new JsonResponse(['error' => 'Owner access required.'], 403);
        }
        if ((int) $request->headers->get('Content-Length', '0') > self::MAX_REQUEST_BYTES) {
            return new JsonResponse(['error' => 'The WordPress import request exceeds the 9 MiB limit.'], 413);
        }
        if (preg_match('/\Amultipart\/form-data(?:\s*;|\z)/i', $request->headers->get('Content-Type') ?? '') !== 1) {
            return new JsonResponse(['error' => 'Expected a multipart WordPress import request.'], 422);
        }

        $fields = $request->request->all();
        $files = $request->files->all();
        $source = $fields['source'] ?? null;
        $mode = $fields['mode'] ?? null;
        $file = $files['file'] ?? null;
        if (array_diff_key($fields, array_flip(['source', 'mode', 'selectedIds'])) !== [] || count($files) !== 1 || !is_string($source) || !in_array($mode, ['dry-run', 'apply'], true) || !$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'Provide a WXR file, source key, and dry-run or apply mode.'], 422);
        }
        $selectedIds = null;
        if (array_key_exists('selectedIds', $fields)) {
            $selectedIds = $fields['selectedIds'];
            if (!is_string($selectedIds)) {
                return new JsonResponse(['error' => 'Selected IDs must be a comma-separated text field.'], 422);
            }
        }
        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return new JsonResponse(['error' => 'The WordPress export exceeds the 8 MiB limit.'], 413);
        }
        if (!$file->isValid()) {
            return new JsonResponse(['error' => 'The WordPress export upload is invalid.'], 422);
        }
        if ($file->getSize() > WordPressExportSource::MAX_BYTES) {
            return new JsonResponse(['error' => 'The WordPress export exceeds the 8 MiB limit.'], 413);
        }

        try {
            $report = ($this->import)($file->getPathname(), $source, $mode === 'apply', $selectedIds);
        } catch (\InvalidArgumentException $error) {
            return new JsonResponse(['error' => $error->getMessage()], 422);
        } catch (WordPressImportClaimConflict | PageSlugAlreadyExists | EntrySlugAlreadyExists | ContentTypeKeyAlreadyExists | ContentTypeVersionConflict | ContentTypeFieldsConflict | TaxonomyConflict) {
            return new JsonResponse(['error' => 'The import conflicted with a concurrent change. Review a new plan and try again.'], 409);
        }

        return new JsonResponse($report, headers: ['Cache-Control' => 'private, no-store']);
    }
}
