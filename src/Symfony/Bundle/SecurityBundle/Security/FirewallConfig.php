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
final readonly class FirewallConfig
{
    public function __construct(
        private string $name,
        private string $userChecker,
        private ?string $requestMatcher = null,
        private bool $securityEnabled = true,
        private bool $stateless = false,
        private ?string $provider = null,
        private ?string $context = null,
        private ?string $entryPoint = null,
        private ?string $accessDeniedHandler = null,
        private ?string $accessDeniedUrl = null,
        private array $authenticators = [],
        private ?array $switchUser = null,
        private ?array $logout = null,
    ) {
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

    public function getAuthenticators(): array
    {
        return $this->authenticators;
    }

    public function getSwitchUser(): ?array
    {
        return $this->switchUser;
    }

    public function getLogout(): ?array
    {
        return $this->logout;
    }
}
