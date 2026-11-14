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

use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;

interface PackageUrlResolverInterface
{
    /**
     * Returns a browser URL for a remote importmap entry.
     */
    public function getPackageUrl(ImportMapEntry $entry): string;
}
