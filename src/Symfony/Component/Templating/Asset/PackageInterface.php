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
 *
 * @deprecated since 2.7, will be removed in 3.0. Use the Asset component instead.
 */
interface PackageInterface
{
    /**
     * Returns the asset package version.
     *
     * @return string The version string
     */
    public function getVersion();

    /**
     * Returns an absolute or root-relative public path.
     *
     * @param string           $path    A path
     * @param string|bool|null $version A specific version for the path
     *
     * @return string The public path
     */
    public function getUrl($path, $version = null);
}
