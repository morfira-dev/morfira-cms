<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application;

use Cms\Content\Application\Import\WordPressImageIndex;
use PHPUnit\Framework\TestCase;

final class WordPressImageIndexTest extends TestCase
{
    private const string ASSET = '12fb65ca-1726-4d2e-9199-a2546db4a983';

    public function testMatchesAResizedUrlOnTheSourceHostAndReusesAnEarlierAttachmentById(): void
    {
        $index = new WordPressImageIndex(
            [['id' => '12', 'path' => 'image.png', 'url' => 'https://wp.example/uploads/image.png', 'alt' => 'Photo', 'eligible' => true]],
            [['id' => '12', 'state' => 'imported', 'assetId' => self::ASSET]],
            static fn (string $id): ?array => $id === '17' ? ['assetId' => self::ASSET, 'alt' => 'Saved alt'] : null,
        );

        self::assertSame(self::ASSET, $index->resolve('http://wp.example/uploads/image-640x480.png?preview=1', null)['assetId'] ?? null);
        self::assertSame('Photo', $index->resolve('/uploads/image.png', null)['alt'] ?? null);
        self::assertNull($index->resolve('https://other.example/uploads/image.png', null));
        self::assertSame('Saved alt', $index->byId('17')['alt'] ?? null);
    }

    public function testAnUnavailableAttachmentCannotReuseAnAmbiguousUrlOrItsOldMapping(): void
    {
        $attachments = [
            ['id' => '12', 'path' => 'image.png', 'url' => 'https://wp.example/uploads/image.png', 'alt' => 'Photo', 'eligible' => true],
            ['id' => '13', 'path' => 'image.png', 'url' => 'https://wp.example/uploads/image.png', 'alt' => '', 'eligible' => false],
        ];
        $index = new WordPressImageIndex(
            $attachments,
            [['id' => '12', 'state' => 'imported', 'assetId' => self::ASSET]],
            static fn (string $id): array => ['assetId' => self::ASSET, 'alt' => 'Old image'],
        );

        self::assertNull($index->resolve('/uploads/image.png', null));
        self::assertNull($index->byId('13'));
        self::assertSame(self::ASSET, $index->byId('12')['assetId'] ?? null);
    }
}
