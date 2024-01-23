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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is dispatched after an error during authentication.
 *
 * Listeners to this event can change state based on authentication
 * failure (e.g. to implement login throttling).
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class LoginFailureEvent extends Event
{
    private AuthenticationException $exception;
    private AuthenticatorInterface $authenticator;
    private Request $request;
    private ?Response $response;
    private string $firewallName;
    private ?Passport $passport;

    public function __construct(AuthenticationException $exception, AuthenticatorInterface $authenticator, Request $request, ?Response $response, string $firewallName, ?Passport $passport = null)
    {
        $this->exception = $exception;
        $this->authenticator = $authenticator;
        $this->request = $request;
        $this->response = $response;
        $this->firewallName = $firewallName;
        $this->passport = $passport;
    }

    public function getException(): AuthenticationException
    {
        return $this->exception;
    }

    public function getAuthenticator(): AuthenticatorInterface
    {
        return $this->authenticator instanceof TraceableAuthenticator ? $this->authenticator->getAuthenticator() : $this->authenticator;
    }

    public function getFirewallName(): string
    {
        return $this->firewallName;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return void
     */
    public function setResponse(?Response $response)
    {
        $this->response = $response;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function getPassport(): ?Passport
    {
        return $this->passport;
    }
}
