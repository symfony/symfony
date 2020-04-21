<?php

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
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
    private $passport;

    public function __construct(AuthenticatorInterface $authenticator, PassportInterface $passport)
    {
        $this->authenticator = $authenticator;
        $this->passport = $passport;
    }

    public function getAuthenticator(): AuthenticatorInterface
    {
        return $this->authenticator;
    }

    public function getPassport(): PassportInterface
    {
        return $this->passport;
    }
}
