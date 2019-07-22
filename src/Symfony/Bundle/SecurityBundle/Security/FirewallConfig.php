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
    private $switchUser;

    public function __construct(string $name, string $userChecker, string $requestMatcher = null, bool $securityEnabled = true, bool $stateless = false, string $provider = null, string $context = null, string $entryPoint = null, string $accessDeniedHandler = null, string $accessDeniedUrl = null, array $listeners = [], $switchUser = null)
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
        $this->switchUser = $switchUser;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string|null The request matcher service id or null if neither the request matcher, pattern or host
     *                     options were provided
     */
    public function getRequestMatcher(): ?string
    {
        return $this->requestMatcher;
    }

    public function isSecurityEnabled(): bool
    {
        return $this->securityEnabled;
    }

    public function allowsAnonymous(): bool
    {
        return \in_array('anonymous', $this->listeners, true);
    }

    public function isStateless(): bool
    {
        return $this->stateless;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * @return string|null The context key (will be null if the firewall is stateless)
     */
    public function getContext(): ?string
    {
        return $this->context;
    }

    public function getEntryPoint(): ?string
    {
        return $this->entryPoint;
    }

    public function getUserChecker(): string
    {
        return $this->userChecker;
    }

    public function getAccessDeniedHandler(): ?string
    {
        return $this->accessDeniedHandler;
    }

    public function getAccessDeniedUrl(): ?string
    {
        return $this->accessDeniedUrl;
    }

    public function getListeners(): array
    {
        return $this->listeners;
    }

    public function getSwitchUser(): ?array
    {
        return $this->switchUser;
    }
}
