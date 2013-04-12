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
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface SimpleAuthenticatorInterface
{
    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, $providerKey);

    public function supportsToken(TokenInterface $token, $providerKey);

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception);

    public function onAuthenticationSuccess(Request $request, TokenInterface $token);
}
