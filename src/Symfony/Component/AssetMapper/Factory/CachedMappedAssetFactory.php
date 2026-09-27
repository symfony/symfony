<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Factory;

use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Decorates the asset factory to load MappedAssets from cache when possible.
 */
class CachedMappedAssetFactory implements MappedAssetFactoryInterface
{
    /**
     * @var array<string, MappedAsset>
     */
    private array $mappedAssets = [];

    public function __construct(
        private readonly MappedAssetFactoryInterface $innerFactory,
        private readonly string $cacheDir,
        private readonly bool $debug,
    ) {
    }

    public function createMappedAsset(string $logicalPath, string $sourcePath): ?MappedAsset
    {
        return $this->mappedAssets[$logicalPath.':'.$sourcePath] ??= $this->loadMappedAsset($logicalPath, $sourcePath);
    }

    public function reset(): void
    {
        $this->mappedAssets = [];

        if (\is_callable([$this->innerFactory, 'reset'])) {
            $this->innerFactory->reset();
        }
    }

    private function loadMappedAsset(string $logicalPath, string $sourcePath): ?MappedAsset
    {
        $cachePath = $this->getCacheFilePath($logicalPath, $sourcePath);
        $filesystem = new Filesystem();

        if ($this->debug) {
            clearstatcache();
        }

        if (is_file($cachePath)) {
            [$resources, $mappedAsset] = unserialize($filesystem->readFile($cachePath), ['allowed_classes' => true]);

            if (!$this->debug || $this->isFresh(filemtime($cachePath), $resources)) {
                return $mappedAsset;
            }
        }

        $mappedAsset = $this->innerFactory->createMappedAsset($logicalPath, $sourcePath);

        if (!$mappedAsset) {
            return null;
        }

        $filesystem->dumpFile($cachePath, serialize([$this->collectResourcesFromAsset($mappedAsset), $mappedAsset]));

        return $mappedAsset;
    }

    private function getCacheFilePath(string $logicalPath, string $sourcePath): string
    {
        return $this->cacheDir.'/'.hash('xxh128', $logicalPath.':'.$sourcePath).'.ser';
    }

    /**
     * @param SelfCheckingResourceInterface[] $resources
     */
    private function isFresh(int $timestamp, array $resources): bool
    {
        foreach ($resources as $resource) {
            if (!$resource->isFresh($timestamp)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return SelfCheckingResourceInterface[]
     */
    private function collectResourcesFromAsset(MappedAsset $mappedAsset): array
    {
        $resources = array_map(static fn (string $path) => is_dir($path) ? new DirectoryResource($path) : new FileResource($path), $mappedAsset->getFileDependencies());
        $resources[] = new FileResource($mappedAsset->sourcePath);

        foreach ($mappedAsset->getDependencies() as $assetDependency) {
            $resources = array_merge($resources, $this->collectResourcesFromAsset($assetDependency));
        }

        foreach ($mappedAsset->getJavaScriptImports() as $import) {
            $resources[] = new FileExistenceResource($import->assetSourcePath);
        }

        return $resources;
    }
}
