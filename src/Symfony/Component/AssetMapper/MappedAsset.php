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
    public string $publicPath;
    /**
     * @var string The filesystem path to the source file.
     */
    private string $sourcePath;
    private string $content;
    private string $digest;
    private bool $isPredigested;
    private ?string $mimeType;
    /** @var AssetDependency[]  */
    private array $dependencies = [];

    public function __construct(public readonly string $logicalPath)
    {
    }

    public function getPublicPath(): string
    {
        return $this->publicPath;
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

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getExtension(): string
    {
        return pathinfo($this->logicalPath, \PATHINFO_EXTENSION);
    }

    /**
     * @return AssetDependency[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function setPublicPath(string $publicPath): void
    {
        if (isset($this->publicPath)) {
            throw new \LogicException('Cannot set public path: it was already set on the asset.');
        }

        $this->publicPath = $publicPath;
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

    public function setMimeType(?string $mimeType): void
    {
        if (isset($this->mimeType)) {
            throw new \LogicException('Cannot set mime type: it was already set on the asset.');
        }

        $this->mimeType = $mimeType;
    }

    public function setContent(string $content): void
    {
        if (isset($this->content)) {
            throw new \LogicException('Cannot set content: it was already set on the asset.');
        }

        $this->content = $content;
    }

    public function addDependency(MappedAsset $asset, bool $isLazy = false): void
    {
        $this->dependencies[] = new AssetDependency($asset, $isLazy);
    }

    public function getPublicPathWithoutDigest(): string
    {
        if ($this->isPredigested()) {
            return $this->getPublicPath();
        }

        // remove last part of publicPath and replace with last part of logicalPath
        $publicPathParts = explode('/', $this->getPublicPath());
        $logicalPathParts = explode('/', $this->logicalPath);
        array_pop($publicPathParts);
        $publicPathParts[] = array_pop($logicalPathParts);

        return implode('/', $publicPathParts);
    }
}
