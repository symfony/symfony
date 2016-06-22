<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Logout;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Provides generator functions for the logout URL.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class LogoutUrlGenerator
{
    private $requestStack;
    private $router;
    private $tokenStorage;
    private $listeners = array();

    public function __construct(RequestStack $requestStack = null, UrlGeneratorInterface $router = null, TokenStorageInterface $tokenStorage = null)
    {
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->tokenStorage = $tokenStorage;
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
    public function registerListener($key, $logoutPath, $csrfTokenId, $csrfParameter, CsrfTokenManagerInterface $csrfTokenManager = null)
    {
        $this->listeners[$key] = array($logoutPath, $csrfTokenId, $csrfParameter, $csrfTokenManager);
    }

    /**
     * Generates the absolute logout path for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     *
     * @return string The logout path
     */
    public function getLogoutPath($key = null)
    {
        return $this->generateLogoutUrl($key, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * Generates the absolute logout URL for the firewall.
     *
     * @param string|null $key The firewall key or null to use the current firewall key
     *
     * @return string The logout URL
     */
    public function getLogoutUrl($key = null)
    {
        return $this->generateLogoutUrl($key, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Generates the logout URL for the firewall.
     *
     * @param string|null $key           The firewall key or null to use the current firewall key
     * @param int         $referenceType The type of reference (one of the constants in UrlGeneratorInterface)
     *
     * @return string The logout URL
     *
     * @throws \InvalidArgumentException if no LogoutListener is registered for the key or the key could not be found automatically.
     */
    private function generateLogoutUrl($key, $referenceType)
    {
        // Fetch the current provider key from token, if possible
        if (null === $key && null !== $this->tokenStorage) {
            $token = $this->tokenStorage->getToken();
            if (null !== $token && method_exists($token, 'getProviderKey')) {
                $key = $token->getProviderKey();
            }
        }

        if (null === $key) {
            throw new \InvalidArgumentException('Unable to find the current firewall LogoutListener, please provide the provider key manually.');
        }

        if (!array_key_exists($key, $this->listeners)) {
            throw new \InvalidArgumentException(sprintf('No LogoutListener found for firewall key "%s".', $key));
        }

        list($logoutPath, $csrfTokenId, $csrfParameter, $csrfTokenManager) = $this->listeners[$key];

        $parameters = null !== $csrfTokenManager ? array($csrfParameter => (string) $csrfTokenManager->getToken($csrfTokenId)) : array();

        if ('/' === $logoutPath[0]) {
            if (!$this->requestStack) {
                throw new \LogicException('Unable to generate the logout URL without a RequestStack.');
            }

            $request = $this->requestStack->getCurrentRequest();

            $url = UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? $request->getUriForPath($logoutPath) : $request->getBaseUrl().$logoutPath;

            if (!empty($parameters)) {
                $url .= '?'.http_build_query($parameters);
            }
        } else {
            if (!$this->router) {
                throw new \LogicException('Unable to generate the logout URL without a Router.');
            }

            $url = $this->router->generate($logoutPath, $parameters, $referenceType);
        }

        return $url;
    }
}
