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
    private const PREDIGESTED_REGEX = '/-([0-9a-zA-Z]{7,128}\.digested)/';

    private ?array $manifestData = null;
    private array $fileContentsCache = [];
    private array $assetsBeingCreated = [];

    private array $assetsCache = [];

    public function __construct(
        private readonly AssetMapperRepository $mapperRepository,
        private readonly AssetMapperCompiler $compiler,
        private readonly PublicAssetsPathResolverInterface $assetsPathResolver,
    ) {
    }

    public function getAsset(string $logicalPath): ?MappedAsset
    {
        if (\in_array($logicalPath, $this->assetsBeingCreated, true)) {
            throw new \RuntimeException(sprintf('Circular reference detected while creating asset for "%s": "%s".', $logicalPath, implode(' -> ', $this->assetsBeingCreated).' -> '.$logicalPath));
        }

        if (!isset($this->assetsCache[$logicalPath])) {
            $this->assetsBeingCreated[] = $logicalPath;

            $filePath = $this->mapperRepository->find($logicalPath);
            if (null === $filePath) {
                return null;
            }

            $asset = new MappedAsset($logicalPath);
            $this->assetsCache[$logicalPath] = $asset;
            $asset->setSourcePath($filePath);

            $asset->setPublicPathWithoutDigest($this->assetsPathResolver->resolvePublicPath($logicalPath));
            $publicPath = $this->getPublicPath($logicalPath);
            $asset->setPublicPath($publicPath);
            [$digest, $isPredigested] = $this->getDigest($asset);
            $asset->setDigest($digest, $isPredigested);
            $asset->setContent($this->calculateContent($asset));

            array_pop($this->assetsBeingCreated);
        }

        return $this->assetsCache[$logicalPath];
    }

    /**
     * @return MappedAsset[]
     */
    public function allAssets(): array
    {
        $assets = [];
        foreach ($this->mapperRepository->all() as $logicalPath => $filePath) {
            $asset = $this->getAsset($logicalPath);
            if (null === $asset) {
                throw new \LogicException(sprintf('Asset "%s" could not be found.', $logicalPath));
            }
            $assets[] = $asset;
        }

        return $assets;
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

        $filePath = $this->mapperRepository->find($logicalPath);
        if (null === $filePath) {
            return null;
        }

        // grab the Asset - first look in the cache, as it may only be partially created
        $asset = $this->assetsCache[$logicalPath] ?? $this->getAsset($logicalPath);
        [$digest, $isPredigested] = $this->getDigest($asset);

        if ($isPredigested) {
            return $this->assetsPathResolver->resolvePublicPath($logicalPath);
        }

        $digestedPath = preg_replace_callback('/\.(\w+)$/', function ($matches) use ($digest) {
            return "-{$digest}{$matches[0]}";
        }, $logicalPath);

        return $this->assetsPathResolver->resolvePublicPath($digestedPath);
    }

    /**
     * Returns an array of "string digest" and "bool predigested".
     *
     * @return array{0: string, 1: bool}
     */
    private function getDigest(MappedAsset $asset): array
    {
        // check for a pre-digested file
        if (1 === preg_match(self::PREDIGESTED_REGEX, $asset->getLogicalPath(), $matches)) {
            return [$matches[1], true];
        }

        return [
            hash('xxh128', $this->calculateContent($asset)),
            false,
        ];
    }

    private function calculateContent(MappedAsset $asset): string
    {
        if (isset($this->fileContentsCache[$asset->getLogicalPath()])) {
            return $this->fileContentsCache[$asset->getLogicalPath()];
        }

        $content = file_get_contents($asset->getSourcePath());
        $content = $this->compiler->compile($content, $asset, $this);

        $this->fileContentsCache[$asset->getLogicalPath()] = $content;

        return $content;
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
