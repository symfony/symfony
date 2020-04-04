<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\GuardedBundle;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;

class AuthenticationController
{
    public function manualLoginAction(GuardAuthenticatorHandler $guardAuthenticatorHandler, Request $request)
    {
        $guardAuthenticatorHandler->authenticateWithToken(new PostAuthenticationGuardToken(new User('Jane', 'test', ['ROLE_USER']), 'secure', ['ROLE_USER']), $request, 'secure');

        return new Response('Logged in.');
    }

    public function profileAction(UserInterface $user = null)
    {
        if (null === $user) {
            return new Response('Not logged in.');
        }

        return new Response('Username: '.$user->getUsername());
    }
}
