<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Security;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class FirewallConfig
{
    private $name;
    private $userChecker;
    private $requestMatcher;
    private $securityEnabled;
    private $stateless;
    private $provider;
    private $context;
    private $entryPoint;
    private $accessDeniedHandler;
    private $accessDeniedUrl;
    private $listeners;

    /**
     * @param string      $name
     * @param string      $userChecker
     * @param string|null $requestMatcher
     * @param bool        $securityEnabled
     * @param bool        $stateless
     * @param string|null $provider
     * @param string|null $context
     * @param string|null $entryPoint
     * @param string|null $accessDeniedHandler
     * @param string|null $accessDeniedUrl
     * @param string[]    $listeners
     */
    public function __construct($name, $userChecker, $requestMatcher = null, $securityEnabled = true, $stateless = false, $provider = null, $context = null, $entryPoint = null, $accessDeniedHandler = null, $accessDeniedUrl = null, $listeners = array())
    {
        $this->name = $name;
        $this->userChecker = $userChecker;
        $this->requestMatcher = $requestMatcher;
        $this->securityEnabled = $securityEnabled;
        $this->stateless = $stateless;
        $this->provider = $provider;
        $this->context = $context;
        $this->entryPoint = $entryPoint;
        $this->accessDeniedHandler = $accessDeniedHandler;
        $this->accessDeniedUrl = $accessDeniedUrl;
        $this->listeners = $listeners;
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string|null The request matcher service id or null if neither the request matcher, pattern or host
     *                     options were provided
     */
    public function getRequestMatcher()
    {
        return $this->requestMatcher;
    }

    public function isSecurityEnabled()
    {
        return $this->securityEnabled;
    }

    public function allowsAnonymous()
    {
        return in_array('anonymous', $this->listeners, true);
    }

    public function isStateless()
    {
        return $this->stateless;
    }

    /**
     * @return string|null The provider service id
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * @return string|null The context key (will be null if the firewall is stateless)
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @return string|null The entry_point service id if configured, null otherwise
     */
    public function getEntryPoint()
    {
        return $this->entryPoint;
    }

    /**
     * @return string The user_checker service id
     */
    public function getUserChecker()
    {
        return $this->userChecker;
    }

    /**
     * @return string|null The access_denied_handler service id if configured, null otherwise
     */
    public function getAccessDeniedHandler()
    {
        return $this->accessDeniedHandler;
    }

    /**
     * @return string|null The access_denied_handler URL if configured, null otherwise
     */
    public function getAccessDeniedUrl()
    {
        return $this->accessDeniedUrl;
    }

    /**
     * @return string[] An array of listener keys
     */
    public function getListeners()
    {
        return $this->listeners;
    }
}
