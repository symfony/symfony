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

use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
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
class CheckPassportEvent extends Event
{
    private AuthenticatorInterface $authenticator;
    private Passport $passport;

    public function __construct(AuthenticatorInterface $authenticator, Passport $passport)
    {
        $this->authenticator = $authenticator;
        $this->passport = $passport;
    }

    public function getAuthenticator(): AuthenticatorInterface
    {
        return $this->authenticator instanceof TraceableAuthenticator ? $this->authenticator->getAuthenticator() : $this->authenticator;
    }

    public function getPassport(): Passport
    {
        return $this->passport;
    }
}
