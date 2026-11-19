<?php

declare(strict_types=1);

namespace Cms\Tests\Publishing\Application;

use Cms\Publishing\Application\RegisterImportedRedirect;
use Cms\Publishing\Application\ResolveImportedRedirect;
use Cms\Publishing\Domain\ImportedRedirectRepository;
use Cms\Publishing\Domain\ImportedRedirectSource;
use Cms\Publishing\Domain\PublicationTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImportedRedirectTest extends TestCase
{
    private const string TARGET_ID = '11111111-1111-4111-8111-111111111111';

    #[DataProvider('sourceUrls')]
    public function testSourceNormalization(string $url, ?string $source): void
    {
        self::assertSame($source, ImportedRedirectSource::fromUrl($url));
    }

    /** @return iterable<string, array{string,?string}> */
    public static function sourceUrls(): iterable
    {
        yield 'nested permalink' => ['https://wp.example/2024/07/original/', '/2024/07/original'];
        yield 'ignore tracking on permalinks' => ['https://wp.example/old/?utm_source=example', '/old'];
        yield 'decode unicode once' => ['https://wp.example/%D1%82%D0%B5%D1%81%D1%82/', '/тест'];
        yield 'plain post' => ['https://wp.example/?p=32', '/?p=32'];
        yield 'plain page' => ['/?page_id=43', '/?page_id=43'];
        yield 'native home' => ['https://wp.example/', null];
        yield 'mixed root params' => ['https://wp.example/?p=32&foo=bar', null];
        yield 'dot segment' => ['https://wp.example/old/%2e%2e/new', null];
        yield 'encoded separator' => ['https://wp.example/old%2Fnew', null];
        yield 'double-encoded separator' => ['https://wp.example/old%252Fnew', null];
        yield 'scheme relative' => ['//remote.example/old', null];
        yield 'auth syntax' => ['https://operator@wp.example/old', null];
        yield 'unsupported scheme' => ['ftp://wp.example/old', null];
        yield 'reserved path' => ['https://wp.example/api/old', null];
        yield 'encoded reserved path' => ['https://wp.example/%61dmin/old', null];
        yield 'protocol-relative request path' => ['https://wp.example//remote.example/old', null];
        yield 'too long' => ['https://wp.example/'.str_repeat('x', 512), null];
    }

    public function testDryRunChecksOwnershipWithoutWriting(): void
    {
        $repo = $this->createMock(ImportedRedirectRepository::class);
        $repo->expects(self::once())->method('owner')->with('/previous')->willReturn(null);
        $repo->expects(self::never())->method('register');

        self::assertSame('planned', new RegisterImportedRedirect($repo)('https://wp.example/previous/', 'page', null));
    }

    public function testSameOwnerReplayAndConflictingOwnerLeaveExistingRegistrationIntact(): void
    {
        $repo = $this->createMock(ImportedRedirectRepository::class);
        $repo->expects(self::exactly(2))->method('owner')->willReturn(['type' => PublicationTarget::Page, 'id' => self::TARGET_ID]);
        $repo->expects(self::never())->method('register');
        $register = new RegisterImportedRedirect($repo);

        self::assertSame('already_registered', $register('/old', 'page', self::TARGET_ID));
        self::assertSame('conflict', $register('/old', 'entry', self::TARGET_ID));
    }

    public function testResolvedTargetFollowsAnActiveRouteAndAvoidsSelfRedirects(): void
    {
        $repo = $this->createMock(ImportedRedirectRepository::class);
        $repo->expects(self::exactly(5))->method('activeTargetPath')->with('/old')
            ->willReturnOnConsecutiveCalls('/new', '/newer', '/old', '/old', null);
        $resolve = new ResolveImportedRedirect($repo);

        self::assertSame('/new', $resolve('/old/', null));
        self::assertSame('/newer', $resolve('/old', null));
        self::assertSame('/old', $resolve('/old/', null));
        self::assertNull($resolve('/old', null));
        self::assertNull($resolve('/old', null));
    }
}
