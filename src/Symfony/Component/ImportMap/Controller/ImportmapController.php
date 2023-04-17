<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ImportMap\Controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\ImportMap\ImportMapManager;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Mime\MimeTypesInterface;

/**
 * Controller to use only in development mode.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class ImportmapController
{
    private const EXTENSIONS_MAP = [
        'js' => 'application/javascript',
        'css' => 'text/css',
    ];

    public function __construct(
        private readonly ImportMapManager $importMapManager,
        private ?MimeTypesInterface $mimeTypes = null,
        private readonly array $extensionsMap = self::EXTENSIONS_MAP,
    ) {
        $this->mimeTypes ??= class_exists(MimeTypes::class) ? new MimeTypes() : null;
    }

    public function handle(Request $request): Response
    {
        $uri = $request->getRequestUri();
        if (!preg_match('/^(.*)\.(\w+)\.(.*)$/', $uri, $matches)) {
            throw new NotFoundHttpException();
        }

        $localPath = $this->importMapManager->getPathForUri($uri);
        if (null === $localPath) {
            throw new NotFoundHttpException();
        }

        $contentType = $this->extensionsMap[$matches[3]] ?? $this->mimeTypes?->guessMimeType($localPath);

        return (new BinaryFileResponse(
            $localPath,
            headers: $contentType ? ['Content-Type' => $contentType] : [],
            public: true,
        ))
            ->setMaxAge(604800)
            ->setImmutable()
            ->setEtag($matches[2])
        ;
    }
}
