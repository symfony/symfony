<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\ImportMap\Resolver;

use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;

final class ResolvedImportMapPackage
{
    public function __construct(
        public readonly PackageRequireOptions $requireOptions,
        public readonly string $version,
        public readonly ImportMapType $type,
    ) {
    }
}
