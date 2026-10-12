<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Domain;

use Cms\Content\Domain\PageSeo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageSeoTest extends TestCase
{
    #[Test]
    public function it_normalizes_valid_settings(): void
    {
        $seo = PageSeo::fromArray([
            'title' => '  Better CMS  ',
            'description' => ' Fast, friendly and extensible. ',
            'noIndex' => false,
            'openGraphImageAssetId' => '',
        ]);

        self::assertSame('Better CMS', $seo->title);
        self::assertSame('Fast, friendly and extensible.', $seo->description);
        self::assertNull($seo->openGraphImageAssetId);
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('invalidSettings')]
    public function it_rejects_invalid_settings(array $payload): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PageSeo::fromArray($payload);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidSettings(): iterable
    {
        yield 'long title' => [[
            'title' => str_repeat('a', 71),
            'description' => '',
            'noIndex' => false,
            'openGraphImageAssetId' => null,
        ]];
        yield 'invalid media id' => [[
            'title' => '',
            'description' => '',
            'noIndex' => false,
            'openGraphImageAssetId' => 'not-a-uuid',
        ]];
        yield 'wrong noIndex type' => [[
            'title' => '',
            'description' => '',
            'noIndex' => 'false',
            'openGraphImageAssetId' => null,
        ]];
    }
}
