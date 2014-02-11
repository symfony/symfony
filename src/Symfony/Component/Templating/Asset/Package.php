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
 * The basic package will add a version to asset URLs.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class Package implements PackageInterface
{
    private $version;
    private $format;

    /**
     * Constructor.
     *
     * @param string $version The package version
     * @param string $format  The format used to apply the version
     */
    public function __construct($version = null, $format = '')
    {
        $this->version = $version;
        $this->format = $format ?: '%s?%s';
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getUrl($path)
    {
        if (false !== strpos($path, '://') || 0 === strpos($path, '//')) {
            return $path;
        }

        return $this->applyVersion($path);
    }

    /**
     * Applies version to the supplied path.
     *
     * @param string $path A path
     *
     * @return string The versionized path
     */
    protected function applyVersion($path)
    {
        if (null === $this->version) {
            return $path;
        }

        $versionized = sprintf($this->format, ltrim($path, '/'), $this->version);

        if ($path && '/' == $path[0]) {
            $versionized = '/'.$versionized;
        }

        return $versionized;
    }
}
