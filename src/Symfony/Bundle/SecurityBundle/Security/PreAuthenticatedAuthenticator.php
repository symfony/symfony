<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;

/**
 * This class is only used in FirewallUserAuthenticator.
 * Its sole reason if existence is LoginSuccessEvent requiring an authenticator.
 *
 * @author Martin Kirilov <wucdbm@gmail.com>
 *
 * @internal
 *
 * @experimental in 5.2
 */
class PreAuthenticatedAuthenticator implements AuthenticatorInterface
{
    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function authenticate(Request $request): PassportInterface
    {
        throw new \LogicException(sprintf('"%s" does not support %s::authenticate() calls', static::class, AuthenticatorInterface::class));
    }

    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
        // TODO Tests
        return new PreAuthenticatedToken($passport->getUser(), null, $firewallName, $passport->getUser()->getRoles());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
