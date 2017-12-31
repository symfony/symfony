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

@trigger_error('The '.__NAMESPACE__.'\LogoutUrlExtension class is deprecated since Symfony 2.7 and will be removed in 3.0. Use Symfony\Bridge\Twig\Extension\LogoutUrlExtension instead.', E_USER_DEPRECATED);

use Symfony\Bundle\SecurityBundle\Templating\Helper\LogoutUrlHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * LogoutUrlHelper provides generator functions for the logout URL to Twig.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 *
 * @deprecated since version 2.7, to be removed in 3.0. Use Symfony\Bridge\Twig\Extension\LogoutUrlExtension instead.
 */
class LogoutUrlExtension extends AbstractExtension
{
    private $helper;

    public function __construct(LogoutUrlHelper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new TwigFunction('logout_url', array($this, 'getLogoutUrl')),
            new TwigFunction('logout_path', array($this, 'getLogoutPath')),
        );
    }

    /**
     * Generates the relative logout URL for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     *
     * @return string The relative logout URL
     */
    public function getLogoutPath($key = null)
    {
        return $this->helper->getLogoutPath($key);
    }

    /**
     * Generates the absolute logout URL for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     *
     * @return string The absolute logout URL
     */
    public function getLogoutUrl($key = null)
    {
        return $this->helper->getLogoutUrl($key);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'logout_url';
    }
}
