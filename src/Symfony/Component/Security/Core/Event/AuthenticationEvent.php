<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Event;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * This is a general purpose authentication event.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @since v2.1.0
 */
class AuthenticationEvent extends Event
{
    private $authenticationToken;

    /**
     * @since v2.1.0
     */
    public function __construct(TokenInterface $token)
    {
        $this->authenticationToken = $token;
    }

    /**
     * @since v2.1.0
     */
    public function getAuthenticationToken()
    {
        return $this->authenticationToken;
    }
}
