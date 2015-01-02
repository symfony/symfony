<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Twig\Extension;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Templating\Asset\PackageInterface;

/**
 * Twig extension for Symfony asset packages.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AssetPackagesExtension extends \Twig_Extension
{
    private $defaultPackage;
    private $namedPackages = array();
    private $requestStack;

    /**
     * @param PackageInterface $defaultPackage The default package
     * @param array            $namedPackages  Additional packages indexed by name
     */
    public function __construct(PackageInterface $defaultPackage, array $namedPackages = array(), RequestStack $requestStack = null)
    {
        $this->defaultPackage = $defaultPackage;

        foreach ($namedPackages as $name => $package) {
            $this->addPackage($name, $package);
        }

        $this->requestStack = $requestStack;
    }

    /**
     * Sets the default package.
     *
     * @param PackageInterface $defaultPackage The default package
     */
    public function setDefaultPackage(PackageInterface $defaultPackage)
    {
        $this->defaultPackage = $defaultPackage;
    }

    /**
     * Adds an asset package to the helper.
     *
     * @param string           $name    The package name
     * @param PackageInterface $package The package
     */
    public function addPackage($name, PackageInterface $package)
    {
        $this->namedPackages[$name] = $package;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('asset', array($this, 'getAssetUrl')),
            new \Twig_SimpleFunction('assets_version', array($this, 'getAssetsVersion')),
        );
    }

    /**
     * Returns the public path of an asset.
     *
     * Absolute paths (i.e. http://...) are returned unmodified.
     *
     * @param string           $path        A public path
     * @param string           $packageName The name of the asset package to use
     * @param bool             $absolute    Whether to return an absolute URL or a relative one
     * @param string|bool|null $version     A specific version
     *
     * @return string A public path which takes into account the base path and URL path
     */
    public function getAssetUrl($path, $packageName = null, $absolute = false, $version = null)
    {
        $url = $this->getPackage($packageName)->getUrl($path, $version);

        if (!$absolute) {
            return $url;
        }

        return $this->ensureUrlIsAbsolute($url);
    }

    /**
     * Returns the version of the assets in a package.
     *
     * @param string $packageName
     *
     * @return int
     */
    public function getAssetsVersion($packageName = null)
    {
        return $this->getPackage($packageName)->getVersion();
    }

    /**
     * Returns an asset package.
     *
     * @param string $name The name of the package or null for the default package
     *
     * @return PackageInterface An asset package
     *
     * @throws \InvalidArgumentException If there is no package by that name
     */
    private function getPackage($name = null)
    {
        if (null === $name) {
            return $this->defaultPackage;
        }

        if (!isset($this->namedPackages[$name])) {
            throw new \InvalidArgumentException(sprintf('There is no "%s" asset package.', $name));
        }

        return $this->namedPackages[$name];
    }

    /**
     * Ensures an URL is absolute, if possible.
     *
     * @param string $url The URL that has to be absolute
     *
     * @throws \RuntimeException
     *
     * @return string The absolute URL
     */
    private function ensureUrlIsAbsolute($url)
    {
        if (false !== strpos($url, '://') || 0 === strpos($url, '//')) {
            return $url;
        }

        if (!$this->requestStack) {
            throw new \RuntimeException('To generate an absolute URL for an asset, the Symfony Routing component is required.');
        }

        return $this->requestStack->getMasterRequest()->getUriForPath($url);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'asset_packages';
    }
}
