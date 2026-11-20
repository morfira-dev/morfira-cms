<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Http;

use Cms\Content\Application\Import\ImportWordPressExport;
use Cms\Content\Application\Import\WordPressExportSource;
use Cms\Content\Application\Import\WordPressImportClaimConflict;
use Cms\Content\Application\Import\WordPressImportRunHistory;
use Cms\Content\Application\Import\WordPressStudioRestReadFailed;
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
    private const int MAX_REST_REQUEST_BYTES = 128 * 1024;
    private const int RUNS_PAGE_SIZE = 20;

    public function __construct(
        private Security $security,
        private ImportWordPressExport $import,
        private WordPressImportRunHistory $history,
    ) {
    }

    #[Route('/api/v1/admin/imports/wordpress', name: 'content.imports.wordpress', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_OWNER')) {
            return self::json(['error' => 'Owner access required.'], 403);
        }
        if ((int) $request->headers->get('Content-Length', '0') > self::MAX_REQUEST_BYTES) {
            return self::json(['error' => 'The WordPress import request exceeds the 9 MiB limit.'], 413);
        }
        if (preg_match('/\Amultipart\/form-data(?:\s*;|\z)/i', $request->headers->get('Content-Type') ?? '') !== 1) {
            return self::json(['error' => 'Expected a multipart WordPress import request.'], 422);
        }

        $fields = $request->request->all();
        $files = $request->files->all();
        $source = $fields['source'] ?? null;
        $mode = $fields['mode'] ?? null;
        $file = $files['file'] ?? null;
        if (array_diff_key($fields, array_flip(['source', 'mode', 'selectedIds'])) !== [] || count($files) !== 1 || !is_string($source) || !in_array($mode, ['dry-run', 'apply'], true) || !$file instanceof UploadedFile) {
            return self::json(['error' => 'Provide a WXR file, source key, and dry-run or apply mode.'], 422);
        }
        $selectedIds = null;
        if (array_key_exists('selectedIds', $fields)) {
            $selectedIds = $fields['selectedIds'];
            if (!is_string($selectedIds)) {
                return self::json(['error' => 'Selected IDs must be a comma-separated text field.'], 422);
            }
        }
        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return self::json(['error' => 'The WordPress export exceeds the 8 MiB limit.'], 413);
        }
        if (!$file->isValid()) {
            return self::json(['error' => 'The WordPress export upload is invalid.'], 422);
        }
        if ($file->getSize() > WordPressExportSource::MAX_BYTES) {
            return self::json(['error' => 'The WordPress export exceeds the 8 MiB limit.'], 413);
        }

        return $this->respondToImport(fn (): array => ($this->import)($file->getPathname(), $source, $mode === 'apply', $selectedIds));
    }

    #[Route('/api/v1/admin/imports/wordpress/rest', name: 'content.imports.wordpress.rest', methods: ['POST'])]
    public function rest(Request $request): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_OWNER')) {
            return self::json(['error' => 'Owner access required.'], 403);
        }
        if ((int) $request->headers->get('Content-Length', '0') > self::MAX_REST_REQUEST_BYTES) {
            return self::json(['error' => 'The WordPress REST import request exceeds the 128 KiB limit.'], 413);
        }
        if (preg_match('/\Aapplication\/json(?:\s*;|\z)/i', $request->headers->get('Content-Type') ?? '') !== 1) {
            return self::json(['error' => 'Expected a JSON WordPress REST import request.'], 422);
        }
        $body = stream_get_contents($request->getContent(true), self::MAX_REST_REQUEST_BYTES + 1);
        if ($body === false) {
            return self::json(['error' => 'Could not read the WordPress REST import request.'], 422);
        }
        if (strlen($body) > self::MAX_REST_REQUEST_BYTES) {
            return self::json(['error' => 'The WordPress REST import request exceeds the 128 KiB limit.'], 413);
        }
        try {
            $decoded = json_decode($body, depth: 16, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::json(['error' => 'Expected a JSON WordPress REST import request.'], 422);
        }
        if (!$decoded instanceof \stdClass) {
            return self::json(['error' => 'Expected a JSON WordPress REST import request.'], 422);
        }
        $fields = get_object_vars($decoded);
        $apiUrl = $fields['apiUrl'] ?? null;
        $source = $fields['source'] ?? null;
        $mode = $fields['mode'] ?? null;
        if (array_diff_key($fields, array_flip(['apiUrl', 'source', 'mode', 'selectedIds', 'downloadMedia', 'allowedMediaHosts'])) !== [] || !is_string($apiUrl) || !is_string($source) || !in_array($mode, ['dry-run', 'apply'], true)) {
            return self::json(['error' => 'Provide a WordPress REST API URL, source key, and dry-run or apply mode.'], 422);
        }
        $selectedIds = null;
        if (array_key_exists('selectedIds', $fields)) {
            $selectedIds = $fields['selectedIds'];
            if (!is_string($selectedIds)) {
                return self::json(['error' => 'Selected IDs must be a comma-separated text field.'], 422);
            }
        }

        $downloadMedia = array_key_exists('downloadMedia', $fields) ? $fields['downloadMedia'] : false;
        if (!is_bool($downloadMedia)) {
            return self::json(['error' => 'Download media must be a boolean.'], 422);
        }
        $allowedMediaHosts = null;
        if (array_key_exists('allowedMediaHosts', $fields)) {
            $hosts = $fields['allowedMediaHosts'];
            if (!is_array($hosts) || !array_is_list($hosts)) {
                return self::json(['error' => ImportWordPressExport::INVALID_MEDIA_HOSTS], 422);
            }
            $allowedMediaHosts = [];
            foreach ($hosts as $host) {
                if (!is_string($host)) {
                    return self::json(['error' => ImportWordPressExport::INVALID_MEDIA_HOSTS], 422);
                }
                $allowedMediaHosts[] = $host;
            }
        }

        return $this->respondToImport(fn (): array => $this->import->fromRest($apiUrl, $source, $mode === 'apply', $selectedIds, $downloadMedia, $allowedMediaHosts));
    }

    #[Route('/api/v1/admin/imports/wordpress/runs', name: 'content.imports.wordpress.runs', methods: ['GET'])]
    public function runs(Request $request): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_OWNER')) {
            return self::json(['error' => 'Owner access required.'], 403);
        }
        $page = $request->query->all()['page'] ?? '1';
        if (!is_string($page) || preg_match('/\A(?:[1-9][0-9]{0,5}|1000000)\z/', $page) !== 1) {
            return self::json(['error' => 'Page must be an integer between 1 and 1000000.'], 422);
        }
        $page = (int) $page;
        $runs = $this->history->latest(($page - 1) * self::RUNS_PAGE_SIZE, self::RUNS_PAGE_SIZE + 1);

        return self::json([
            'items' => array_slice($runs, 0, self::RUNS_PAGE_SIZE),
            'page' => $page,
            'hasMore' => count($runs) > self::RUNS_PAGE_SIZE,
        ]);
    }

    #[Route('/api/v1/admin/imports/wordpress/runs/{id}', name: 'content.imports.wordpress.run', methods: ['GET'])]
    public function run(string $id): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_OWNER')) {
            return self::json(['error' => 'Owner access required.'], 403);
        }
        $report = preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/', $id) === 1
            ? $this->history->report($id)
            : null;

        return $report === null
            ? self::json(['error' => 'WordPress import run not found.'], 404)
            : self::json($report);
    }

    /** @param \Closure(): array<string,mixed> $operation */
    private function respondToImport(\Closure $operation): JsonResponse
    {
        try {
            $report = $operation();
        } catch (\InvalidArgumentException $error) {
            return self::json(['error' => $error->getMessage()], 422);
        } catch (WordPressStudioRestReadFailed $error) {
            return self::json(['error' => $error->getMessage()], 502);
        } catch (WordPressImportClaimConflict | PageSlugAlreadyExists | EntrySlugAlreadyExists | ContentTypeKeyAlreadyExists | ContentTypeVersionConflict | ContentTypeFieldsConflict | TaxonomyConflict) {
            return self::json(['error' => 'The import conflicted with a concurrent change. Review a new plan and try again.'], 409);
        }

        return self::json($report);
    }

    private static function json(mixed $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'private, no-store']);
    }
}
