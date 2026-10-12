<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Domain;

use Cms\Content\Domain\PageBlock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageBlockTest extends TestCase
{
    #[Test]
    public function it_accepts_an_image_reference_with_accessible_text(): void
    {
        $block = PageBlock::fromArray([
            'id' => 'cf49b538-07ee-4b53-a6a3-6b25cbfa22ae',
            'type' => 'image',
            'data' => [
                'assetId' => '61ee498b-f19c-43a4-9961-aa32c9da3ec2',
                'alt' => 'A blue editorial illustration',
                'caption' => 'Reusable media asset',
            ],
        ]);

        self::assertSame('image', $block->type);
        self::assertSame('61ee498b-f19c-43a4-9961-aa32c9da3ec2', $block->data['assetId']);
        self::assertSame('A blue editorial illustration', $block->data['alt']);
    }

    /** @param array<string, string> $data */
    #[Test]
    #[DataProvider('invalidImageData')]
    public function it_rejects_an_invalid_image_reference(array $data): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PageBlock::fromArray([
            'id' => 'cf49b538-07ee-4b53-a6a3-6b25cbfa22ae',
            'type' => 'image',
            'data' => $data,
        ]);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidImageData(): iterable
    {
        yield 'unknown asset id' => [[
            'assetId' => 'not-an-asset-id',
            'alt' => 'A description',
        ]];
        yield 'missing alt' => [[
            'assetId' => '61ee498b-f19c-43a4-9961-aa32c9da3ec2',
            'alt' => '',
        ]];
    }
}
