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

use Symfony\Component\AssetMapper\Factory\MappedAssetFactoryInterface;
use Symfony\Component\AssetMapper\Path\PublicAssetsPathResolverInterface;

/**
 * Finds and returns assets in the pipeline.
 *
 * @experimental
 *
 * @final
 */
class AssetMapper implements AssetMapperInterface
{
    public const MANIFEST_FILE_NAME = 'manifest.json';

    private ?array $manifestData = null;

    public function __construct(
        private readonly AssetMapperRepository $mapperRepository,
        private readonly MappedAssetFactoryInterface $mappedAssetFactory,
        private readonly PublicAssetsPathResolverInterface $assetsPathResolver,
    ) {
    }

    public function getAsset(string $logicalPath): ?MappedAsset
    {
        $filePath = $this->mapperRepository->find($logicalPath);
        if (null === $filePath) {
            return null;
        }

        return $this->mappedAssetFactory->createMappedAsset($logicalPath, $filePath);
    }

    public function allAssets(): iterable
    {
        foreach ($this->mapperRepository->all() as $logicalPath => $filePath) {
            $asset = $this->getAsset($logicalPath);
            if (null === $asset) {
                throw new \LogicException(sprintf('Asset "%s" could not be found.', $logicalPath));
            }
            yield $asset;
        }
    }

    public function getAssetFromSourcePath(string $sourcePath): ?MappedAsset
    {
        $logicalPath = $this->mapperRepository->findLogicalPath($sourcePath);
        if (null === $logicalPath) {
            return null;
        }

        return $this->getAsset($logicalPath);
    }

    public function getPublicPath(string $logicalPath): ?string
    {
        $manifestData = $this->loadManifest();
        if (isset($manifestData[$logicalPath])) {
            return $manifestData[$logicalPath];
        }

        $asset = $this->getAsset($logicalPath);

        return $asset?->publicPath;
    }

    private function loadManifest(): array
    {
        if (null === $this->manifestData) {
            $path = $this->assetsPathResolver->getPublicFilesystemPath().'/'.self::MANIFEST_FILE_NAME;

            if (!is_file($path)) {
                $this->manifestData = [];
            } else {
                $this->manifestData = json_decode(file_get_contents($path), true);
            }
        }

        return $this->manifestData;
    }
}
