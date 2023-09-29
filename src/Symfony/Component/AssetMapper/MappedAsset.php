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

use Symfony\Component\AssetMapper\ImportMap\JavaScriptImport;

/**
 * Represents a single asset in the asset mapper system.
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
     * @var MappedAsset[]
     */
    private array $dependencies = [];

    /**
     * @var string[]
     */
    private array $fileDependencies = [];

    /**
     * @var JavaScriptImport[]
     */
    private array $javaScriptImports = [];

    /**
     * @param MappedAsset[] $dependencies     assets that the content of this asset depends on
     * @param string[]      $fileDependencies files that the content of this asset depends on
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
        array $javaScriptImports = [],
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
        $this->dependencies = $dependencies;
        $this->fileDependencies = $fileDependencies;
        $this->javaScriptImports = $javaScriptImports;
    }

    /**
     * @return MappedAsset[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function addDependency(self $asset): void
    {
        $this->dependencies[] = $asset;
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

    /**
     * @return JavaScriptImport[]
     */
    public function getJavaScriptImports(): array
    {
        return $this->javaScriptImports;
    }

    public function addJavaScriptImport(JavaScriptImport $import): void
    {
        $this->javaScriptImports[] = $import;
    }
}
