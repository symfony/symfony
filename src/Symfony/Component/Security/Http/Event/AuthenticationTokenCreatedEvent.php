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
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * When a newly authenticated security token was created, before it becomes effective in the security system.
 *
 * @author Christian Scheb <me@christianscheb.de>
 */
class AuthenticationTokenCreatedEvent extends Event
{
    private $authenticatedToken;
    private $passport;

    public function __construct(TokenInterface $token, PassportInterface $passport)
    {
        $this->authenticatedToken = $token;
        $this->passport = $passport;
    }

    public function getAuthenticatedToken(): TokenInterface
    {
        return $this->authenticatedToken;
    }

    public function setAuthenticatedToken(TokenInterface $authenticatedToken): void
    {
        $this->authenticatedToken = $authenticatedToken;
    }

    public function getPassport(): PassportInterface
    {
        return $this->passport;
    }
}
