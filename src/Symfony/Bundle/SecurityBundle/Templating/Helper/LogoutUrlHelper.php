<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Templating\Helper;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Component\Templating\Helper\Helper;

/**
 * LogoutUrlHelper provides generator functions for the logout URL.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class LogoutUrlHelper extends Helper
{
    private $generator;

    /**
     * Constructor.
     *
     * @param LogoutUrlGenerator $generator A LogoutUrlGenerator instance
     */
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
