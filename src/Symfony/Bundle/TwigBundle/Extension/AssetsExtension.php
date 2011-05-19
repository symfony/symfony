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

use Symfony\Bundle\FrameworkBundle\Templating\Helper\AssetsHelper;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class AssetsExtension extends \Twig_Extension
{
    /**
     * @var Symfony\Bundle\FrameworkBundle\Templating\Helper\AssetsHelper
     */
    private $helper;

    public function __construct(AssetsHelper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
            'asset' => new \Twig_Function_Method($this, 'getAssetUrl'),
            'assets_version' => new \Twig_Function_Method($this, 'getAssetsVersion'),
        );
    }

    /**
     * Returns the public path of an asset
     *
     * Absolute paths (i.e. http://...) are returned unmodified.
     *
     * @param string $path        A public path
     * @param string $packageName The name of the asset package to use
     *
     * @return string A public path which takes into account the base path and URL path
     */
    public function getAssetUrl($path, $packageName = null)
    {
        return $this->helper->getUrl($path, $packageName);
    }

    /**
     * Returns the version of the assets in a package
     *
     * @param string $packageName
     * @return int
     */
    public function getAssetsVersion($packageName = null)
    {
        return $this->helper->getVersion($packageName);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'assets';
    }
}
