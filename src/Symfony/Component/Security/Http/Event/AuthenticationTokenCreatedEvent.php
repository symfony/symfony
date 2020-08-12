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
use Symfony\Contracts\EventDispatcher\Event;

/**
 * When a newly authenticated security token was created, before it becomes effective in the security system.
 *
 * @author Christian Scheb <me@christianscheb.de>
 */
class AuthenticationTokenCreatedEvent extends Event
{
    private $authenticatedToken;

    public function __construct(TokenInterface $token)
    {
        $this->authenticatedToken = $token;
    }

    public function getAuthenticatedToken(): TokenInterface
    {
        return $this->authenticatedToken;
    }

    public function setAuthenticatedToken(TokenInterface $authenticatedToken): void
    {
        $this->authenticatedToken = $authenticatedToken;
    }
}
