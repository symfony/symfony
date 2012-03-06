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
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Templating\Helper\Helper;

/**
 * LogoutUrlHelper provides generator functions for the logout URL.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class LogoutUrlHelper extends Helper
{
    private $container;
    private $listeners;
    private $router;

    /**
     * Constructor.
     *
     * @param ContainerInterface    $container A ContainerInterface instance
     * @param UrlGeneratorInterface $router    A Router instance
     */
    public function __construct(ContainerInterface $container, UrlGeneratorInterface $router)
    {
        $this->container = $container;
        $this->router = $router;
        $this->listeners = array();
    }

    /**
     * Registers a firewall's LogoutListener, allowing its URL to be generated.
     *
     * @param string                $key           The firewall key
     * @param string                $logoutPath    The path that starts the logout process
     * @param string                $intention     The intention for CSRF token generation
     * @param string                $csrfParameter The CSRF token parameter name
     * @param CsrfProviderInterface $csrfProvider  A CsrfProviderInterface instance
     */
    public function registerListener($key, $logoutPath, $intention, $csrfParameter, CsrfProviderInterface $csrfProvider = null)
    {
        $this->listeners[$key] = array($logoutPath, $intention, $csrfParameter, $csrfProvider);
    }

    /**
     * Generate the relative logout URL for the firewall.
     *
     * @param string $key The firewall key
     * @return string The relative logout URL
     */
    public function getLogoutPath($key)
    {
        return $this->generateLogoutUrl($key, false);
    }

    /**
     * Generate the absolute logout URL for the firewall.
     *
     * @param string $key The firewall key
     * @return string The absolute logout URL
     */
    public function getLogoutUrl($key)
    {
        return $this->generateLogoutUrl($key, true);
    }

    /**
     * Generate the logout URL for the firewall.
     *
     * @param string  $key      The firewall key
     * @param Boolean $absolute Whether to generate an absolute URL
     * @return string The logout URL
     * @throws InvalidArgumentException if no LogoutListener is registered for the key
     */
    private function generateLogoutUrl($key, $absolute)
    {
        if (!array_key_exists($key, $this->listeners)) {
            throw new \InvalidArgumentException(sprintf('No LogoutListener found for firewall key "%s".', $key));
        }

        list($logoutPath, $intention, $csrfParameter, $csrfProvider) = $this->listeners[$key];

        $parameters = null !== $csrfProvider ? array($csrfParameter => $csrfProvider->generateCsrfToken($intention)) : array();

        if ('/' === $logoutPath[0]) {
            $request = $this->container->get('request');

            $url = ($absolute ? $request->getUriForPath($logoutPath) : $request->getBasePath() . $logoutPath);

            if (!empty($parameters)) {
                $url .= '?' . http_build_query($parameters);
            }
        } else {
            $url = $this->router->generate($logoutPath, $parameters, $absolute);
        }

        return $url;
    }

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName()
    {
        return 'logout_url';
    }
}
