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
use Symfony\Contracts\Service\ResetInterface;

/**
 * Provides generator functions for the logout URL.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class LogoutUrlGenerator implements ResetInterface
{
    private array $listeners = [];
    private ?string $currentFirewallName = null;
    private ?string $currentFirewallContext = null;

    public function __construct(
        private ?RequestStack $requestStack = null,
        private ?UrlGeneratorInterface $router = null,
        private ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    /**
     * Registers a firewall's LogoutListener, allowing its URL to be generated.
     *
     * @param string      $key           The firewall key
     * @param string      $logoutPath    The path that starts the logout process
     * @param string|null $csrfTokenId   The ID of the CSRF token
     * @param string|null $csrfParameter The CSRF token parameter name
     * @param string|null $context       The listener context
     */
    public function registerListener(string $key, string $logoutPath, ?string $csrfTokenId, ?string $csrfParameter, ?CsrfTokenManagerInterface $csrfTokenManager = null, ?string $context = null): void
    {
        $this->listeners[$key] = [$logoutPath, $csrfTokenId, $csrfParameter, $csrfTokenManager, $context];
    }

    /**
     * Generates the absolute logout path for the firewall.
     */
    public function getLogoutPath(?string $key = null): string
    {
        return $this->generateLogoutUrl($key, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * Generates the absolute logout URL for the firewall.
     */
    public function getLogoutUrl(?string $key = null): string
    {
        return $this->generateLogoutUrl($key, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Returns the action and the hidden fields of a form triggering the logout.
     *
     * Unlike the URLs, this keeps the CSRF token out of the address bar and suits a
     * logout endpoint restricted to POST requests.
     *
     * @return array{action: string, fields: array<string, string>}
     */
    public function getLogoutForm(?string $key = null): array
    {
        [$action, $fields] = $this->buildParts($key, UrlGeneratorInterface::ABSOLUTE_PATH);

        return ['action' => $action, 'fields' => $fields];
    }

    public function setCurrentFirewall(?string $key, ?string $context = null): void
    {
        $this->currentFirewallName = $key;
        $this->currentFirewallContext = $context;
    }

    /**
     * Generates the logout URL for the firewall.
     */
    private function generateLogoutUrl(?string $key, int $referenceType): string
    {
        [$url, $parameters] = $this->buildParts($key, $referenceType);

        if (!$parameters) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($parameters, '', '&');
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function buildParts(?string $key, int $referenceType): array
    {
        [$logoutPath, $csrfTokenId, $csrfParameter, $csrfTokenManager] = $this->getListener($key);

        if (null === $logoutPath) {
            throw new \LogicException('Unable to generate the logout URL without a path.');
        }

        $parameters = null !== $csrfTokenManager ? [$csrfParameter => (string) $csrfTokenManager->getToken($csrfTokenId)] : [];

        if ('/' !== $logoutPath[0]) {
            if (!$this->router) {
                throw new \LogicException('Unable to generate the logout URL without a Router.');
            }

            // the route may take the parameters as placeholders, only the others are left over
            return self::splitQuery($this->router->generate($logoutPath, $parameters, $referenceType));
        }

        if (!$this->requestStack) {
            throw new \LogicException('Unable to generate the logout URL without a RequestStack.');
        }

        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            throw new \LogicException('Unable to generate the logout URL without a Request.');
        }

        $url = UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? $request->getUriForPath($logoutPath) : $request->getBaseUrl().$logoutPath;

        return [$url, $parameters];
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private static function splitQuery(string $url): array
    {
        [$url, $query] = explode('?', $url, 2) + ['', ''];
        $parameters = [];

        // parse_str() cannot be used here as it turns dots and spaces in parameter names into underscores
        foreach ('' === $query ? [] : explode('&', $query) as $pair) {
            [$name, $value] = explode('=', $pair, 2) + ['', ''];
            $parameters[urldecode($name)] = urldecode($value);
        }

        return [$url, $parameters];
    }

    /**
     * @throws \InvalidArgumentException if no LogoutListener is registered for the key or could not be found automatically
     */
    private function getListener(?string $key): array
    {
        if (null !== $key) {
            if (isset($this->listeners[$key])) {
                return $this->listeners[$key];
            }

            throw new \InvalidArgumentException(\sprintf('No LogoutListener found for firewall key "%s".', $key));
        }

        // Fetch the current provider key from token, if possible
        if (null !== $this->tokenStorage) {
            $token = $this->tokenStorage->getToken();

            if (null !== $token && method_exists($token, 'getFirewallName')) {
                $key = $token->getFirewallName();

                if (isset($this->listeners[$key])) {
                    return $this->listeners[$key];
                }
            }
        }

        // Fetch from injected current firewall information, if possible
        if (isset($this->listeners[$this->currentFirewallName ?? ''])) {
            return $this->listeners[$this->currentFirewallName ?? ''];
        }

        foreach ($this->listeners as $listener) {
            if (isset($listener[4]) && $this->currentFirewallContext === $listener[4]) {
                return $listener;
            }
        }

        if (null === $this->currentFirewallName) {
            throw new \InvalidArgumentException('This request is not behind a firewall, pass the firewall name manually to generate a logout URL.');
        }

        throw new \InvalidArgumentException('Unable to find logout in the current firewall, pass the firewall name manually to generate a logout URL.');
    }

    public function reset(): void
    {
        $this->currentFirewallName = null;
        $this->currentFirewallContext = null;
    }
}
