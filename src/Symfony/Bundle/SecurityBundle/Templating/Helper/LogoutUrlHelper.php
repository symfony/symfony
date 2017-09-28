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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
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
     * @param ContainerInterface|LogoutUrlGenerator $generator    A ContainerInterface or LogoutUrlGenerator instance
     * @param UrlGeneratorInterface|null            $router       The router service
     * @param TokenStorageInterface|null            $tokenStorage The token storage service
     *
     * @deprecated Passing a ContainerInterface as a first argument is deprecated since 2.7 and will be removed in 3.0.
     * @deprecated Passing a second and third argument is deprecated since 2.7 and will be removed in 3.0.
     */
    public function __construct($generator, UrlGeneratorInterface $router = null, TokenStorageInterface $tokenStorage = null)
    {
        if ($generator instanceof ContainerInterface) {
            @trigger_error('The '.__CLASS__.' constructor will require a LogoutUrlGenerator instead of a ContainerInterface instance in 3.0.', E_USER_DEPRECATED);

            if ($generator->has('security.logout_url_generator')) {
                $this->generator = $generator->get('security.logout_url_generator');
            } else {
                $this->generator = new LogoutUrlGenerator($generator->get('request_stack'), $router, $tokenStorage);
            }
        } else {
            $this->generator = $generator;
        }
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
