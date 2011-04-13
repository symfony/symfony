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

use Symfony\Component\Templating\Asset\AssetPackage;
use Symfony\Component\Templating\Asset\AssetPackageInterface;

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
    protected $packages;

    /**
     * Constructor.
     *
     * @param string       $basePath The base path
     * @param string|array $baseURLs The default domain URL(s)
     * @param string       $version  The default version string
     * @param array        $packages An array of packages
     */
    public function __construct($basePath = null, $baseUrls = null, $version = null, array $packages = array())
    {
        if (!isset($packages[self::DEFAULT_PACKAGE])) {
            $packages[self::DEFAULT_PACKAGE] = new AssetPackage();
        }

        if (null !== $baseUrls) {
            $packages[self::DEFAULT_PACKAGE]->setBaseUrls((array) $baseUrls);
        }

        if (null !== $version) {
            $packages[self::DEFAULT_PACKAGE]->setVersion($version);
        }

        $this->setBasePath($basePath);

        $this->packages = array();
        foreach ($packages as $name => $package) {
            $this->addPackage($name, $package);
        }
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
     * Gets the base path.
     *
     * @return string The base path
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * Adds an asset package.
     *
     * @param string                $name    The package name
     * @param AssetPackageInterface $package The package
     */
    public function addPackage($name, AssetPackageInterface $package)
    {
        $this->packages[$name] = $package;
    }

    /**
     * Returns an asset package.
     *
     * @param string $name The package name
     *
     * @return AssetPackage The package
     *
     * @throws InvalidArgumentException If the package does not exist
     */
    public function getPackage($name = self::DEFAULT_PACKAGE)
    {
        if (!isset($this->packages[$name])) {
            throw new \InvalidArgumentException(sprintf('There is no "%s" asset package.', $name));
        }

        return $this->packages[$name];
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
     * @param string $path        The path
     * @param string $packageName The asset package name
     *
     * @return string The base URL
     */
    public function getBaseUrl($path, $packageName = self::DEFAULT_PACKAGE)
    {
        return $this->getPackage($packageName)->getBaseUrl($path);
    }

    /**
     * Gets the version to add to public URL.
     *
     * @param string $package A package name
     *
     * @return string The current version
     */
    public function getVersion($packageName = self::DEFAULT_PACKAGE)
    {
        return $this->getPackage($packageName)->getVersion();
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
    public function getUrl($path, $packageName = self::DEFAULT_PACKAGE)
    {
        if (false !== strpos($path, '://') || 0 === strpos($path, '//')) {
            return $path;
        }

        $package = $this->getPackage($packageName);
        $base = $package->getBaseUrl($path);
        $path = $package->versionize($path);

        if (0 !== strpos($path, '/')) {
            $path = $base ? '/'.$path : $this->basePath.$path;
        }

        return $base.$path;
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
