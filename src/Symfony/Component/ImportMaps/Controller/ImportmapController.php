<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ImportMaps\Controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\ImportMaps\ImportMapManager;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Mime\MimeTypesInterface;

/**
 * Controller to use only in development mode.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class ImportmapController
{
    public function __construct(
        private readonly string $assetsDir,
        private readonly ImportMapManager $importMapManager,
        private readonly MimeTypesInterface $mimeTypes = new MimeTypes(),
    ) {
    }

    public function handle(string $path): Response
    {
        if (
            // todo: fix this to prevent path traversing attacks
            !preg_match('/^([a-zA-Z0-9_@\/].*)\.(\w+)\.(.*)$/', $path, $matches)
        ) {
            throw new NotFoundHttpException();
        }

        $localPath = $this->assetsDir.$matches[1].'.'.$matches[3];

        if (
            !file_exists($localPath)
            || $matches[2] !== hash('xxh128', file_get_contents($localPath))
        ) {
            throw new NotFoundHttpException();
        }

        $contentType = $this->mimeTypes->guessMimeType($localPath);
        if ($contentType === 'text/plain') {
            $contentType = match ($matches[3]) {
                'js' => 'application/javascript',
                'css' => 'text/css',
                default => $matches[3],
            };
        }

        return new BinaryFileResponse(
            $localPath,
            headers: ['Content-Type' => $contentType],
        );
    }
}
