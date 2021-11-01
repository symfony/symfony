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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is dispatched after authentication has successfully completed.
 *
 * At this stage, the authenticator created a token and
 * generated an authentication success response. Listeners to
 * this event can do actions related to successful authentication
 * (such as migrating the password).
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class LoginSuccessEvent extends Event
{
    private AuthenticatorInterface $authenticator;
    private Passport $passport;
    private TokenInterface $authenticatedToken;
    private Request $request;
    private ?Response $response;
    private string $firewallName;

    public function __construct(AuthenticatorInterface $authenticator, Passport $passport, TokenInterface $authenticatedToken, Request $request, ?Response $response, string $firewallName)
    {
        $this->authenticator = $authenticator;
        $this->passport = $passport;
        $this->authenticatedToken = $authenticatedToken;
        $this->request = $request;
        $this->response = $response;
        $this->firewallName = $firewallName;
    }

    public function getAuthenticator(): AuthenticatorInterface
    {
        return $this->authenticator;
    }

    public function getPassport(): Passport
    {
        return $this->passport;
    }

    public function getUser(): UserInterface
    {
        return $this->passport->getUser();
    }

    public function getAuthenticatedToken(): TokenInterface
    {
        return $this->authenticatedToken;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getFirewallName(): string
    {
        return $this->firewallName;
    }

    public function setResponse(?Response $response): void
    {
        $this->response = $response;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }
}
