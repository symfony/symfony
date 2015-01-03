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
use Symfony\Component\Asset\Packages;

/**
 * Twig extension for the Symfony Asset component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AssetExtension extends \Twig_Extension
{
    private $packages;
    private $requestStack;

    public function __construct(Packages $packages, RequestStack $requestStack = null)
    {
        $this->packages = $packages;
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
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
        $url = $this->packages->getUrl($path, $packageName, $version);

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
        return $this->packages->getVersion($packageName);
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

        if (null === $this->requestStack) {
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
