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
     * @param string $path A path
     *
     * @return string The version string
     */
    public function getVersion($path);

    /**
     * Returns an absolute or root-relative public path.
     *
     * @param string $path A path
     *
     * @return string The public path
     */
    public function getUrl($path);
}
