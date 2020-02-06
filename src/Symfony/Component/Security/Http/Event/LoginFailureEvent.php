<?php

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
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
    private $exception;
    private $authenticator;
    private $request;
    private $response;
    private $providerKey;

    public function __construct(AuthenticationException $exception, AuthenticatorInterface $authenticator, Request $request, ?Response $response, string $providerKey)
    {
        $this->exception = $exception;
        $this->authenticator = $authenticator;
        $this->request = $request;
        $this->response = $response;
        $this->providerKey = $providerKey;
    }

    public function getException(): AuthenticationException
    {
        return $this->exception;
    }

    public function getAuthenticator(): AuthenticatorInterface
    {
        return $this->authenticator;
    }

    public function getProviderKey(): string
    {
        return $this->providerKey;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }
}
