<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Asset;

/**
 * Asset package interface.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
interface AssetPackageInterface
{
    /**
     * Returns the asset package version.
     *
     * @return string The version string
     */
    function getVersion();

    /**
     * Returns a base URL for the supplied path.
     *
     * @param string $path An asset path
     *
     * @return string A base URL
     */
    function getBaseUrl($path);
}
