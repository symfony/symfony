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

/**
 * Represents a single asset in the asset mapper system.
 *
 * @experimental
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class MappedAsset
{
    public readonly string $sourcePath;
    public readonly string $publicPath;
    public readonly string $publicPathWithoutDigest;
    public readonly string $publicExtension;
    public readonly string $content;
    public readonly string $digest;
    public readonly bool $isPredigested;

    /**
     * @var AssetDependency[]
     */
    private array $dependencies = [];

    /**
     * @var string[]
     */
    private array $fileDependencies = [];

    /**
     * @param AssetDependency[] $dependencies
     * @param string[]          $fileDependencies
     */
    public function __construct(
        public readonly string $logicalPath,
        string $sourcePath = null,
        string $publicPathWithoutDigest = null,
        string $publicPath = null,
        string $content = null,
        string $digest = null,
        bool $isPredigested = null,
        array $dependencies = [],
        array $fileDependencies = [],
    ) {
        if (null !== $sourcePath) {
            $this->sourcePath = $sourcePath;
        }
        if (null !== $publicPath) {
            $this->publicPath = $publicPath;
        }
        if (null !== $publicPathWithoutDigest) {
            $this->publicPathWithoutDigest = $publicPathWithoutDigest;
            $this->publicExtension = pathinfo($publicPathWithoutDigest, \PATHINFO_EXTENSION);
        }
        if (null !== $content) {
            $this->content = $content;
        }
        if (null !== $digest) {
            $this->digest = $digest;
        }
        if (null !== $isPredigested) {
            $this->isPredigested = $isPredigested;
        }
        foreach ($dependencies as $dependency) {
            $this->addDependency($dependency);
        }
        foreach ($fileDependencies as $fileDependency) {
            $this->addFileDependency($fileDependency);
        }
    }

    /**
     * @return AssetDependency[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function addDependency(AssetDependency $assetDependency): void
    {
        $this->dependencies[] = $assetDependency;
    }

    /**
     * @return string[]
     */
    public function getFileDependencies(): array
    {
        return $this->fileDependencies;
    }

    public function addFileDependency(string $sourcePath): void
    {
        $this->fileDependencies[] = $sourcePath;
    }
}
