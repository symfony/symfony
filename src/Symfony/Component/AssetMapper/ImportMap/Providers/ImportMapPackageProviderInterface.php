<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\ImportMap\Providers;

use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;

/**
 * @experimental
 */
interface ImportMapPackageProviderInterface
{
    /**
     * Grabs the URLs for the given packages and converts them to ImportMapEntry objects.
     *
     * If "download" is specified in PackageRequireOptions, the resolved package
     * contents should be included.
     *
     * @param PackageRequireOptions[] $packagesToRequire
     *
     * @return ResolvedImportMapPackage[] The import map entries that should be added
     */
    public function resolvePackages(array $packagesToRequire): array;
}
