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

use Symfony\Bundle\FrameworkBundle\Templating\Helper\AssetsHelper;

/**
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplatingExtension extends \Twig_Extension
{
    protected $helper;

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
        );
    }

    public function getAssetUrl($location)
    {
        return $this->helper->getUrl($location);
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
