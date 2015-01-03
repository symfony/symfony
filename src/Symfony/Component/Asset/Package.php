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
 * Basic package that adds a version to asset URLs.
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

    /**
     * {@inheritdoc}
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl($path, $version = null)
    {
        if ($this->isAbsoluteUrl($path)) {
            return $path;
        }

        return $this->applyVersion($path, $version);
    }

    protected function isAbsoluteUrl($url)
    {
        return false !== strpos($url, '://') || '//' === substr($url, 0, 2);
    }

    /**
     * Applies version to the supplied path.
     *
     * @param string           $path    A path
     * @param string|bool|null $version A specific version
     *
     * @return string The versionized path
     */
    protected function applyVersion($path, $version = null)
    {
        $version = $version ?: $this->version;
        if (!$version) {
            return $path;
        }

        $versionized = sprintf($this->format, ltrim($path, '/'), $version);

        if ($path && '/' == $path[0]) {
            $versionized = '/'.$versionized;
        }

        return $versionized;
    }
}
