<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

/**
 * A loader that discovers controller classes in a directory that follows PSR-4.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class Psr4DirectoryLoader extends Loader
{
    public function __construct(
        private readonly FileLocatorInterface $locator,
    ) {
        // PSR-4 directory loader has no env-aware logic, so we drop the $env constructor parameter.
        parent::__construct();
    }

    /**
     * @param array{path: string, namespace: string} $resource
     */
    public function load(mixed $resource, string $type = null): ?RouteCollection
    {
        $path = $this->locator->locate($resource['path']);
        if (!is_dir($path)) {
            return new RouteCollection();
        }

        return $this->loadFromDirectory($path, trim($resource['namespace'], '\\'));
    }

    public function supports(mixed $resource, string $type = null): bool
    {
        return ('attribute' === $type || 'annotation' === $type) && \is_array($resource) && isset($resource['path'], $resource['namespace']);
    }

    private function loadFromDirectory(string $directory, string $psr4Prefix): RouteCollection
    {
        $collection = new RouteCollection();

        /** @var \SplFileInfo $file */
        foreach (new \FilesystemIterator($directory) as $file) {
            if ($file->isDir()) {
                $collection->addCollection($this->loadFromDirectory($file->getPathname(), $psr4Prefix.'\\'.$file->getFilename()));

                continue;
            }
            if ('php' !== $file->getExtension() || !class_exists($className = $psr4Prefix.'\\'.$file->getBasename('.php'))) {
                continue;
            }

            $collection->addCollection($this->import($className, 'attribute'));
        }

        return $collection;
    }
}
