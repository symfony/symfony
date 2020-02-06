<?php

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\Authenticator\AuthenticatorInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is dispatched after authentication has successfully completed.
 *
 * At this stage, the authenticator created an authenticated token
 * and generated an authentication success response. Listeners to
 * this event can do actions related to successful authentication
 * (such as migrating the password).
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class LoginSuccessEvent extends Event
{
    private $authenticator;
    private $authenticatedToken;
    private $request;
    private $response;
    private $providerKey;

    public function __construct(AuthenticatorInterface $authenticator, TokenInterface $authenticatedToken, Request $request, ?Response $response, string $providerKey)
    {
        $this->authenticator = $authenticator;
        $this->authenticatedToken = $authenticatedToken;
        $this->request = $request;
        $this->response = $response;
        $this->providerKey = $providerKey;
    }

    public function getAuthenticator(): AuthenticatorInterface
    {
        return $this->authenticator;
    }

    public function getAuthenticatedToken(): TokenInterface
    {
        return $this->authenticatedToken;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function getProviderKey(): string
    {
        return $this->providerKey;
    }
}
