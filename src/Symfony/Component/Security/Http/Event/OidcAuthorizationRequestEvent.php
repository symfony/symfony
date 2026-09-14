<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before the user is redirected to the OIDC authorization endpoint, so that the
 * extra parameters of the authorization request can be computed per request, e.g. a
 * "ui_locales" following the current locale or a "login_hint" read from the session.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
final class OidcAuthorizationRequestEvent extends Event
{
    /**
     * @param array<string, string> $params The extra parameters of the authorization request,
     *                                      starting from the configured ones
     */
    public function __construct(
        private Request $request,
        private string $firewallName,
        private array $params,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getFirewallName(): string
    {
        return $this->firewallName;
    }

    /**
     * @return array<string, string>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Replaces the whole list; use setParam() and removeParam() to touch one parameter,
     * so that several listeners can each set their own without overwriting what the
     * others did.
     *
     * @param array<string, string> $params
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function setParam(string $name, string $value): void
    {
        $this->params[$name] = $value;
    }

    public function removeParam(string $name): void
    {
        unset($this->params[$name]);
    }
}
