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
 * AssetsHelper is the base class for all helper classes that manages assets.
 *
 * Usage:
 *
 * <code>
 *   <img src="<?php echo $view['assets']->getUrl('foo.png') ?>" />
 * </code>
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AssetsHelper extends Helper implements AssetPackageInterface
{
    protected $version;
    protected $defaultPackage;
    protected $packages;

    /**
     * Constructor.
     *
     * @param string       $basePath The base path
     * @param string|array $baseURLs The domain URL or an array of domain URLs
     * @param string       $version  The version
     * @param array        $packages Asset packages indexed by name
     */
    public function __construct($basePath = null, $baseUrls = array(), $version = null, $packages = array())
    {
        $this->setBasePath($basePath);
        $this->defaultPackage = new AssetPackage($baseUrls, $version);
        $this->packages = array();

        foreach ($packages as $name => $package) {
            $this->setPackage($name, $package);
        }
    }

    /**
     * Adds an asset package to the helper.
     *
     * @param string                $name    The package name
     * @param AssetPackageInterface $package The package
     */
    public function setPackage($name, AssetPackageInterface $package)
    {
        $this->packages[$name] = $package;
    }

    /**
     * Returns an asset package.
     *
     * @param string $name The name of the package or null for the default package
     *
     * @return AssetPackageInterface An asset package
     *
     * @throws InvalidArgumentException If there is no package by that name
     */
    public function getPackage($name = null)
    {
        if (null === $name) {
            return $this->defaultPackage;
        }

        if (!isset($this->packages[$name])) {
            throw new \InvalidArgumentException(sprintf('There is no "%s" asset package.', $name));
        }

        return $this->packages[$name];
    }

    /**
     * Gets the version to add to public URL.
     *
     * @param string $package A package name
     *
     * @return string The current version
     */
    public function getVersion($packageName = null)
    {
        return $this->getPackage($packageName)->getVersion();
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
     * Sets the base path.
     *
     * @param string $basePath The base path
     */
    public function setBasePath($basePath)
    {
        if (strlen($basePath) && '/' != $basePath[0]) {
            $basePath = '/'.$basePath;
        }

        $this->basePath = rtrim($basePath, '/').'/';
    }

    /**
     * Gets the base URL.
     *
     * If multiple base URLs have been defined a random one will be picked for each asset.
     * In other words: for one asset path the same base URL will always be picked among the available base URLs.
     *
     * @param  string $path The path
     *
     * @return string The base URL
     */
    public function getBaseUrl($path, $packageName = null)
    {
        return $this->getPackage($packageName)->getBaseUrl($path);
    }

    /**
     * Returns the public path.
     *
     * Absolute paths (i.e. http://...) are returned unmodified.
     *
     * @param string $path        A public path
     * @param string $packageName The name of the asset package to use
     *
     * @return string A public path which takes into account the base path and URL path
     */
    public function getUrl($path, $packageName = null)
    {
        if (false !== strpos($path, '://') || 0 === strpos($path, '//')) {
            return $path;
        }

        $package = $this->getPackage($packageName);
        $base    = $package->getBaseUrl($path);
        $version = $package->getVersion();

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
