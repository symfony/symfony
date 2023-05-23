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

use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;

/**
 * @experimental
 */
class ResolvedImportMapPackage
{
    public function __construct(
        public readonly PackageRequireOptions $requireOptions,
        public readonly string $url,
        public readonly ?string $content = null,
    ) {
    }
}
