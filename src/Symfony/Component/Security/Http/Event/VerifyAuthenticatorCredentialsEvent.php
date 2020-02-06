<?php

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is dispatched when the credentials have to be checked.
 *
 * Listeners to this event must validate the user and the
 * credentials (e.g. default listeners do password verification and
 * user checking)
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class VerifyAuthenticatorCredentialsEvent extends Event
{
    private $authenticator;
    private $preAuthenticatedToken;
    private $user;
    private $credentialsValid = false;

    public function __construct(AuthenticatorInterface $authenticator, TokenInterface $preAuthenticatedToken, ?UserInterface $user)
    {
        $this->authenticator = $authenticator;
        $this->preAuthenticatedToken = $preAuthenticatedToken;
        $this->user = $user;
    }

    public function getAuthenticator(): AuthenticatorInterface
    {
        return $this->authenticator;
    }

    public function getPreAuthenticatedToken(): TokenInterface
    {
        return $this->preAuthenticatedToken;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setCredentialsValid(bool $validated = true): void
    {
        $this->credentialsValid = $validated;
    }

    public function areCredentialsValid(): bool
    {
        return $this->credentialsValid;
    }
}
