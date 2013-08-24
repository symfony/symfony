<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Twig extension for Symfony assets helper
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class AssetsExtension extends \Twig_Extension
{
    private $container;

    /**
     * @since v2.0.0
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     *
     * @since v2.0.0
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
     * @param string $path        A public path
     * @param string $packageName The name of the asset package to use
     *
     * @return string A public path which takes into account the base path and URL path
     *
     * @since v2.0.0
     */
    public function getAssetUrl($path, $packageName = null)
    {
        return $this->container->get('templating.helper.assets')->getUrl($path, $packageName);
    }

    /**
     * Returns the version of the assets in a package.
     *
     * @param string $packageName
     *
     * @return int
     *
     * @since v2.0.0
     */
    public function getAssetsVersion($packageName = null)
    {
        return $this->container->get('templating.helper.assets')->getVersion($packageName);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     *
     * @since v2.0.0
     */
    public function getName()
    {
        return 'assets';
    }
}
