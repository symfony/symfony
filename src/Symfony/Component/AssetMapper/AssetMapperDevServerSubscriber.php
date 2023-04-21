<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Functions like a controller that returns assets from the asset mapper.
 *
 * @experimental
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class AssetMapperDevServerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $pathInfo = $event->getRequest()->getPathInfo();
        if (!str_starts_with($pathInfo, $this->assetMapper->getPublicPrefix())) {
            return;
        }

        [$assetPath, $digest] = $this->extractAssetPathAndDigest($pathInfo);
        $asset = $this->assetMapper->getAsset($assetPath);

        if (!$asset) {
            throw new NotFoundHttpException(sprintf('Asset "%s" not found.', $assetPath));
        }

        if ($asset->getDigest() !== $digest) {
            throw new NotFoundHttpException(sprintf('Asset "%s" was found but the digest does not match.', $assetPath));
        }

        $response = (new Response(
            $asset->getContent(),
            headers: $asset->getMimeType() ? ['Content-Type' => $asset->getMimeType()] : [],
        ))
            ->setPublic()
            ->setMaxAge(604800)
            ->setImmutable()
            ->setEtag($asset->getDigest())
        ;

        $event->setResponse($response);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // priority higher than RouterListener
            KernelEvents::REQUEST => [['onKernelRequest', 35]],
        ];
    }

    private function extractAssetPathAndDigest(string $fullPath): array
    {
        $fullPath = substr($fullPath, \strlen($this->assetMapper->getPublicPrefix()));
        preg_match('/-([0-9a-zA-Z]{7,128}(?:\.digested)?)\.[^.]+\z/', $fullPath, $matches);

        if (!isset($matches[1])) {
            return [$fullPath, null];
        }

        $digest = $matches[1];

        $path = AssetMapper::isPathPredigested($fullPath) ? $fullPath : str_replace("-{$digest}", '', $fullPath);

        return [$path, $digest];
    }
}
