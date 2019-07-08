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
 * Asset package interface.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
interface PackageInterface
{
    /**
     * Returns the asset version for an asset.
     *
     * @return string The version string
     */
    public function getVersion(string $path);

    /**
     * Returns an absolute or root-relative public path.
     *
     * @return string The public path
     */
    public function getUrl(string $path);
}
