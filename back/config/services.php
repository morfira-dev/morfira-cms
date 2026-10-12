<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Infrastructure\Persistence\DoctrinePageRepository;
use Cms\Publishing\Domain\PublishedPageRepository;
use Cms\Publishing\Infrastructure\Persistence\DoctrinePublishedPageRepository;
use Cms\Identity\Application\PasswordHasher;
use Cms\Identity\Domain\UserRepository;
use Cms\Identity\Infrastructure\Persistence\DoctrineUserRepository;
use Cms\Identity\Infrastructure\Security\SymfonyPasswordHasher;
use Cms\Media\Application\ImageProcessor;
use Cms\Media\Domain\MediaAssetRepository;
use Cms\Media\Infrastructure\Image\LocalGdImageProcessor;
use Cms\Media\Infrastructure\Persistence\DoctrineMediaAssetRepository;
use Cms\Navigation\Domain\NavigationMenuRepository;
use Cms\Navigation\Domain\PublishedNavigationRepository;
use Cms\Navigation\Infrastructure\Persistence\DoctrineNavigationMenuRepository;
use Cms\Navigation\Infrastructure\Persistence\DoctrinePublishedNavigationRepository;
use Cms\Schema\Domain\ContentTypeRepository;
use Cms\Schema\Infrastructure\Persistence\DoctrineContentTypeRepository;
use Cms\Content\Domain\EntryRepository;
use Cms\Content\Infrastructure\Persistence\DoctrineEntryRepository;
use Cms\Publishing\Domain\PublishedEntryRepository;
use Cms\Publishing\Infrastructure\Persistence\DoctrinePublishedEntryRepository;
use Cms\Publishing\Domain\PublicationScheduleRepository;
use Cms\Publishing\Infrastructure\Persistence\DoctrinePublicationScheduleRepository;
use Cms\Taxonomy\Domain\TaxonomyRepository;
use Cms\Taxonomy\Infrastructure\Persistence\DoctrineTaxonomyRepository;
use Cms\Schema\Application\RelationTargetLookup;
use Cms\Content\Infrastructure\Persistence\DoctrineRelationTargetLookup;
use Cms\Content\Application\ContentDeletionGateway;
use Cms\Content\Application\ContentMediaUsageLookup;
use Cms\Content\Application\RichTextSanitizer;
use Cms\Content\Infrastructure\Persistence\DoctrineContentDeletionGateway;
use Cms\Content\Infrastructure\Html\SymfonyRichTextSanitizer;
use Cms\Settings\Domain\SiteSettingsRepository;
use Cms\Settings\Infrastructure\Persistence\DoctrineSiteSettingsRepository;
use Cms\Content\Infrastructure\Persistence\DoctrineContentMediaUsageLookup;
use Cms\Publishing\Application\PublishedMediaUsageLookup;
use Cms\Publishing\Infrastructure\Persistence\DoctrinePublishedMediaUsageLookup;
use Cms\Extensions\Application\ExtensionCatalog;
use Cms\Extensions\Application\PagePublishedHookBus;
use Cms\Extensions\Infrastructure\CoreExtension;
use Cms\Extensions\Infrastructure\RegisteredExtensionCatalog;
use Cms\Extensions\Infrastructure\SynchronousPagePublishedHookBus;
use Cms\Theme\Application\ThemeCatalog;
use Cms\Theme\Infrastructure\BuiltInThemeCatalog;
use Cms\Content\Domain\ReusableSectionRepository;
use Cms\Content\Infrastructure\Persistence\DoctrineReusableSectionRepository;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services
        ->load('Cms\\', '../src/')
        ->exclude([
            '../src/Kernel.php',
            '../src/**/Domain/',
        ]);

    $services->alias(PageRepository::class, DoctrinePageRepository::class);
    $services->alias(PublishedPageRepository::class, DoctrinePublishedPageRepository::class);
    $services->alias(UserRepository::class, DoctrineUserRepository::class);
    $services->alias(PasswordHasher::class, SymfonyPasswordHasher::class);
    $services->alias(MediaAssetRepository::class, DoctrineMediaAssetRepository::class);
    $services->alias(ImageProcessor::class, LocalGdImageProcessor::class);
    $services->alias(NavigationMenuRepository::class, DoctrineNavigationMenuRepository::class);
    $services->alias(PublishedNavigationRepository::class, DoctrinePublishedNavigationRepository::class);
    $services->alias(ContentTypeRepository::class, DoctrineContentTypeRepository::class);
    $services->alias(EntryRepository::class, DoctrineEntryRepository::class);
    $services->alias(PublishedEntryRepository::class, DoctrinePublishedEntryRepository::class);
    $services->alias(PublicationScheduleRepository::class, DoctrinePublicationScheduleRepository::class);
    $services->alias(TaxonomyRepository::class, DoctrineTaxonomyRepository::class);
    $services->alias(RelationTargetLookup::class, DoctrineRelationTargetLookup::class);
    $services->alias(ContentDeletionGateway::class, DoctrineContentDeletionGateway::class);
    $services->alias(RichTextSanitizer::class, SymfonyRichTextSanitizer::class);
    $services->alias(SiteSettingsRepository::class, DoctrineSiteSettingsRepository::class);
    $services->alias(ContentMediaUsageLookup::class, DoctrineContentMediaUsageLookup::class);
    $services->alias(PublishedMediaUsageLookup::class, DoctrinePublishedMediaUsageLookup::class);
    $services->alias(ExtensionCatalog::class, RegisteredExtensionCatalog::class);
    $services->alias(PagePublishedHookBus::class, SynchronousPagePublishedHookBus::class);
    $services->alias(ThemeCatalog::class, BuiltInThemeCatalog::class);
    $services->alias(ReusableSectionRepository::class, DoctrineReusableSectionRepository::class);
    $services->set(RegisteredExtensionCatalog::class)
        ->arg('$providers', tagged_iterator('cms.extension'));
    $services->set(SynchronousPagePublishedHookBus::class)
        ->arg('$subscribers', tagged_iterator('cms.extension.page_published'));
    $services->set(CoreExtension::class)
        ->tag('cms.extension')
        ->tag('cms.extension.page_published');
};
