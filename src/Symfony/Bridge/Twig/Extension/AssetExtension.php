<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\Preload\PreloadManagerInterface;

/**
 * Twig extension for the Symfony Asset component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AssetExtension extends \Twig_Extension
{
    private $packages;
    private $preloadManager;

    public function __construct(Packages $packages, PreloadManagerInterface $preloadManager = null)
    {
        $this->packages = $packages;
        $this->preloadManager = $preloadManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('asset', array($this, 'getAssetUrl')),
            new \Twig_SimpleFunction('asset_version', array($this, 'getAssetVersion')),
            new \Twig_SimpleFunction('preload', array($this, 'preload')),
        );
    }

    /**
     * Returns the public url/path of an asset.
     *
     * If the package used to generate the path is an instance of
     * UrlPackage, you will always get a URL and not a path.
     *
     * @param string $path        A public path
     * @param string $packageName The name of the asset package to use
     *
     * @return string The public path of the asset
     */
    public function getAssetUrl($path, $packageName = null)
    {
        return $this->packages->getUrl($path, $packageName);
    }

    /**
     * Returns the version of an asset.
     *
     * @param string $path        A public path
     * @param string $packageName The name of the asset package to use
     *
     * @return string The asset version
     */
    public function getAssetVersion($path, $packageName = null)
    {
        return $this->packages->getVersion($path, $packageName);
    }

    /**
     * Preloads an asset.
     *
     * @param string $path   A public path
     * @param string $as     A valid destination according to https://fetch.spec.whatwg.org/#concept-request-destination
     * @param bool   $nopush If this asset should not be pushed over HTTP/2
     *
     * @return string The path of the asset
     */
    public function preload($path, $as = '', $nopush = false)
    {
        if (null === $this->preloadManager) {
            throw new \RuntimeException('A preload manager must be configured to use the "preload" function.');
        }

        $this->preloadManager->addResource($path, $as, $nopush);

        return $path;
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'asset';
    }
}
