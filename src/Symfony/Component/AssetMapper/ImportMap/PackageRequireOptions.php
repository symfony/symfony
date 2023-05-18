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
 * Represents a package that should be installed or updated.
 *
 * @experimental
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class PackageRequireOptions
{
    public function __construct(
        public readonly string $packageName,
        public readonly ?string $versionConstraint = null,
        public readonly bool $download = false,
        public readonly bool $preload = false,
        public readonly ?string $importName = null,
        public readonly ?string $registryName = null,
        public readonly ?string $path = null,
    ) {
    }
}
