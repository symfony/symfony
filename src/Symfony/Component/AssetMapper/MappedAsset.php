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
    private string $publicPath;
    private string $publicPathWithoutDigest;
    /**
     * @var string the filesystem path to the source file
     */
    private string $sourcePath;
    private string $content;
    private string $digest;
    private bool $isPredigested;
    /** @var AssetDependency[] */
    private array $dependencies = [];
    /** @var string[] */
    private array $fileDependencies = [];

    public function __construct(private readonly string $logicalPath)
    {
    }

    public function getLogicalPath(): string
    {
        return $this->logicalPath;
    }

    public function getPublicPath(): string
    {
        return $this->publicPath;
    }

    public function getPublicExtension(): string
    {
        return pathinfo($this->publicPathWithoutDigest, \PATHINFO_EXTENSION);
    }

    public function getSourcePath(): string
    {
        return $this->sourcePath;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getDigest(): string
    {
        return $this->digest;
    }

    public function isPredigested(): bool
    {
        return $this->isPredigested;
    }

    /**
     * @return AssetDependency[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * @return string[]
     */
    public function getFileDependencies(): array
    {
        return $this->fileDependencies;
    }

    public function setPublicPath(string $publicPath): void
    {
        if (isset($this->publicPath)) {
            throw new \LogicException('Cannot set public path: it was already set on the asset.');
        }

        $this->publicPath = $publicPath;
    }

    public function setPublicPathWithoutDigest(string $publicPathWithoutDigest): void
    {
        if (isset($this->publicPathWithoutDigest)) {
            throw new \LogicException('Cannot set public path without digest: it was already set on the asset.');
        }

        $this->publicPathWithoutDigest = $publicPathWithoutDigest;
    }

    public function setSourcePath(string $sourcePath): void
    {
        if (isset($this->sourcePath)) {
            throw new \LogicException('Cannot set source path: it was already set on the asset.');
        }

        $this->sourcePath = $sourcePath;
    }

    public function setDigest(string $digest, bool $isPredigested): void
    {
        if (isset($this->digest)) {
            throw new \LogicException('Cannot set digest: it was already set on the asset.');
        }

        $this->digest = $digest;
        $this->isPredigested = $isPredigested;
    }

    public function setContent(string $content): void
    {
        if (isset($this->content)) {
            throw new \LogicException('Cannot set content: it was already set on the asset.');
        }

        $this->content = $content;
    }

    public function addDependency(AssetDependency $assetDependency): void
    {
        $this->dependencies[] = $assetDependency;
    }

    /**
     * Any filesystem files whose contents are used to create this asset.
     *
     * This is used to invalidate the cache when any of these files change.
     */
    public function addFileDependency(string $sourcePath): void
    {
        $this->fileDependencies[] = $sourcePath;
    }

    public function getPublicPathWithoutDigest(): string
    {
        return $this->publicPathWithoutDigest;
    }
}
