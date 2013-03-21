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

    public function getUrl($path)
    {
        if (false !== strpos($path, '://') || 0 === strpos($path, '//')) {
            return $path;
        }

        $path = $this->applyRegex($path);

        $url = $this->applyVersion($path);

        // apply the base path
        if ('/' !== substr($url, 0, 1)) {
            $url = $this->basePath.$url;
        }

        return $url;
    }

    /**
     * Returns assets path which matches by $path simple regex.
     * Applies to paths with .js or .css ending and * suffix.
     *
     * For example :
     *     js/jquery*.js => js/jquery-1.8.0.js (jquery-1.8.0.js should be in the /web/js folder)
     *
     * @param string   $path      A path
     * @param callback $globFunc Optional callback to use as php glob function, defaults to glob($pattern)
     *
     * @return string The regex matched path
     */
    public function applyRegex($path, $globFunc = null)
    {
        if (!is_callable($globFunc)) {
            $globFunc = function ($pattern) { return glob($pattern); };
        }

        if (substr_count($path, '*') === 1 && preg_match("/.(js|css)$/i", $path) && $files = $globFunc($path)) {
            $path = $files[0];
        }

        return $path;
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
