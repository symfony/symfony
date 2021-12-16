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
    private $authenticators;
    private $switchUser;

    public function __construct(string $name, string $userChecker, string $requestMatcher = null, bool $securityEnabled = true, bool $stateless = false, string $provider = null, string $context = null, string $entryPoint = null, string $accessDeniedHandler = null, string $accessDeniedUrl = null, array $authenticators = [], array $switchUser = null)
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
        $this->authenticators = $authenticators;
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

    /**
     * @deprecated since Symfony 5.4
     */
    public function allowsAnonymous(): bool
    {
        trigger_deprecation('symfony/security-bundle', '5.4', 'The "%s()" method is deprecated.', __METHOD__);

        return \in_array('anonymous', $this->authenticators, true);
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

    /**
     * @deprecated since Symfony 5.4, use {@see getAuthenticators()} instead
     */
    public function getListeners(): array
    {
        trigger_deprecation('symfony/security-bundle', '5.4', 'Method "%s()" is deprecated, use "%s::getAuthenticators()" instead.', __METHOD__, __CLASS__);

        return $this->getAuthenticators();
    }

    public function getAuthenticators(): array
    {
        return $this->authenticators;
    }

    public function getSwitchUser(): ?array
    {
        return $this->switchUser;
    }
}
