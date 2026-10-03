<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset;

/**
 * Returns the public URL and the version of the assets of a package.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
interface PackageInterface
{
    /**
     * Returns the asset version for an asset.
     */
    public function getVersion(string $path): string;

    /**
     * Returns an absolute or root-relative public path.
     */
    public function getUrl(string $path): string;
}
