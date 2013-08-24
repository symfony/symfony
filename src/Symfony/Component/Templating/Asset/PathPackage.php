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
 * The path packages adds a version and a base path to asset URLs.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 *
 * @since v2.0.0
 */
class PathPackage extends Package
{
    private $basePath;

    /**
     * Constructor.
     *
     * @param string $basePath The base path to be prepended to relative paths
     * @param string $version  The package version
     * @param string $format   The format used to apply the version
     *
     * @since v2.0.0
     */
    public function __construct($basePath = null, $version = null, $format = null)
    {
        parent::__construct($version, $format);

        if (!$basePath) {
            $this->basePath = '/';
        } else {
            if ('/' != $basePath[0]) {
                $basePath = '/'.$basePath;
            }

            $this->basePath = rtrim($basePath, '/').'/';
        }
    }

    /**
     * @since v2.0.0
     */
    public function getUrl($path)
    {
        if (false !== strpos($path, '://') || 0 === strpos($path, '//')) {
            return $path;
        }

        $url = $this->applyVersion($path);

        // apply the base path
        if ('/' !== substr($url, 0, 1)) {
            $url = $this->basePath.$url;
        }

        return $url;
    }

    /**
     * Returns the base path.
     *
     * @return string The base path
     *
     * @since v2.0.0
     */
    public function getBasePath()
    {
        return $this->basePath;
    }
}
