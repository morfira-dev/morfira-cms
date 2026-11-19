<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Infrastructure\Import;

use Cms\Content\Infrastructure\Import\WordPressRestEndpoint;
use Cms\Content\Infrastructure\Import\WordPressRestTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WordPressRestTransportTest extends TestCase
{
    public function testNormalizesStandardAndSubdirectoryEndpoints(): void
    {
        self::assertSame('https://example.test/wp-json/wp/v2', WordPressRestTransport::normalizeBaseUrl('https://Example.Test:443/wp-json/wp/v2/'));
        self::assertSame('https://example.test/Blog/wp-json/wp/v2', WordPressRestTransport::normalizeBaseUrl('https://example.test/Blog/wp-json/wp/v2'));
    }

    #[DataProvider('invalidEndpoints')]
    public function testRejectsNoncanonicalEndpointsWithoutConnecting(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WordPressRestTransport::normalizeBaseUrl($url);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidEndpoints(): iterable
    {
        yield 'unsecured' => ['http://example.test/wp-json/wp/v2'];
        yield 'loopback literal' => ['https://127.0.0.1/wp-json/wp/v2'];
        yield 'IPv6 literal' => ['https://[::1]/wp-json/wp/v2'];
        yield 'mixed numeric host' => ['https://0x7f.0.0.1/wp-json/wp/v2'];
        yield 'userinfo' => ['https://name:password@example.test/wp-json/wp/v2'];
        yield 'non-default port' => ['https://example.test:8443/wp-json/wp/v2'];
        yield 'raw query' => ['https://example.test/wp-json/wp/v2?x=1'];
        yield 'fragment' => ['https://example.test/wp-json/wp/v2#secret'];
        yield 'dot path' => ['https://example.test/../wp-json/wp/v2'];
        yield 'encoded dot path' => ['https://example.test/%2e%2e/wp-json/wp/v2'];
        yield 'authority separator' => ['https://example.test\example.org/wp-json/wp/v2'];
        yield 'different namespace' => ['https://example.test/wp-json/custom/v2'];
    }

    public function testRejectsUnrecognizedCollectionBeforeResolvingTheHost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WordPressRestTransport()->get('https://example.test/wp-json/wp/v2', '../other', []);
    }

    public function testAcceptsPublicUnicastFromBothAddressFamilies(): void
    {
        $addresses = ['1.1.1.1', '2606:4700:4700::1111'];
        self::assertSame($addresses, WordPressRestEndpoint::publicAddresses($addresses));
    }

    #[DataProvider('nonpublicAddresses')]
    public function testRejectsAResolutionIfAnyAddressIsNotPublic(string $address): void
    {
        $this->expectException(\RuntimeException::class);
        WordPressRestEndpoint::publicAddresses(['1.1.1.1', $address]);
    }

    /** @return iterable<string,array{string}> */
    public static function nonpublicAddresses(): iterable
    {
        yield 'loopback' => ['127.0.0.1'];
        yield 'link-local' => ['169.254.169.254'];
        yield 'private' => ['10.1.2.3'];
        yield 'carrier shared' => ['100.64.1.2'];
        yield 'documentation' => ['192.0.2.1'];
        yield 'multicast' => ['224.0.0.1'];
        yield 'IPv6 mapped' => ['::ffff:127.0.0.1'];
        yield 'IPv6 loopback' => ['::1'];
        yield 'IPv6 unique local' => ['fd00::1'];
        yield 'IPv6 link-local' => ['fe80::1'];
        yield 'IPv6 multicast' => ['ff02::1'];
        yield 'NAT64' => ['64:ff9b::7f00:1'];
        yield 'IPv6 6to4' => ['2002:7f00:1::1'];
        yield 'IPv6 documentation' => ['3fff::1'];
    }
}
