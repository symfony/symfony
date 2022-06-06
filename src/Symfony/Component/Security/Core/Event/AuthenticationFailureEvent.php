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
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated, use "%s" with the new authenticator system instead.', AuthenticationFailureEvent::class, LoginFailureEvent::class);

/**
 * This event is dispatched on authentication failure.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @deprecated since Symfony 5.3, use LoginFailureEvent with the new authenticator system instead
 */
final class AuthenticationFailureEvent extends AuthenticationEvent
{
    private $authenticationException;

    public function __construct(TokenInterface $token, AuthenticationException $ex)
    {
        parent::__construct($token);

        $this->authenticationException = $ex;
    }

    public function getAuthenticationException(): AuthenticationException
    {
        return $this->authenticationException;
    }
}
