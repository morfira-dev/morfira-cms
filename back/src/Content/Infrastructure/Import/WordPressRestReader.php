<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Import;

use Cms\Content\Application\Import\WordPressRestCredentials;

/**
 * @phpstan-import-type Export from WordPressExportReader
 */
final readonly class WordPressRestReader
{
    private const int PAGE_SIZE = 50;
    private const int MAX_REQUESTS = 110;
    private const int MAX_RECORDS = 5_000;
    private const int MAX_BATCH_BYTES = 16 * 1024 * 1024;
    private const array FIELDS = [
        'posts' => 'id,link,slug,status,title,content,author,featured_media,categories,tags',
        'pages' => 'id,link,slug,status,title,content,author,featured_media',
        'media' => 'id,source_url,media_type,mime_type,media_details.file,alt_text,status,post',
        'categories' => 'id,slug,name,parent',
        'tags' => 'id,slug,name',
        'users' => 'id,slug,name',
    ];

    public function __construct(private WordPressRestJsonTransport $transport, private WordPressRestMapper $mapper)
    {
    }

    /** @return Export */
    public function read(string $baseUrl, ?WordPressRestCredentials $credentials = null): array
    {
        $baseUrl = WordPressRestTransport::normalizeBaseUrl($baseUrl);
        $collections = [];
        $requests = $records = $bytes = 0;
        $fieldsByCollection = self::FIELDS;
        if ($credentials !== null) {
            $fieldsByCollection['authenticatedMedia'] = self::FIELDS['media'];
        }
        foreach ($fieldsByCollection as $collection => $fields) {
            $authenticatedMedia = $collection === 'authenticatedMedia';
            $resource = $authenticatedMedia ? 'media' : $collection;
            $content = in_array($resource, ['posts', 'pages'], true);
            $collectionCredentials = $content || $authenticatedMedia ? $credentials : null;
            $rows = [];
            $lastPage = 1;
            $totalPages = null;
            for ($page = 1; $page <= $lastPage; ++$page) {
                if (++$requests > self::MAX_REQUESTS) {
                    throw new \RuntimeException('WordPress REST request limit exceeded.');
                }
                $query = [
                    'context' => $collectionCredentials === null ? 'view' : 'edit',
                    'per_page' => self::PAGE_SIZE,
                    'page' => $page,
                    '_fields' => $fields,
                    'orderby' => 'id',
                    'order' => 'asc',
                ];
                if ($content) {
                    $query['status'] = $collectionCredentials === null ? 'publish' : 'publish,private,draft,pending,future';
                } elseif ($authenticatedMedia) {
                    $query['status'] = 'inherit,private';
                }
                $response = $this->transport->get($baseUrl, $resource, $query, $collectionCredentials);
                if ($response['totalPages'] < 0 || $response['totalPages'] > self::MAX_REQUESTS
                    || ($totalPages !== null && $response['totalPages'] !== $totalPages)) {
                    throw new \RuntimeException('WordPress REST pagination changed or exceeds the import limits.');
                }
                $totalPages = $response['totalPages'];
                $lastPage = max(1, $totalPages);
                $body = $response['body'];
                $count = count($body);
                if ($count > self::PAGE_SIZE || ($page < $lastPage && $count < self::PAGE_SIZE)
                    || ($totalPages === 0 && $count !== 0) || ($totalPages > 0 && $count === 0)) {
                    throw new \RuntimeException('WordPress REST returned inconsistent pagination.');
                }
                $records += $count;
                $bytes += strlen(json_encode($body, JSON_THROW_ON_ERROR));
                if ($records > self::MAX_RECORDS || $bytes > self::MAX_BATCH_BYTES) {
                    throw new \RuntimeException('WordPress REST snapshot exceeds the import limits.');
                }
                foreach ($body as $record) {
                    $id = self::recordId($record['id'] ?? null);
                    if ($id === null || isset($rows[$id])) {
                        throw new \RuntimeException('WordPress REST returned a missing or duplicate collection ID.');
                    }
                    $rows[$id] = $record;
                }
            }
            $collections[$collection] = array_values($rows);
        }

        return ($this->mapper)(substr($baseUrl, 0, -strlen('/wp-json/wp/v2')), $collections, $credentials !== null);
    }

    private static function recordId(mixed $value): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        return is_string($value) && preg_match('/\A[1-9][0-9]{0,19}\z/', $value) === 1 ? $value : null;
    }
}
