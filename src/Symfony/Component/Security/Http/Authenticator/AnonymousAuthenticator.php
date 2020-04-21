<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\AnonymousPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 * @experimental in 5.1
 */
class AnonymousAuthenticator implements AuthenticatorInterface
{
    private $secret;
    private $tokenStorage;

    public function __construct(string $secret, TokenStorageInterface $tokenStorage)
    {
        $this->secret = $secret;
        $this->tokenStorage = $tokenStorage;
    }

    public function supports(Request $request): ?bool
    {
        // do not overwrite already stored tokens (i.e. from the session)
        // the `null` return value indicates that this authenticator supports lazy firewalls
        return null === $this->tokenStorage->getToken() ? null : false;
    }

    public function authenticate(Request $request): PassportInterface
    {
        return new AnonymousPassport();
    }

    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
        return new AnonymousToken($this->secret, 'anon.', []);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // let the original request continue
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
