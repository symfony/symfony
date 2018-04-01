<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Event;

use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * This event is dispatched on authentication failure.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AuthenticationFailureEvent extends AuthenticationEvent
{
    private $authenticationException;

    public function __construct(TokenInterface $token, AuthenticationException $ex)
    {
        parent::__construct($token);

        $this->authenticationException = $ex;
    }

    public function getAuthenticationException()
    {
        return $this->authenticationException;
    }
}
