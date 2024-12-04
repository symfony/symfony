<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\CacheWarmer;

use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
use Symfony\Component\AssetMapper\Event\PreAssetsCompileEvent;
use Symfony\Component\AssetMapper\ImportMap\ImportMapGenerator;
use Symfony\Component\AssetMapper\Path\PublicAssetsFilesystemInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class AssetMapperCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private readonly CompiledAssetMapperConfigReader $compiledConfigReader,
        private readonly AssetMapperInterface $assetMapper,
        private readonly ImportMapGenerator $importMapGenerator,
        private readonly PublicAssetsFilesystemInterface $assetsFilesystem,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    public function isOptional(): bool
    {
        return false;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $this->compiledConfigReader->removeConfig(AssetMapper::MANIFEST_FILE_NAME);
        $this->compiledConfigReader->removeConfig(ImportMapGenerator::IMPORT_MAP_CACHE_FILENAME);

        $entrypointFiles = [];
        foreach ($this->importMapGenerator->getEntrypointNames() as $entrypointName) {
            $path = \sprintf(ImportMapGenerator::ENTRYPOINT_CACHE_FILENAME_PATTERN, $entrypointName);
            $this->compiledConfigReader->removeConfig($path);
            $entrypointFiles[$entrypointName] = $path;
        }

        $this->eventDispatcher?->dispatch(new PreAssetsCompileEvent(new NullOutput()));

        $manifest = $this->createManifestAndWriteFiles();
        $this->compiledConfigReader->saveConfig(AssetMapper::MANIFEST_FILE_NAME, $manifest);

        $this->compiledConfigReader->saveConfig(
            ImportMapGenerator::IMPORT_MAP_CACHE_FILENAME,
            $this->importMapGenerator->getRawImportMapData()
        );

        foreach ($entrypointFiles as $entrypointName => $path) {
            $this->compiledConfigReader->saveConfig(
                $path,
                $this->importMapGenerator->findEagerEntrypointImports($entrypointName)
            );
        }

        return [];
    }

    private function createManifestAndWriteFiles(): array
    {
        $manifest = [];
        foreach ($this->assetMapper->allAssets() as $asset) {
            if (null !== $asset->content) {
                $this->assetsFilesystem->write($asset->publicPath, $asset->content);
            } else {
                $this->assetsFilesystem->copy($asset->sourcePath, $asset->publicPath);
            }

            $manifest[$asset->logicalPath] = $asset->publicPath;
        }
        ksort($manifest);

        return $manifest;
    }
}
