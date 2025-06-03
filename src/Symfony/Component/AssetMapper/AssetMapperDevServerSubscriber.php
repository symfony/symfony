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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * Functions like a controller that returns assets from the asset mapper.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class AssetMapperDevServerSubscriber implements EventSubscriberInterface
{
    // source: https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
    private const EXTENSIONS_MAP = [
        'aac' => 'audio/aac',
        'abw' => 'application/x-abiword',
        'arc' => 'application/x-freearc',
        'avif' => 'image/avif',
        'avi' => 'video/x-msvideo',
        'azw' => 'application/vnd.amazon.ebook',
        'bin' => 'application/octet-stream',
        'bmp' => 'image/bmp',
        'bz' => 'application/x-bzip',
        'bz2' => 'application/x-bzip2',
        'cda' => 'application/x-cdf',
        'csh' => 'application/x-csh',
        'css' => 'text/css',
        'csv' => 'text/csv',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'eot' => 'application/vnd.ms-fontobject',
        'epub' => 'application/epub+zip',
        'gz' => 'application/gzip',
        'gif' => 'image/gif',
        'htm' => 'text/html',
        'html' => 'text/html',
        'ico' => 'image/vnd.microsoft.icon',
        'ics' => 'text/calendar',
        'jar' => 'application/java-archive',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'jsonld' => 'application/ld+json',
        'mid' => 'audio/midi',
        'midi' => 'audio/midi',
        'mjs' => 'text/javascript',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'mpeg' => 'video/mpeg',
        'mpkg' => 'application/vnd.apple.installer+xml',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'oga' => 'audio/ogg',
        'ogv' => 'video/ogg',
        'ogx' => 'application/ogg',
        'opus' => 'audio/opus',
        'otf' => 'font/otf',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
        'php' => 'application/x-httpd-php',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'rar' => 'application/vnd.rar',
        'rtf' => 'application/rtf',
        'sh' => 'application/x-sh',
        'svg' => 'image/svg+xml',
        'tar' => 'application/x-tar',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'ts' => 'video/mp2t',
        'ttf' => 'font/ttf',
        'txt' => 'text/plain',
        'vsd' => 'application/vnd.visio',
        'wav' => 'audio/wav',
        'weba' => 'audio/webm',
        'webm' => 'video/webm',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    private readonly string $publicPrefix;
    private array $extensionsMap;

    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        string $publicPrefix = '/assets/',
        array $extensionsMap = [],
        private readonly ?CacheItemPoolInterface $cacheMapCache = null,
        private readonly ?Profiler $profiler = null,
    ) {
        $this->publicPrefix = '/'.trim($publicPrefix, '/').'/';
        $this->extensionsMap = array_merge(self::EXTENSIONS_MAP, $extensionsMap);
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $pathInfo = rawurldecode($event->getRequest()->getPathInfo());
        if (!str_starts_with($pathInfo, $this->publicPrefix)) {
            return;
        }

        $asset = $this->findAssetFromCache($pathInfo);

        if (!$asset) {
            throw new NotFoundHttpException(\sprintf('Asset with public path "%s" not found.', $pathInfo));
        }

        $this->profiler?->disable();

        if (null !== $asset->content) {
            $response = new Response($asset->content);
        } else {
            $response = new BinaryFileResponse($asset->sourcePath, autoLastModified: false);
        }
        $response
            ->setPublic()
            ->setMaxAge(604800) // 1 week
            ->setImmutable()
            ->setEtag($asset->digest)
        ;
        if ($mediaType = $this->getMediaType($asset->publicPath)) {
            $response->headers->set('Content-Type', $mediaType);
        }
        $response->headers->set('X-Assets-Dev', true);

        $event->setResponse($response);
        $event->stopPropagation();
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($event->getResponse()->headers->get('X-Assets-Dev')) {
            $event->stopPropagation();
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // priority higher than RouterListener
            KernelEvents::REQUEST => [['onKernelRequest', 35]],
            // Highest priority possible to bypass all other listeners
            KernelEvents::RESPONSE => [['onKernelResponse', 2048]],
        ];
    }

    private function getMediaType(string $path): ?string
    {
        $extension = pathinfo($path, \PATHINFO_EXTENSION);

        return $this->extensionsMap[$extension] ?? null;
    }

    private function findAssetFromCache(string $pathInfo): ?MappedAsset
    {
        $cachedAsset = null;
        if (null !== $this->cacheMapCache) {
            $cachedAsset = $this->cacheMapCache->getItem(hash('xxh128', $pathInfo));
            $asset = $cachedAsset->isHit() ? $this->assetMapper->getAsset($cachedAsset->get()) : null;

            if (null !== $asset && $asset->publicPath === $pathInfo) {
                return $asset;
            }
        }

        // we did not find a match
        $asset = null;
        foreach ($this->assetMapper->allAssets() as $assetCandidate) {
            if ($pathInfo === $assetCandidate->publicPath) {
                $asset = $assetCandidate;
                break;
            }
        }

        if (null === $asset) {
            return null;
        }

        if (null !== $cachedAsset) {
            $cachedAsset->set($asset->logicalPath);
            $this->cacheMapCache->save($cachedAsset);
        }

        return $asset;
    }
}
