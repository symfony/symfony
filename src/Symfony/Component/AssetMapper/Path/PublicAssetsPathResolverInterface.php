<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Path;

interface PublicAssetsPathResolverInterface
{
    /**
     * The path that should be prefixed on all asset paths to point to the output location.
     */
    public function resolvePublicPath(string $logicalPath): string;
}
