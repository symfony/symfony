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
 * An asset package.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
interface AssetPackageInterface
{
    /**
     * Returns a base URL to use for the supplied path.
     *
     * @param string $path A path
     *
     * @return string The base URL for that path
     */
    function getBaseUrl($path);

    /**
     * Sets the collection of base URLs.
     *
     * @param array $baseUrls An array of base URLs
     */
    function setBaseUrls(array $baseUrls = array());

    /**
     * Returns the package version.
     *
     * @return string|null The package version, if set
     */
    function getVersion();

    /**
     * Sets the package version.
     *
     * @param string $version The package version
     */
    function setVersion($version);

    /**
     * Adds the package version to a path.
     *
     * @param string $path An asset path
     *
     * @return string The versionized path
     */
    function versionize($path);
}
