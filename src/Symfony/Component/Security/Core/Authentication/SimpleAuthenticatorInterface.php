<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface SimpleAuthenticatorInterface
{
    public function authenticate(TokenInterface $token, UserProviderInterface $userProvider, $providerKey);

    public function supports(TokenInterface $token, $providerKey);

    public function handleAuthenticationFailure(GetResponseEvent $event, AuthenticationException $exception);
}
