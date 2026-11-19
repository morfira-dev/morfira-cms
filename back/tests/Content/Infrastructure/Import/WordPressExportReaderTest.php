<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Infrastructure\Import;

use Cms\Content\Infrastructure\Import\WordPressExportReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WordPressExportReaderTest extends TestCase
{
    public function testReadsDirectItemsAndCountsUnsupportedTypes(): void
    {
        $export = $this->readXml(<<<'XML'
            <?xml version="1.0"?>
            <rss version="2.0" xmlns:w="http://wordpress.org/export/1.2/"
                xmlns:c="http://purl.org/rss/1.0/modules/content/">
              <channel>
                <w:wxr_version>1.2</w:wxr_version>
                <unrelated><item><w:post_id>3</w:post_id></item></unrelated>
                <w:item><w:post_id>3</w:post_id></w:item>
                <item>
                  <title><![CDATA[A & B]]></title>
                  <link>https://wordpress.example/?p=7&amp;lang=en</link>
                  <c:encoded><![CDATA[<p>Body &amp; details.</p>]]></c:encoded>
                  <w:post_id>7</w:post_id>
                  <w:post_type>page</w:post_type>
                  <w:status>publish</w:status>
                  <w:post_name>about</w:post_name>
                  <w:post_password><![CDATA[synthetic-fixture-value]]></w:post_password>
                </item>
                <item><w:post_id>8</w:post_id><w:post_type>nav_menu_item</w:post_type></item>
                <item><w:post_id>9</w:post_id><w:post_type>nav_menu_item</w:post_type></item>
                <item><w:post_id>10</w:post_id><w:post_type>post</w:post_type><w:status>draft</w:status></item>
              </channel>
            </rss>
            XML);

        self::assertSame([
            'items' => [
                [
                    'id' => '7',
                    'type' => 'page',
                    'status' => 'publish',
                    'title' => 'A & B',
                    'slug' => 'about',
                    'html' => '<p>Body &amp; details.</p>',
                    'link' => 'https://wordpress.example/?p=7&lang=en',
                    'passwordProtected' => true,
                    'terms' => [],
                ],
                [
                    'id' => '10',
                    'type' => 'post',
                    'status' => 'draft',
                    'title' => '',
                    'slug' => '',
                    'html' => '',
                    'link' => '',
                    'passwordProtected' => false,
                    'terms' => [],
                ],
            ],
            'skippedTypes' => ['nav_menu_item' => 2],
            'attachments' => [],
            'terms' => [],
        ], $export);
    }

    #[DataProvider('invalidExports')]
    public function testRejectsUntrustedAndAmbiguousExports(string $xml, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->readXml($xml);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidExports(): iterable
    {
        $start = '<rss xmlns:w="http://wordpress.org/export/1.2/"><channel><w:wxr_version>1.2</w:wxr_version>';
        $end = '</channel></rss>';
        $item = '<item><w:post_id>1</w:post_id><w:post_type>post</w:post_type></item>';

        yield 'non-WXR RSS' => ['<rss><channel/></rss>', 'supported WXR version'];
        yield 'truncated XML' => [$start, 'invalid XML'];
        yield 'external DTD' => ['<!DOCTYPE rss SYSTEM "https://invalid.example/wxr.dtd">' . $start . $end, 'document type declaration'];
        yield 'entity declaration' => ['<!DOCTYPE rss [<!ENTITY sample "anything">]>' . $start . $end, 'document type declaration'];
        yield 'duplicate identifiers' => [$start . $item . $item . $end, 'invalid or duplicate item identifier'];
        yield 'invalid identifier' => [$start . '<item><w:post_id>0</w:post_id></item>' . $end, 'invalid or duplicate item identifier'];
        yield 'duplicate XML fields' => [$start . '<item><w:post_id>1</w:post_id><w:post_id>2</w:post_id></item>' . $end, 'duplicate XML field'];
        yield 'item count' => [$start . implode('', array_map(static fn (int $i): string => '<item><w:post_id>' . $i . '</w:post_id></item>', range(1, 5001))) . $end, 'more than 5,000 items'];
        yield 'file size' => [str_repeat('x', 8 * 1024 * 1024 + 1), '8 MiB limit'];
    }

    public function testRejectsUrlAsInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Provide a local WordPress export file.');

        new WordPressExportReader()->read('https://invalid.example/site.xml');
    }

    public function testReadsMediaAndTaxonomyFromTheMigrationFixture(): void
    {
        $export = new WordPressExportReader()->read(dirname(__DIR__, 2).'/Fixtures/wordpress-import.xml');

        self::assertSame('2026/11/sample.png', $export['attachments'][0]['path']);
        self::assertSame('Sample alt', $export['attachments'][0]['alt']);
        self::assertSame('', $export['attachments'][1]['path']);
        self::assertSame(['taxonomy' => 'category', 'slug' => 'updates', 'name' => 'Updates', 'parent' => 'news'], $export['terms'][0]);
        self::assertSame(['taxonomy' => 'post_tag', 'slug' => 'release', 'name' => 'Release', 'parent' => ''], $export['terms'][2]);
        self::assertCount(2, $export['items'][1]['terms']);
    }

    public function testAttachmentOfExportedPrivatePostIsIneligible(): void
    {
        $export = $this->readXml(<<<'XML'
            <rss xmlns:wp="http://wordpress.org/export/1.2/"><channel>
              <wp:wxr_version>1.2</wp:wxr_version>
              <item><wp:post_id>2</wp:post_id><wp:post_type>attachment</wp:post_type><wp:status>inherit</wp:status><wp:post_parent>3</wp:post_parent></item>
              <item><wp:post_id>3</wp:post_id><wp:post_type>post</wp:post_type><wp:status>private</wp:status></item>
            </channel></rss>
            XML);

        self::assertFalse($export['attachments'][0]['eligible']);
    }

    /**
     * @return array{items:list<array<string,mixed>>,skippedTypes:array<string,int>,attachments:list<array<string,mixed>>,terms:list<array<string,mixed>>}
     */
    private function readXml(string $xml): array
    {
        $file = tmpfile();
        self::assertIsResource($file);

        try {
            self::assertSame(strlen($xml), fwrite($file, $xml));

            $path = stream_get_meta_data($file)['uri'] ?? null;
            self::assertIsString($path);

            return new WordPressExportReader()->read($path);
        } finally {
            fclose($file);
        }
    }
}
