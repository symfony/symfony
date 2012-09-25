<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Twig\Extension;

use Symfony\Bundle\SecurityBundle\Templating\Helper\LogoutUrlHelper;

/**
 * LogoutUrlHelper provides generator functions for the logout URL to Twig.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class LogoutUrlExtension extends \Twig_Extension
{
    private $helper;

    /**
     * Constructor.
     *
     * @param LogoutUrlHelper $helper
     */
    public function __construct(LogoutUrlHelper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @see Twig_Extension::getFunctions()
     */
    public function getFunctions()
    {
        return array(
            'logout_url'  => new \Twig_Function_Method($this, 'getLogoutUrl'),
            'logout_path' => new \Twig_Function_Method($this, 'getLogoutPath'),
        );
    }

    /**
     * Generate the relative logout URL for the firewall.
     *
     * @param string $key The firewall key
     * @return string The relative logout URL
     */
    public function getLogoutPath($key)
    {
        return $this->helper->getLogoutPath($key);
    }

    /**
     * Generate the absolute logout URL for the firewall.
     *
     * @param string $key The firewall key
     * @return string The absolute logout URL
     */
    public function getLogoutUrl($key)
    {
        return $this->helper->getLogoutUrl($key);
    }

    /**
     * @see Twig_ExtensionInterface::getName()
     */
    public function getName()
    {
        return 'logout_url';
    }
}
