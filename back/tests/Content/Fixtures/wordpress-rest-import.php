<?php

declare(strict_types=1);

use Cms\Content\Application\Import\WordPressRemoteMedia;
use Cms\Content\Application\Import\WordPressRemoteMediaFailure;
use Cms\Content\Infrastructure\Import\WordPressRemoteMediaDownloader;
use Cms\Content\Application\Import\WordPressRestCredentials;
use Cms\Content\Infrastructure\Import\WordPressRestTransport;
use Cms\Content\Infrastructure\Import\WordPressRestJsonTransport;
use Cms\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\DependencyInjection\ContainerInterface;

require dirname(__DIR__, 3).'/vendor/autoload.php';

function restFixtureAttachmentUrl(string $file): string
{
    $host = getenv('CMS_REST_FIXTURE_MEDIA') === 'transient-failure' || str_starts_with($file, 'restricted-')
        ? 'wordpress.example'
        : 'cdn.wordpress.example';

    return 'https://'.$host.'/wp-content/uploads/2026/11/'.$file;
}

$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
if (!$container instanceof ContainerInterface) {
    throw new RuntimeException('The fixture requires the test service container.');
}
$container->set(WordPressRestTransport::class, new class implements WordPressRestJsonTransport {
    /** @param array<string,int|string> $query */
    public function get(string $baseUrl, string $resource, array $query, ?WordPressRestCredentials $credentials = null): array
    {
        if ($baseUrl !== 'https://wordpress.example/wp-json/wp/v2' || $query['page'] !== 1) {
            throw new RuntimeException('Unexpected fixture request.');
        }
        $mode = getenv('CMS_REST_FIXTURE_AUTH');
        if (in_array($mode, ['private', 'denied'], true)) {
            return $this->privateCollection($resource, $query, $credentials, $mode === 'denied');
        }
        if ($query['context'] !== 'view' || $credentials !== null) {
            throw new RuntimeException('The public fixture requires anonymous view requests.');
        }
        $records = match ($resource) {
            'posts' => [[
                'id' => 92001,
                'status' => 'publish',
                'slug' => 'imported-rest-post',
                'title' => ['rendered' => getenv('CMS_REST_FIXTURE_EDITED') === '1' ? 'Edited REST post' : 'Imported REST post'],
                'content' => [
                    'rendered' => '<p>REST body</p><img class="wp-image-92004" src="'.restFixtureAttachmentUrl('rest-fixture.png').'" alt="Inline REST image">',
                    'protected' => false,
                ],
                'link' => 'https://wordpress.example/blog/imported-rest-post/',
                'author' => 92002,
                'categories' => [92003],
                'featured_media' => 92004,
            ]],
            'media' => $this->attachments(),
            'users' => [['id' => 92002, 'slug' => 'fixture-author', 'name' => 'REST Fixture Author']],
            'categories' => [['id' => 92003, 'slug' => 'rest-fixture-category', 'name' => 'REST Fixture Category', 'parent' => 0]],
            'pages', 'tags' => [],
            default => throw new RuntimeException('Unknown fixture collection.'),
        };

        return ['body' => $records, 'totalPages' => $records === [] ? 0 : 1];
    }

    /**
     * @param array<string,int|string> $query
     * @return array{body:list<array<string,mixed>>,totalPages:int}
     */
    private function privateCollection(string $resource, array $query, ?WordPressRestCredentials $credentials, bool $denyPage): array
    {
        $content = in_array($resource, ['posts', 'pages'], true);
        $editMedia = $resource === 'media' && ($query['context'] ?? null) === 'edit';
        if ($content || $editMedia) {
            if ($credentials?->username !== 'fixture-wp-editor' || $credentials->applicationPassword() !== 'fixtureApplicationPassword'
                || ($query['context'] ?? null) !== 'edit' || ($query['status'] ?? null) !== ($editMedia ? 'inherit,private' : 'publish,private,draft,pending,future')) {
                throw new RuntimeException('Expected scoped WordPress edit credentials.');
            }
        } elseif ($credentials !== null || ($query['context'] ?? null) !== 'view') {
            throw new RuntimeException('Non-content collections must stay anonymous.');
        }

        if ($denyPage && $resource === 'pages') {
            throw new RuntimeException('WordPress refused access to the fixture pages collection.');
        }
        $edited = getenv('CMS_REST_FIXTURE_EDITED') === '1';
        $rows = match ($resource) {
            'posts' => [[
                'id' => 93101, 'status' => 'private', 'slug' => 'restricted-rest-post',
                'title' => ['raw' => $edited ? 'Edited private draft' : 'Imported private post'],
                'content' => ['raw' => '<p>Restricted fixture post body</p><img class="wp-image-93103" src="'.restFixtureAttachmentUrl('restricted-image.png').'">', 'protected' => false],
                'link' => 'https://wordpress.example/old/private-post/', 'featured_media' => 93103,
            ]],
            'pages' => [[
                'id' => 93102, 'status' => 'publish', 'slug' => 'restricted-rest-page',
                'title' => ['raw' => 'Imported protected page'],
                'content' => ['raw' => '<p>Restricted fixture page body</p>', 'protected' => true],
                'link' => 'https://wordpress.example/old/protected-page/', 'featured_media' => 93103,
                'password' => 'synthetic-post-password',
            ]],
            'media' => !$editMedia ? [] : [[
                'id' => 93103, 'source_url' => restFixtureAttachmentUrl('restricted-image.png'),
                'media_type' => 'image', 'mime_type' => 'image/png', 'status' => 'inherit', 'post' => 93101,
            ]],
            default => [],
        };
        if ($editMedia && getenv('CMS_REST_FIXTURE_MEDIA') === 'private-failure') {
            $rows[] = [
                'id' => 93104, 'source_url' => restFixtureAttachmentUrl('restricted-failure.png'),
                'media_type' => 'image', 'mime_type' => 'image/png', 'status' => 'private', 'post' => 93101,
            ];
        }

        return ['body' => $rows, 'totalPages' => $rows === [] ? 0 : 1];
    }

    /** @return list<array<string,mixed>> */
    private function attachments(): array
    {
        $files = [92004 => 'rest-fixture.png'];
        if (getenv('CMS_REST_FIXTURE_MEDIA') === 'transient-failure') {
            $files[92005] = 'rest-fixture-failure.png';
        }

        $records = [];
        foreach ($files as $id => $file) {
            $records[] = [
                'id' => $id,
                'source_url' => restFixtureAttachmentUrl($file),
                'media_type' => 'image',
                'mime_type' => 'image/png',
                'media_details' => ['file' => '2026/11/'.$file],
                'alt_text' => 'REST fixture image',
                'status' => 'inherit',
                'post' => 92001,
            ];
        }

        return $records;
    }
});
$container->set(WordPressRemoteMediaDownloader::class, new class implements WordPressRemoteMedia {
    /** @param list<string> $allowedHosts */
    public function assertUrl(string $apiUrl, string $attachmentUrl, array $allowedHosts = [], ?WordPressRestCredentials $credentials = null): void
    {
        if ($apiUrl !== 'https://wordpress.example/wp-json/wp/v2' || !in_array($attachmentUrl, [
            restFixtureAttachmentUrl('rest-fixture.png'),
            restFixtureAttachmentUrl('rest-fixture-failure.png'),
            restFixtureAttachmentUrl('restricted-image.png'),
            restFixtureAttachmentUrl('restricted-failure.png'),
        ], true)) {
            throw new RuntimeException('Unexpected fixture media URL.');
        }
        if (str_starts_with(basename($attachmentUrl), 'restricted-')) {
            if ($credentials?->username !== 'fixture-wp-editor' || $credentials->applicationPassword() !== 'fixtureApplicationPassword') {
                throw new RuntimeException('Expected same-host credentials for restricted fixture media.');
            }
        } elseif ($credentials !== null) {
            throw new RuntimeException('Credentials must not accompany a public fixture media request.');
        }
        new WordPressRemoteMediaDownloader()->assertUrl($apiUrl, $attachmentUrl, $allowedHosts, $credentials);
    }

    /**
     * @template T
     * @param Closure(string,string):T $consume
     * @param list<string> $allowedHosts
     * @return T
     */
    public function download(string $apiUrl, string $attachmentUrl, Closure $consume, array $allowedHosts = [], ?WordPressRestCredentials $credentials = null): mixed
    {
        $this->assertUrl($apiUrl, $attachmentUrl, $allowedHosts, $credentials);
        $mode = getenv('CMS_REST_FIXTURE_MEDIA');
        $fileName = basename($attachmentUrl);
        if ($mode === 'reject-download' || in_array($fileName, ['rest-fixture-failure.png', 'restricted-failure.png'], true)) {
            throw new WordPressRemoteMediaFailure('Fixture media download failed.');
        }

        $file = tmpfile();
        if ($file === false) {
            throw new RuntimeException('Could not open fixture image storage.');
        }

        try {
            $path = stream_get_meta_data($file)['uri'] ?? null;
            $image = imagecreatetruecolor(64, 48);
            if (!is_string($path) || $image === false || !imagepng($image, $file)) {
                throw new RuntimeException('Could not stage the fixture image.');
            }
            fflush($file);

            return $consume($path, $fileName);
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
        }
    }
});

$application = new Application($kernel);
$application->setDefaultCommand('cms:import:wordpress', true);
exit($application->run(new ArgvInput()));
