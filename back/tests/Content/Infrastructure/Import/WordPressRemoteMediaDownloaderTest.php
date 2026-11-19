<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Infrastructure\Import;

use Cms\Content\Application\Import\WordPressMediaHosts;
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

    public function testAllowsExplicitCdnHostAndNormalizesDuplicatesWithoutConnecting(): void
    {
        $downloader = new WordPressRemoteMediaDownloader();
        $hosts = WordPressMediaHosts::normalize(['CDN.Example.Org', 'cdn.example.org', 'images.example.org']);
        self::assertSame(['cdn.example.org', 'images.example.org'], $hosts);
        $downloader->assertUrl(self::API, 'https://CDN.Example.Org:443/assets/photo.jpg?width=200', $hosts);
        $downloader->assertUrl(self::API, 'https://example.test/uploads/one.jpg', $hosts);
    }

    #[DataProvider('invalidHosts')]
    public function testRejectsInvalidAllowlistEntries(string $host): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WordPressMediaHosts::normalize([$host]);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidHosts(): iterable
    {
        yield 'wildcard' => ['*.example.org'];
        yield 'URL' => ['https://cdn.example.org'];
        yield 'port' => ['cdn.example.org:443'];
        yield 'IPv4' => ['127.0.0.1'];
        yield 'IPv6' => ['[::1]'];
        yield 'space' => [' cdn.example.org'];
        yield 'trailing dot' => ['cdn.example.org.'];
        yield 'local DNS' => ['example.local'];
        yield 'local suffix' => ['app.localhost'];
        yield 'private DNS' => ['app.home.arpa'];
        yield 'short name' => ['localhost'];
        yield 'comma-separated list' => ['cdn.example.org,assets.example.org'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidCdnUrls')]
    public function testCdnAuthorizationDoesNotAllowSubdomainsOrRelaxUrlPolicy(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WordPressRemoteMediaDownloader()->assertUrl(self::API, $url, ['cdn.example.org']);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidCdnUrls(): iterable
    {
        yield 'subdomain' => ['https://sub.cdn.example.org/images/one.jpg'];
        yield 'deceptive suffix' => ['https://cdn.example.org.other.example.org/images/one.jpg'];
        yield 'insecure scheme' => ['http://cdn.example.org/images/one.jpg'];
        yield 'nonstandard port' => ['https://cdn.example.org:444/images/one.jpg'];
        yield 'credentials' => ['https://user@cdn.example.org/images/one.jpg'];
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
