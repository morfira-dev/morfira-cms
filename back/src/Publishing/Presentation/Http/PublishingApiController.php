<?php

declare(strict_types=1);

namespace Cms\Publishing\Presentation\Http;

use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageVersionConflict;
use Cms\Publishing\Application\GetPublishedPage\GetPublishedPage;
use Cms\Publishing\Application\GetPublishedRedirect\GetPublishedRedirect;
use Cms\Publishing\Application\ListPageRedirects\ListPageRedirects;
use Cms\Publishing\Application\PublishPage\PublishPage;
use Cms\Publishing\Application\AddPageRedirect;
use Cms\Publishing\Application\RemovePageRedirect;
use Cms\Publishing\Domain\PublishedPageNotActive;
use Cms\Publishing\Domain\PublishedSlugAlreadyExists;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class PublishingApiController
{
    public function __construct(
        private PublishPage $publishPage,
        private GetPublishedPage $getPublishedPage,
        private GetPublishedRedirect $getPublishedRedirect,
        private ListPageRedirects $listPageRedirects,
        private AddPageRedirect $addPageRedirect,
        private RemovePageRedirect $removePageRedirect,
    ) {
    }

    #[Route('/api/v1/admin/pages/{id}/publish', name: 'publishing.pages.publish', methods: ['POST'])]
    public function publish(string $id, Request $request): JsonResponse
    {
        $version = $request->toArray()['version'] ?? null;

        if (!is_int($version)) {
            return new JsonResponse(['error' => 'The current integer version is required.'], 422);
        }

        try {
            $published = ($this->publishPage)($id, $version);
        } catch (PageNotFound) {
            return new JsonResponse(['error' => 'Page not found.'], 404);
        } catch (PageVersionConflict|PublishedSlugAlreadyExists $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }

        return new JsonResponse($published->toArray());
    }

    #[Route('/api/v1/content/pages/{slug}', name: 'publishing.pages.api', methods: ['GET'])]
    public function get(string $slug, Request $request): Response
    {
        return $this->getByPath($slug, $request);
    }

    #[Route('/api/v1/content/pages/{locale}/{slug}', name: 'publishing.pages.localized_api', requirements: ['locale' => '[a-z]{2,3}(?:-[A-Z]{2})?', 'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function getLocalized(string $locale, string $slug, Request $request): Response
    {
        return $this->getByPath($locale . '/' . $slug, $request);
    }

    private function getByPath(string $path, Request $request): Response
    {
        $published = ($this->getPublishedPage)($path);

        if ($published === null) {
            $redirectTarget = ($this->getPublishedRedirect)($path);
            if ($redirectTarget !== null) {
                $response = new RedirectResponse('/api/v1/content/pages' . $redirectTarget->url, 301);
                $response->setPublic();
                $response->setMaxAge(300);

                return $response;
            }

            return new JsonResponse(['error' => 'Published page not found.'], 404);
        }

        $response = new JsonResponse($published->toArray());
        $response->setPublic();
        $response->setMaxAge(60);
        $response->setEtag($published->snapshotId);
        $response->isNotModified($request);

        return $response;
    }

    #[Route('/api/v1/admin/pages/{id}/redirects', name: 'publishing.pages.redirects', methods: ['GET'])]
    public function redirects(string $id): JsonResponse
    {
        try {
            $pageId = PageId::fromString($id);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }

        return new JsonResponse(['items' => ($this->listPageRedirects)($pageId->value)]);
    }

    #[Route('/api/v1/admin/pages/{id}/redirects', name: 'publishing.pages.redirects.create', methods: ['POST'])]
    public function addRedirect(string $id,Request $request):JsonResponse
    {
        $payload=$request->toArray();$sourceSlug=$payload['sourceSlug']??null;
        if(array_diff(array_keys($payload),['sourceSlug'])!==[]||!is_string($sourceSlug))return new JsonResponse(['error'=>'sourceSlug must be a string.'],422);
        try{return new JsonResponse(($this->addPageRedirect)($id,$sourceSlug),201);}catch(PageNotFound){return new JsonResponse(['error'=>'Page not found.'],404);}catch(PublishedPageNotActive $e){return new JsonResponse(['error'=>$e->getMessage()],409);}catch(PublishedSlugAlreadyExists $e){return new JsonResponse(['error'=>$e->getMessage()],409);}catch(\InvalidArgumentException $e){return new JsonResponse(['error'=>$e->getMessage()],422);}
    }

    #[Route('/api/v1/admin/pages/{id}/redirects/{sourceSlug}', name: 'publishing.pages.redirects.delete', methods: ['DELETE'])]
    public function removeRedirect(string $id,string $sourceSlug):Response
    {
        try{($this->removePageRedirect)($id,$sourceSlug);}catch(PageNotFound){return new JsonResponse(['error'=>'Page not found.'],404);}catch(\InvalidArgumentException $e){return new JsonResponse(['error'=>$e->getMessage()],422);}
        return new Response(status:204);
    }
}
