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

class PublicAssetsPathResolver implements PublicAssetsPathResolverInterface
{
    private readonly string $publicPrefix;

    public function __construct(
        string $publicPrefix = '/assets/',
    ) {
        // ensure that the public prefix always ends with a single slash
        $this->publicPrefix = rtrim($publicPrefix, '/').'/';
    }

    public function resolvePublicPath(string $logicalPath): string
    {
        return $this->publicPrefix.ltrim($logicalPath, '/');
    }
}
