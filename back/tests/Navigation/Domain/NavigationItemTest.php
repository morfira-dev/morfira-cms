<?php

declare(strict_types=1);

namespace Cms\Tests\Navigation\Domain;

use Cms\Navigation\Domain\NavigationItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NavigationItemTest extends TestCase
{
    #[Test]
    public function it_preserves_an_ordered_three_level_tree(): void
    {
        $items = NavigationItem::listFromArray([$this->item('One', '/', [$this->item('Two', '/two', [$this->item('Three', 'https://example.com/three')])])]);
        self::assertSame('Three', $items[0]->children[0]->children[0]->label);
    }

    #[Test]
    #[DataProvider('unsafeHrefs')]
    public function it_rejects_unsafe_hrefs(string $href): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NavigationItem::listFromArray([$this->item('Unsafe', $href)]);
    }

    /** @return iterable<string, array{string}> */
    public static function unsafeHrefs(): iterable
    {
        yield 'protocol relative' => ['//evil.example/path'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'non-http scheme' => ['data:text/html,test'];
        yield 'relative without root' => ['about'];
    }

    #[Test]
    public function it_rejects_a_fourth_level(): void
    {
        $this->expectExceptionMessage('must not exceed 3 levels');
        NavigationItem::listFromArray([$this->item('1', '/', [$this->item('2', '/2', [$this->item('3', '/3', [$this->item('4', '/4')])])])]);
    }

    #[Test]
    public function it_rejects_more_than_one_hundred_items_across_the_tree(): void
    {
        $items = [];
        for ($index = 0; $index < 101; ++$index) { $items[] = $this->item((string) $index, '/' . $index); }
        $this->expectExceptionMessage('more than 100 items');
        NavigationItem::listFromArray($items);
    }

    /** @param list<array<string, mixed>> $children
     *  @return array<string, mixed>
     */
    private function item(string $label, string $href, array $children = []): array
    {
        static $sequence = 0;
        self::assertIsInt($sequence);
        ++$sequence;
        return ['id' => sprintf('00000000-0000-4000-8000-%012d', $sequence), 'label' => $label, 'href' => $href, 'openInNewTab' => false, 'children' => $children];
    }
}
