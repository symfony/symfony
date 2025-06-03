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
use Symfony\Component\AssetMapper\Exception\CircularAssetsException;
use Symfony\Component\AssetMapper\Exception\RuntimeException;
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

    public function __construct(
        private readonly PublicAssetsPathResolverInterface $assetsPathResolver,
        private readonly AssetMapperCompiler $compiler,
        private readonly string $vendorDir,
    ) {
    }

    public function createMappedAsset(string $logicalPath, string $sourcePath): ?MappedAsset
    {
        if (isset($this->assetsBeingCreated[$logicalPath])) {
            throw new CircularAssetsException($this->assetsCache[$logicalPath], sprintf('Circular reference detected while creating asset for "%s": "%s".', $logicalPath, implode(' -> ', $this->assetsBeingCreated).' -> '.$logicalPath));
        }
        $this->assetsBeingCreated[$logicalPath] = $logicalPath;

        if (!isset($this->assetsCache[$logicalPath])) {
            $isVendor = $this->isVendor($sourcePath);
            $asset = new MappedAsset($logicalPath, $sourcePath, $this->assetsPathResolver->resolvePublicPath($logicalPath), isVendor: $isVendor);
            $this->assetsCache[$logicalPath] = $asset;

            $content = $this->compileContent($asset);
            [$digest, $isPredigested] = $this->getDigest($asset, $content);

            $asset = new MappedAsset(
                $asset->logicalPath,
                $asset->sourcePath,
                $asset->publicPathWithoutDigest,
                $this->getPublicPath($asset, $content),
                $content,
                $digest,
                $isPredigested,
                $isVendor,
                $asset->getDependencies(),
                $asset->getFileDependencies(),
                $asset->getJavaScriptImports(),
            );

            $this->assetsCache[$logicalPath] = $asset;
        }

        unset($this->assetsBeingCreated[$logicalPath]);

        return $this->assetsCache[$logicalPath];
    }

    /**
     * Returns an array of "string digest" and "bool predigested".
     *
     * @return array{0: string, 1: bool}
     */
    private function getDigest(MappedAsset $asset, ?string $content): array
    {
        // check for a pre-digested file
        if (preg_match(self::PREDIGESTED_REGEX, $asset->logicalPath, $matches)) {
            return [$matches[1], true];
        }

        // Use the compiled content if any
        if (null !== $content) {
            return [hash('xxh128', $content), false];
        }

        return [
            hash_file('xxh128', $asset->sourcePath),
            false,
        ];
    }

    private function compileContent(MappedAsset $asset): ?string
    {
        if (!is_file($asset->sourcePath)) {
            throw new RuntimeException(sprintf('Asset source path "%s" could not be found.', $asset->sourcePath));
        }

        if (!$this->compiler->supports($asset)) {
            return null;
        }

        $content = file_get_contents($asset->sourcePath);
        $compiled = $this->compiler->compile($content, $asset);

        return $compiled !== $content ? $compiled : null;
    }

    private function getPublicPath(MappedAsset $asset, ?string $content): ?string
    {
        [$digest, $isPredigested] = $this->getDigest($asset, $content);

        if ($isPredigested) {
            return $this->assetsPathResolver->resolvePublicPath($asset->logicalPath);
        }

        $digestedPath = preg_replace_callback('/\.(\w+)$/', fn ($matches) => "-{$digest}{$matches[0]}", $asset->logicalPath);

        return $this->assetsPathResolver->resolvePublicPath($digestedPath);
    }

    private function isVendor(string $sourcePath): bool
    {
        $sourcePath = realpath($sourcePath);
        $vendorDir = realpath($this->vendorDir);

        return $sourcePath && $vendorDir && str_starts_with($sourcePath, $vendorDir);
    }
}
