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

@trigger_error('The Symfony\Component\Templating\Asset\PathPackage is deprecated since Symfony 2.7 and will be removed in 3.0. Use the Asset component instead.', E_USER_DEPRECATED);

/**
 * The path packages adds a version and a base path to asset URLs.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 *
 * @deprecated since 2.7, will be removed in 3.0. Use the Asset component instead.
 */
class PathPackage extends Package
{
    private $basePath;

    /**
     * @param string $basePath The base path to be prepended to relative paths
     * @param string $version  The package version
     * @param string $format   The format used to apply the version
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
     * {@inheritdoc}
     */
    public function getUrl($path, $version = null)
    {
        if (false !== strpos($path, '://') || 0 === strpos($path, '//')) {
            return $path;
        }

        $url = $this->applyVersion($path, $version);

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
     */
    public function getBasePath()
    {
        return $this->basePath;
    }
}
