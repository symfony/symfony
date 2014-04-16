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
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderAdapter;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Templating\Helper\Helper;

/**
 * LogoutUrlHelper provides generator functions for the logout URL.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class LogoutUrlHelper extends Helper
{
    private $container;
    private $listeners = array();
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
    }

    /**
     * Registers a firewall's LogoutListener, allowing its URL to be generated.
     *
     * @param string                    $key              The firewall key
     * @param string                    $logoutPath       The path that starts the logout process
     * @param string                    $csrfTokenId      The ID of the CSRF token
     * @param string                    $csrfParameter    The CSRF token parameter name
     * @param CsrfTokenManagerInterface $csrfTokenManager A CsrfTokenManagerInterface instance
     */
    public function registerListener($key, $logoutPath, $csrfTokenId, $csrfParameter, $csrfTokenManager = null)
    {
        if ($csrfTokenManager instanceof CsrfProviderInterface) {
            $csrfTokenManager = new CsrfProviderAdapter($csrfTokenManager);
        } elseif (null !== $csrfTokenManager && !$csrfTokenManager instanceof CsrfTokenManagerInterface) {
            throw new \InvalidArgumentException('The CSRF token manager should be an instance of CsrfProviderInterface or CsrfTokenManagerInterface.');
        }

        $this->listeners[$key] = array($logoutPath, $csrfTokenId, $csrfParameter, $csrfTokenManager);
    }

    /**
     * Generates the absolute logout path for the firewall.
     *
     * @param string $key The firewall key
     *
     * @return string The logout path
     */
    public function getLogoutPath($key)
    {
        return $this->generateLogoutUrl($key, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * Generates the absolute logout URL for the firewall.
     *
     * @param string $key The firewall key
     *
     * @return string The logout URL
     */
    public function getLogoutUrl($key)
    {
        return $this->generateLogoutUrl($key, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Generates the logout URL for the firewall.
     *
     * @param string         $key           The firewall key
     * @param bool|string    $referenceType The type of reference (one of the constants in UrlGeneratorInterface)
     *
     * @return string The logout URL
     *
     * @throws \InvalidArgumentException if no LogoutListener is registered for the key
     */
    private function generateLogoutUrl($key, $referenceType)
    {
        if (!array_key_exists($key, $this->listeners)) {
            throw new \InvalidArgumentException(sprintf('No LogoutListener found for firewall key "%s".', $key));
        }

        list($logoutPath, $csrfTokenId, $csrfParameter, $csrfTokenManager) = $this->listeners[$key];

        $parameters = null !== $csrfTokenManager ? array($csrfParameter => (string) $csrfTokenManager->getToken($csrfTokenId)) : array();

        if ('/' === $logoutPath[0]) {
            $request = $this->container->get('request');

            $url = UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? $request->getUriForPath($logoutPath) : $request->getBasePath().$logoutPath;

            if (!empty($parameters)) {
                $url .= '?'.http_build_query($parameters);
            }
        } else {
            $url = $this->router->generate($logoutPath, $parameters, $referenceType);
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
