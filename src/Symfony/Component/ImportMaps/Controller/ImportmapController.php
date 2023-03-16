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

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\ImportMaps\ImportMapManager;

/**
 * Controller to use only in development mode.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class ImportmapController
{
    public function __construct(
        private readonly string $javascriptsDir,
        private readonly ImportMapManager $importMapManager,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function handle(string $path): Response
    {
        if (
            // prevent path traversing attacks
            !preg_match('/^([a-zA-Z0-9_@\/].*)\.(\w+)\.js$/', $path, $matches) ||
            !($mappedPath = $this->importMapManager->getImportMapArray()[$matches[1]]['path'] ?? null)
        ) {
            throw new NotFoundHttpException();
        }

        if (
            !$this->filesystem->exists($localPath = $this->javascriptsDir.$mappedPath)
            || $matches[2] !== hash('xxh128', file_get_contents($localPath))
        ) {
            throw new NotFoundHttpException();
        }

        return new BinaryFileResponse($localPath, headers: ['Content-Type' => 'text/javascript']);
    }
}
