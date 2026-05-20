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
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticator;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is dispatched right before an authenticator's authenticate() method is called.
 *
 * Listeners may use it to observe authentication attempts, e.g. to audit
 * login attempts (including ones targeting an unknown user). Throwing an
 * AuthenticationException from a listener triggers the regular failure
 * flow (LoginFailureEvent is dispatched with a null passport).
 *
 * @author Michael Thieulin <michael.thieulin@gmail.com>
 */
class BeforeAuthenticateEvent extends Event
{
    public function __construct(
        private AuthenticatorInterface $authenticator,
        private Request $request,
    ) {
    }

    public function getAuthenticator(): AuthenticatorInterface
    {
        return $this->authenticator instanceof TraceableAuthenticator ? $this->authenticator->getAuthenticator() : $this->authenticator;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
