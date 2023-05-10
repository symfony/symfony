<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Factory;

use Symfony\Component\AssetMapper\MappedAsset;

interface MappedAssetFactoryInterface
{
    public function createMappedAsset(string $logicalPath, string $sourcePath): ?MappedAsset;
}
