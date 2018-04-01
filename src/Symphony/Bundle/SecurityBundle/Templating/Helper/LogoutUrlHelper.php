<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Templating\Helper;

use Symphony\Component\Routing\Generator\UrlGeneratorInterface;
use Symphony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symphony\Component\Templating\Helper\Helper;

/**
 * LogoutUrlHelper provides generator functions for the logout URL.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class LogoutUrlHelper extends Helper
{
    private $generator;

    public function __construct(LogoutUrlGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Generates the absolute logout path for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     *
     * @return string The logout path
     */
    public function getLogoutPath($key)
    {
        return $this->generator->getLogoutPath($key, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * Generates the absolute logout URL for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     *
     * @return string The logout URL
     */
    public function getLogoutUrl($key)
    {
        return $this->generator->getLogoutUrl($key, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'logout_url';
    }
}
