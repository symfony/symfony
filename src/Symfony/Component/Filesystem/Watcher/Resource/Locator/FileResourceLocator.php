<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Watcher\Resource\Locator;

use Symfony\Component\Filesystem\Watcher\Resource\ArrayResource;
use Symfony\Component\Filesystem\Watcher\Resource\DirectoryResource;
use Symfony\Component\Filesystem\Watcher\Resource\FileResource;
use Symfony\Component\Filesystem\Watcher\Resource\ResourceInterface;
use Symfony\Component\Finder\Finder;

/**
 * @author Pierre du Plessis <pdples@gmail.com>
 *
 * @internal
 */
class FileResourceLocator
{
    public function locate($path): ?ResourceInterface
    {
        if ($path instanceof Finder || $path instanceof \Iterator) {
            $path = iterator_to_array($path);
        }

        if ($path instanceof \SplFileInfo) {
            $path = $path->getRealPath();
        }

        if (\is_array($path)) {
            return new ArrayResource(array_map([$this, 'locate'], $path));
        }

        if (\is_string($path)) {
            if (is_dir($path)) {
                return new DirectoryResource($path);
            }

            $paths = glob($path, \defined('GLOB_BRACE') ? GLOB_BRACE : 0);

            if (1 === \count($paths)) {
                return new FileResource($paths[0]);
            }

            return new ArrayResource(array_map(function ($path) {
                return new FileResource($path);
            }, $paths));
        }

        return null;
    }
}
