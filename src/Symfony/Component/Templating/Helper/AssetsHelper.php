<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Helper;

/**
 * The assets helper.
 *
 * Usage:
 *
 *     <img src="<?php echo $view['assets']->getUrl('foo.png') ?>" />
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AssetsHelper extends Helper
{
    const DEFAULT_PACKAGE = 'default';

    protected $basePath;
    protected $baseUrls;
    protected $versions;

    /**
     * Constructor.
     *
     * @param string       $basePath The base path
     * @param string|array $baseURLs The domain URL or an array of domain URLs
     * @param string|array $versions The version or array of package versions
     */
    public function __construct($basePath = null, $baseUrls = array(), $versions = array())
    {
        if (!is_array($versions)) {
            $versions = array(self::DEFAULT_PACKAGE => $versions);
        }

        $this->versions = $versions;
        $this->baseUrls = array();

        $this->setBasePath($basePath);
        $this->addBaseUrlPackages((array) $baseUrls);
    }

    /**
     * Sets the base path.
     *
     * @param string $basePath The base path
     */
    public function setBasePath($basePath)
    {
        if (strlen($basePath) && '/' != $basePath[0]) {
            $basePath = '/'.$basePath;
        }

        if ('/' != substr($basePath, -1)) {
            $basePath .= '/';
        }

        $this->basePath = $basePath;
    }

    /**
     * Adds packages of base URLs.
     *
     * @param array $packages The domain URL or an array of domain URLs
     */
    public function addBaseUrlPackages(array $packages)
    {
        // ensure urls are grouped by package
        if (isset($packages[0])) {
            $packages = array(self::DEFAULT_PACKAGE => $packages);
        }

        foreach ($packages as $package => $baseUrls) {
            $this->baseUrls[$package] = array();
            foreach ((array) $baseUrls as $baseUrl) {
                $this->baseUrls[$package][] = rtrim($baseUrl, '/');
            }
        }
    }

    /**
     * Gets the base path.
     *
     * @return string The base path
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * Gets the base URL.
     *
     * If multiple base URLs have been defined a random one will be picked
     * for each asset.
     *
     * In other words: for one asset path the same base URL will always be
     * picked among the available base URLs.
     *
     * @param string $path    The path
     * @param string $package The asset package
     *
     * @return string The base URL
     */
    public function getBaseUrl($path, $package = self::DEFAULT_PACKAGE)
    {
        if (!isset($this->baseUrls[$package])) {
            return '';
        }

        $baseUrls = $this->baseUrls[$package];

        if (1 == $count = count($baseUrls)) {
            return $baseUrls[0];
        }

        return $baseUrls[fmod(hexdec(substr(md5($path), 0, 10)), $count)];
    }

    /**
     * Gets the version to add to public URL.
     *
     * @param string $package A package name
     *
     * @return string The current version
     */
    public function getVersion($package = self::DEFAULT_PACKAGE)
    {
        if (isset($this->versions[$package])) {
            return $this->versions[$package];
        }
    }

    /**
     * Returns the public path.
     *
     * Absolute paths (i.e. http://...) and protocol-relative paths
     * (i.e. //...) are returned unmodified.
     *
     * @param string $path        A public path
     * @param string $package The name of the asset package to use
     *
     * @return string A public path which takes into account the base path and URL path
     */
    public function getUrl($path, $package = self::DEFAULT_PACKAGE)
    {
        if (false !== strpos($path, '://') || 0 === strpos($path, '//')) {
            return $path;
        }

        $base = $this->getBaseUrl($path, $package);
        $version = $this->getVersion($package);

        if (0 !== strpos($path, '/')) {
            $path = $base ? '/'.$path : $this->basePath.$path;
        }

        return $base.$path.($version ? '?'.$version : '');
    }

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName()
    {
        return 'assets';
    }
}
