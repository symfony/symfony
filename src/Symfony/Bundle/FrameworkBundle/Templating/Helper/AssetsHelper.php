<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Helper;

use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
use Symfony\Component\Templating\Helper\Helper;

/**
 * AssetsHelper helps manage asset URLs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AssetsHelper extends Helper
{
    private $packages;

    public function __construct(Packages $packages)
    {
        $this->packages = $packages;
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
    public function getUrl($path, $packageName = null, $version = null)
    {
        // BC layer to be removed in 3.0
        if (3 === $count = func_num_args()) {
            @trigger_error('Forcing a version for an asset was deprecated in 2.7 and will be removed in 3.0.', E_USER_DEPRECATED);

            $args = func_get_args();

            return $this->getLegacyAssetUrl($path, $packageName, $args[2]);
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
    public function getVersion($path = null, $packageName = null)
    {
        // no arguments means old getVersion() for default package
        if (null === $path) {
            @trigger_error('The getVersion() method requires a path as a first argument since 2.7 and will be enforced as of 3.0.', E_USER_DEPRECATED);

            return $this->packages->getVersion('/', $packageName);
        }

        // path and packageName can only be for the new version
        if (null !== $packageName) {
            return $this->packages->getVersion($path, $packageName);
        }

        // packageName is null and path not, so path is a path or a packageName
        try {
            $package = $this->packages->getPackage($path);
        } catch (\InvalidArgumentException $e) {
            // path is not a package, so it should be a path
            return $this->packages->getVersion($path);
        }

        // path is a packageName, old version
        @trigger_error('The getVersion() method requires a path as a first argument since 2.7 and will be enforced as of 3.0.', E_USER_DEPRECATED);

        return $this->packages->getVersion('/', $path);
    }

    private function getLegacyAssetUrl($path, $packageName = null, $version = null)
    {
        if ($version) {
            $package = $this->packages->getPackage($packageName);

            $v = new \ReflectionProperty('Symfony\Component\Asset\Package', 'versionStrategy');
            $v->setAccessible(true);

            $currentVersionStrategy = $v->getValue($package);

            $f = new \ReflectionProperty($currentVersionStrategy, 'format');
            $f->setAccessible(true);

            $format = $f->getValue($currentVersionStrategy);

            $v->setValue($package, new StaticVersionStrategy($version, $format));
        }

        $url = $this->packages->getUrl($path, $packageName);

        if ($version) {
            $v->setValue($package, $currentVersionStrategy);
        }

        return $url;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'assets';
    }
}
