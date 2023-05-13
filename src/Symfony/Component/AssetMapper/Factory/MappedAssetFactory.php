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

use Symfony\Component\AssetMapper\AssetMapperCompiler;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\AssetMapper\Path\PublicAssetsPathResolverInterface;

/**
 * Creates MappedAsset objects by reading their contents & passing it through compilers.
 */
class MappedAssetFactory implements MappedAssetFactoryInterface
{
    private const PREDIGESTED_REGEX = '/-([0-9a-zA-Z]{7,128}\.digested)/';

    private array $assetsCache = [];
    private array $assetsBeingCreated = [];
    private array $fileContentsCache = [];

    public function __construct(
        private PublicAssetsPathResolverInterface $assetsPathResolver,
        private AssetMapperCompiler $compiler,
    ) {
    }

    public function createMappedAsset(string $logicalPath, string $sourcePath): ?MappedAsset
    {
        if (\in_array($logicalPath, $this->assetsBeingCreated, true)) {
            throw new \RuntimeException(sprintf('Circular reference detected while creating asset for "%s": "%s".', $logicalPath, implode(' -> ', $this->assetsBeingCreated).' -> '.$logicalPath));
        }

        if (!isset($this->assetsCache[$logicalPath])) {
            $this->assetsBeingCreated[] = $logicalPath;

            $asset = new MappedAsset($logicalPath);
            $this->assetsCache[$logicalPath] = $asset;
            $asset->setSourcePath($sourcePath);

            $asset->setPublicPathWithoutDigest($this->assetsPathResolver->resolvePublicPath($logicalPath));
            $publicPath = $this->getPublicPath($asset);
            $asset->setPublicPath($publicPath);
            [$digest, $isPredigested] = $this->getDigest($asset);
            $asset->setDigest($digest, $isPredigested);
            $asset->setContent($this->calculateContent($asset));

            array_pop($this->assetsBeingCreated);
        }

        return $this->assetsCache[$logicalPath];
    }

    /**
     * Returns an array of "string digest" and "bool predigested".
     *
     * @return array{0: string, 1: bool}
     */
    private function getDigest(MappedAsset $asset): array
    {
        // check for a pre-digested file
        if (preg_match(self::PREDIGESTED_REGEX, $asset->getLogicalPath(), $matches)) {
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

        if (!is_file($asset->getSourcePath())) {
            throw new \RuntimeException(sprintf('Asset source path "%s" could not be found.', $asset->getSourcePath()));
        }

        $content = file_get_contents($asset->getSourcePath());
        $content = $this->compiler->compile($content, $asset);

        $this->fileContentsCache[$asset->getLogicalPath()] = $content;

        return $content;
    }

    private function getPublicPath(MappedAsset $asset): ?string
    {
        [$digest, $isPredigested] = $this->getDigest($asset);

        if ($isPredigested) {
            return $this->assetsPathResolver->resolvePublicPath($asset->getLogicalPath());
        }

        $digestedPath = preg_replace_callback('/\.(\w+)$/', fn ($matches) => "-{$digest}{$matches[0]}", $asset->getLogicalPath());

        return $this->assetsPathResolver->resolvePublicPath($digestedPath);
    }
}
