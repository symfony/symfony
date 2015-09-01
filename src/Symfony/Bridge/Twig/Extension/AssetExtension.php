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
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

/**
 * Twig extension for the Symfony Asset component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AssetExtension extends \Twig_Extension
{
    private $packages;
    private $foundationExtension;

    /**
     * Passing an HttpFoundationExtension instance as a second argument must not be relied on
     * as it's only there to maintain BC with older Symfony version. It will be removed in 3.0.
     */
    public function __construct(Packages $packages, HttpFoundationExtension $foundationExtension = null)
    {
        $this->packages = $packages;
        $this->foundationExtension = $foundationExtension;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('asset', array($this, 'getAssetUrl')),
            new \Twig_SimpleFunction('asset_version', array($this, 'getAssetVersion')),
            new \Twig_SimpleFunction('assets_version', array($this, 'getAssetsVersion'), array('deprecated' => true, 'alternative' => 'asset_version')),
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
    public function getAssetUrl($path, $packageName = null, $absolute = false, $version = null)
    {
        // BC layer to be removed in 3.0
        if (2 < $count = func_num_args()) {
            @trigger_error('Generating absolute URLs with the Twig asset() function was deprecated in 2.7 and will be removed in 3.0. Please use absolute_url() instead.', E_USER_DEPRECATED);
            if (4 === $count) {
                @trigger_error('Forcing a version with the Twig asset() function was deprecated in 2.7 and will be removed in 3.0.', E_USER_DEPRECATED);
            }

            $args = func_get_args();

            return $this->getLegacyAssetUrl($path, $packageName, $args[2], isset($args[3]) ? $args[3] : null);
        }

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

    public function getAssetsVersion($packageName = null)
    {
        @trigger_error('The Twig assets_version() function was deprecated in 2.7 and will be removed in 3.0. Please use asset_version() instead.', E_USER_DEPRECATED);

        return $this->packages->getVersion('/', $packageName);
    }

    private function getLegacyAssetUrl($path, $packageName = null, $absolute = false, $version = null)
    {
        if ($version) {
            $package = $this->packages->getPackage($packageName);
            $class = new \ReflectionClass($package);

            while ('Symfony\Component\Asset\Package' !== $class->getName()) {
                $class = $class->getParentClass();
            }

            $v = $class->getProperty('versionStrategy');
            $v->setAccessible(true);
            $currentVersionStrategy = $v->getValue($package);

            if (property_exists($currentVersionStrategy, 'format')) {
                $f = new \ReflectionProperty($currentVersionStrategy, 'format');
                $f->setAccessible(true);
                $format = $f->getValue($currentVersionStrategy);

                $v->setValue($package, new StaticVersionStrategy($version, $format));
            } else {
                $v->setValue($package, new StaticVersionStrategy($version));
            }
        }

        try {
            $url = $this->packages->getUrl($path, $packageName);
        } catch (\Exception $e) {
            if ($version) {
                $v->setValue($package, $currentVersionStrategy);
            }

            throw $e;
        }

        if ($version) {
            $v->setValue($package, $currentVersionStrategy);
        }

        if ($absolute) {
            return $this->foundationExtension->generateAbsoluteUrl($url);
        }

        return $url;
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
