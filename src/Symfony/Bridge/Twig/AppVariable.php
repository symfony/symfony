<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Exposes some Symfony parameters and services as an "app" global variable.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AppVariable
{
    private TokenStorageInterface $tokenStorage;
    private RequestStack $requestStack;
    private string $environment;
    private bool $debug;
    private LocaleSwitcher $localeSwitcher;
    private array $enabledLocales;

    public function setTokenStorage(TokenStorageInterface $tokenStorage): void
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
    }

    public function setEnvironment(string $environment): void
    {
        $this->environment = $environment;
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    public function setLocaleSwitcher(LocaleSwitcher $localeSwitcher): void
    {
        $this->localeSwitcher = $localeSwitcher;
    }

    public function setEnabledLocales(array $enabledLocales): void
    {
        $this->enabledLocales = $enabledLocales;
    }

    /**
     * Returns the current token.
     *
     * @throws \RuntimeException When the TokenStorage is not available
     */
    public function getToken(): ?TokenInterface
    {
        if (!isset($this->tokenStorage)) {
            throw new \RuntimeException('The "app.token" variable is not available.');
        }

        return $this->tokenStorage->getToken();
    }

    /**
     * Returns the current user.
     *
     * @see TokenInterface::getUser()
     */
    public function getUser(): ?UserInterface
    {
        if (!isset($this->tokenStorage)) {
            throw new \RuntimeException('The "app.user" variable is not available.');
        }

        return $this->tokenStorage->getToken()?->getUser();
    }

    /**
     * Returns the current request.
     */
    public function getRequest(): ?Request
    {
        if (!isset($this->requestStack)) {
            throw new \RuntimeException('The "app.request" variable is not available.');
        }

        return $this->requestStack->getCurrentRequest();
    }

    /**
     * Returns the current session.
     */
    public function getSession(): ?SessionInterface
    {
        if (!isset($this->requestStack)) {
            throw new \RuntimeException('The "app.session" variable is not available.');
        }
        $request = $this->getRequest();

        return $request?->hasSession() ? $request->getSession() : null;
    }

    /**
     * Returns the current app environment.
     */
    public function getEnvironment(): string
    {
        if (!isset($this->environment)) {
            throw new \RuntimeException('The "app.environment" variable is not available.');
        }

        return $this->environment;
    }

    /**
     * Returns the current app debug mode.
     */
    public function getDebug(): bool
    {
        if (!isset($this->debug)) {
            throw new \RuntimeException('The "app.debug" variable is not available.');
        }

        return $this->debug;
    }

    public function getLocale(): string
    {
        if (!isset($this->localeSwitcher)) {
            throw new \RuntimeException('The "app.locale" variable is not available.');
        }

        return $this->localeSwitcher->getLocale();
    }

    public function getEnabled_locales(): array
    {
        if (!isset($this->enabledLocales)) {
            throw new \RuntimeException('The "app.enabled_locales" variable is not available.');
        }

        return $this->enabledLocales;
    }

    /**
     * Returns some or all the existing flash messages:
     *  * getFlashes() returns all the flash messages
     *  * getFlashes('notice') returns a simple array with flash messages of that type
     *  * getFlashes(['notice', 'error']) returns a nested array of type => messages.
     */
    public function getFlashes(string|array|null $types = null): array
    {
        try {
            $session = $this->getSession();
        } catch (\RuntimeException) {
            return [];
        }

        if (!$session instanceof FlashBagAwareSessionInterface) {
            return [];
        }

        if (null === $types || '' === $types || [] === $types) {
            return $session->getFlashBag()->all();
        }

        if (\is_string($types)) {
            return $session->getFlashBag()->get($types);
        }

        $result = [];
        foreach ($types as $type) {
            $result[$type] = $session->getFlashBag()->get($type);
        }

        return $result;
    }

    public function getCurrent_route(): ?string
    {
        if (!isset($this->requestStack)) {
            throw new \RuntimeException('The "app.current_route" variable is not available.');
        }

        return $this->getRequest()?->attributes->get('_route');
    }

    /**
     * @return array<string, mixed>
     */
    public function getCurrent_route_parameters(): array
    {
        if (!isset($this->requestStack)) {
            throw new \RuntimeException('The "app.current_route_parameters" variable is not available.');
        }

        return $this->getRequest()?->attributes->get('_route_params') ?? [];
    }
}
