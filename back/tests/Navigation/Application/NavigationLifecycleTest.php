<?php

declare(strict_types=1);

namespace Cms\Tests\Navigation\Application;

use Cms\Navigation\Application\CreateNavigationMenu\CreateNavigationMenu;
use Cms\Navigation\Application\GetPublishedNavigation\GetPublishedNavigation;
use Cms\Navigation\Application\PublishNavigationMenu\PublishNavigationMenu;
use Cms\Navigation\Application\UpdateNavigationMenu\UpdateNavigationMenu;
use Cms\Navigation\Domain\NavigationVersionConflict;
use Cms\Tests\Navigation\InMemoryNavigationMenuRepository;
use Cms\Tests\Navigation\InMemoryPublishedNavigationRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NavigationLifecycleTest extends TestCase
{
    #[Test]
    public function publishing_is_idempotent_and_delivery_remains_immutable(): void
    {
        $drafts = new InMemoryNavigationMenuRepository();
        $published = new InMemoryPublishedNavigationRepository();
        $created = (new CreateNavigationMenu($drafts))('Main', 'main', [$this->item('Home', '/')]);
        $publish = new PublishNavigationMenu($drafts, $published);
        $first = $publish($created->id, 1);
        $second = $publish($created->id, 1);
        (new UpdateNavigationMenu($drafts))($created->id, 'Main changed', 'main', [$this->item('About', '/about')], 1);
        $delivery = (new GetPublishedNavigation($published))('main');

        self::assertSame($first->snapshotId, $second->snapshotId);
        self::assertNotNull($delivery);
        self::assertSame('Main', $delivery->name);
        self::assertSame('Home', $delivery->items[0]['label']);
    }

    #[Test]
    public function publishing_rejects_a_stale_version(): void
    {
        $drafts = new InMemoryNavigationMenuRepository();
        $created = (new CreateNavigationMenu($drafts))('Main', 'main', []);
        (new UpdateNavigationMenu($drafts))($created->id, 'Changed', 'main', [], 1);
        $this->expectException(NavigationVersionConflict::class);
        (new PublishNavigationMenu($drafts, new InMemoryPublishedNavigationRepository()))($created->id, 1);
    }

    /** @return array<string, mixed> */
    private function item(string $label, string $href): array
    {
        return ['id' => '11111111-1111-4111-8111-111111111111', 'label' => $label, 'href' => $href, 'openInNewTab' => false, 'children' => []];
    }
}
