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

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * This event is dispatched on authentication failure.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @since v2.1.0
 */
class AuthenticationFailureEvent extends AuthenticationEvent
{
    private $authenticationException;

    /**
     * @since v2.1.0
     */
    public function __construct(TokenInterface $token, AuthenticationException $ex)
    {
        parent::__construct($token);

        $this->authenticationException = $ex;
    }

    /**
     * @since v2.1.0
     */
    public function getAuthenticationException()
    {
        return $this->authenticationException;
    }
}
