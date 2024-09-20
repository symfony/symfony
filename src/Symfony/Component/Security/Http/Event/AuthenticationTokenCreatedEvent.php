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

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * When a newly authenticated security token was created, before it becomes effective in the security system.
 *
 * @author Christian Scheb <me@christianscheb.de>
 */
class AuthenticationTokenCreatedEvent extends Event
{
    public function __construct(
        private TokenInterface $token,
        private Passport $passport,
    ) {
    }

    public function getAuthenticatedToken(): TokenInterface
    {
        return $this->token;
    }

    public function setAuthenticatedToken(TokenInterface $authenticatedToken): void
    {
        $this->token = $authenticatedToken;
    }

    public function getPassport(): Passport
    {
        return $this->passport;
    }
}
