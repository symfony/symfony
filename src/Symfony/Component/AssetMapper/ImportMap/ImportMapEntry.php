<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\ImportMap;

/**
 * Represents an item that should be in the importmap.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class ImportMapEntry
{
    private function __construct(
        public readonly string $importName,
        public readonly ImportMapType $type,
        /**
         * A logical path, relative path or absolute path to the file.
         */
        public readonly string $path,
        public readonly bool $isEntrypoint,
        /**
         * The version of the package (remote only).
         */
        public readonly ?string $version,
        /**
         * The full "package-name/path" (remote only).
         */
        public readonly ?string $packageModuleSpecifier,
    ) {
    }

    public static function createLocal(string $importName, ImportMapType $importMapType, string $path, bool $isEntrypoint): self
    {
        return new self($importName, $importMapType, $path, $isEntrypoint, null, null);
    }

    public static function createRemote(string $importName, ImportMapType $importMapType, string $path, string $version, string $packageModuleSpecifier, bool $isEntrypoint): self
    {
        return new self($importName, $importMapType, $path, $isEntrypoint, $version, $packageModuleSpecifier);
    }

    public function getPackageName(): string
    {
        return self::splitPackageNameAndFilePath($this->packageModuleSpecifier)[0];
    }

    public function getPackagePathString(): string
    {
        return self::splitPackageNameAndFilePath($this->packageModuleSpecifier)[1];
    }

    /**
     * @psalm-assert-if-true !null $this->version
     * @psalm-assert-if-true !null $this->packageModuleSpecifier
     */
    public function isRemotePackage(): bool
    {
        return null !== $this->version;
    }

    public static function splitPackageNameAndFilePath(string $packageModuleSpecifier): array
    {
        $filePath = '';
        $i = strpos($packageModuleSpecifier, '/');

        if ($i && (!str_starts_with($packageModuleSpecifier, '@') || $i = strpos($packageModuleSpecifier, '/', $i + 1))) {
            // @vendor/package/filepath or package/filepath
            $filePath = substr($packageModuleSpecifier, $i);
            $packageModuleSpecifier = substr($packageModuleSpecifier, 0, $i);
        }

        return [$packageModuleSpecifier, $filePath];
    }
}
