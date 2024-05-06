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
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class PackageRequireOptions
{
    public readonly string $importName;

    public function __construct(
        /**
         * The "package-name/path" of the remote package.
         */
        public readonly string $packageModuleSpecifier,
        public readonly ?string $versionConstraint = null,
        ?string $importName = null,
        public readonly ?string $path = null,
        public readonly bool $entrypoint = false,
    ) {
        $this->importName = $importName ?: $packageModuleSpecifier;
    }
}
