<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Infrastructure\Import;

use Cms\Content\Infrastructure\Import\WordPressRemoteMediaDownloader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WordPressRemoteMediaDownloaderTest extends TestCase
{
    private const string API = 'https://example.test/blog/wp-json/wp/v2';

    public function testAcceptsSameHostPercentEncodedFilenamesAndQueryWithoutConnecting(): void
    {
        $downloader = new WordPressRemoteMediaDownloader();
        $downloader->assertUrl(self::API, 'https://Example.Test:443/custom-uploads/2026/01/photo%20one.jpg?v=2');
        $downloader->assertUrl(self::API, 'https://example.test/custom-uploads/%D1%84%D0%BE%D1%82%D0%BE.jpg');

        self::addToAssertionCount(2);
    }

    #[DataProvider('invalidAttachments')]
    public function testRejectsUnsuitableAttachmentUrlsWithoutConnecting(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WordPressRemoteMediaDownloader()->assertUrl(self::API, $url);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidAttachments(): iterable
    {
        yield 'unsecured' => ['http://example.test/uploads/one.jpg'];
        yield 'other host' => ['https://example.org/uploads/one.jpg'];
        yield 'deceptive host' => ['https://example.test.example.org/uploads/one.jpg'];
        yield 'nonstandard port' => ['https://example.test:444/uploads/one.jpg'];
        yield 'credentials' => ['https://name:password@example.test/uploads/one.jpg'];
        yield 'fragment' => ['https://example.test/uploads/one.jpg#page'];
        yield 'literal control' => ["https://example.test/uploads/one.jpg\r"];
        yield 'encoded control' => ['https://example.test/uploads/one%0a.jpg'];
        yield 'encoded slash' => ['https://example.test/uploads/a%2fone.jpg'];
        yield 'encoded backslash' => ['https://example.test/uploads/a%5cone.jpg'];
        yield 'encoded dot path' => ['https://example.test/uploads/%2e%2e/one.jpg'];
        yield 'incomplete escape' => ['https://example.test/uploads/one%.jpg'];
        yield 'missing filename' => ['https://example.test/uploads/'];
    }

    public function testDownloadRevalidatesTheUrlBeforeInvokingTheConsumer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WordPressRemoteMediaDownloader()->download(
            self::API,
            'https://example.org/uploads/one.jpg',
            static fn (): never => self::fail('The consumer must not be called for an invalid URL.'),
        );
    }
}
