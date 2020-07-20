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
 * Deauthentication happens in case the user has changed when trying to refresh the token.
 *
 * @author Hamza Amrouche <hamza.simperfit@gmail.com>
 */
final class DeauthenticatedEvent extends Event
{
    private $originalToken;
    private $refreshedToken;

    public function __construct(TokenInterface $originalToken, TokenInterface $refreshedToken)
    {
        $this->originalToken = $originalToken;
        $this->refreshedToken = $refreshedToken;
    }

    public function getRefreshedToken(): TokenInterface
    {
        return $this->refreshedToken;
    }

    public function getOriginalToken(): TokenInterface
    {
        return $this->originalToken;
    }
}
