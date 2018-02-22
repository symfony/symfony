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
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
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
    private $currentFirewall;

    public function __construct(RequestStack $requestStack = null, UrlGeneratorInterface $router = null, TokenStorageInterface $tokenStorage = null)
    {
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Registers a firewall's LogoutListener, allowing its URL to be generated.
     *
     * @param string                         $key              The firewall key
     * @param string                         $logoutPath       The path that starts the logout process
     * @param string                         $csrfTokenId      The ID of the CSRF token
     * @param string                         $csrfParameter    The CSRF token parameter name
     * @param CsrfTokenManagerInterface|null $csrfTokenManager A CsrfTokenManagerInterface instance
     * @param string|null                    $context          The listener context
     */
    public function registerListener($key, $logoutPath, $csrfTokenId, $csrfParameter, CsrfTokenManagerInterface $csrfTokenManager = null/*, string $context = null*/)
    {
        if (func_num_args() >= 6) {
            $context = func_get_arg(5);
        } else {
            if (__CLASS__ !== get_class($this)) {
                $r = new \ReflectionMethod($this, __FUNCTION__);
                if (__CLASS__ !== $r->getDeclaringClass()->getName()) {
                    @trigger_error(sprintf('Method %s() will have a sixth `string $context = null` argument in version 4.0. Not defining it is deprecated since Symfony 3.3.', __METHOD__), E_USER_DEPRECATED);
                }
            }

            $context = null;
        }

        $this->listeners[$key] = array($logoutPath, $csrfTokenId, $csrfParameter, $csrfTokenManager, $context);
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
     * @param string|null $key     The current firewall key
     * @param string|null $context The current firewall context
     */
    public function setCurrentFirewall($key, $context = null)
    {
        $this->currentFirewall = array($key, $context);
    }

    /**
     * Generates the logout URL for the firewall.
     *
     * @param string|null $key           The firewall key or null to use the current firewall key
     * @param int         $referenceType The type of reference (one of the constants in UrlGeneratorInterface)
     *
     * @return string The logout URL
     */
    private function generateLogoutUrl($key, $referenceType)
    {
        list($logoutPath, $csrfTokenId, $csrfParameter, $csrfTokenManager) = $this->getListener($key);

        if (null === $logoutPath) {
            throw new \LogicException('Unable to generate the logout URL without a path.');
        }

        $parameters = null !== $csrfTokenManager ? array($csrfParameter => (string) $csrfTokenManager->getToken($csrfTokenId)) : array();

        if ('/' === $logoutPath[0]) {
            if (!$this->requestStack) {
                throw new \LogicException('Unable to generate the logout URL without a RequestStack.');
            }

            $request = $this->requestStack->getCurrentRequest();

            $url = UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? $request->getUriForPath($logoutPath) : $request->getBaseUrl().$logoutPath;

            if (!empty($parameters)) {
                $url .= '?'.http_build_query($parameters, '', '&');
            }
        } else {
            if (!$this->router) {
                throw new \LogicException('Unable to generate the logout URL without a Router.');
            }

            $url = $this->router->generate($logoutPath, $parameters, $referenceType);
        }

        return $url;
    }

    /**
     * @param string|null $key The firewall key or null use the current firewall key
     *
     * @return array The logout listener found
     *
     * @throws \InvalidArgumentException if no LogoutListener is registered for the key or could not be found automatically
     */
    private function getListener($key)
    {
        if (null !== $key) {
            if (isset($this->listeners[$key])) {
                return $this->listeners[$key];
            }

            throw new \InvalidArgumentException(sprintf('No LogoutListener found for firewall key "%s".', $key));
        }

        // Fetch the current provider key from token, if possible
        if (null !== $this->tokenStorage) {
            $token = $this->tokenStorage->getToken();

            if ($token instanceof AnonymousToken) {
                throw new \InvalidArgumentException('Unable to generate a logout url for an anonymous token.');
            }

            if (null !== $token && method_exists($token, 'getProviderKey')) {
                $key = $token->getProviderKey();

                if (isset($this->listeners[$key])) {
                    return $this->listeners[$key];
                }
            }
        }

        // Fetch from injected current firewall information, if possible
        list($key, $context) = $this->currentFirewall;

        if (isset($this->listeners[$key])) {
            return $this->listeners[$key];
        }

        foreach ($this->listeners as $listener) {
            if (isset($listener[4]) && $context === $listener[4]) {
                return $listener;
            }
        }

        throw new \InvalidArgumentException('Unable to find the current firewall LogoutListener, please provide the provider key manually.');
    }
}
