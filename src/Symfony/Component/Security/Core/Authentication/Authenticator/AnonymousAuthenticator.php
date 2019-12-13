<?php

namespace Symfony\Component\Security\Core\Authentication\Authenticator;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\Token\GuardTokenInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class AnonymousAuthenticator implements AuthenticatorInterface
{
    private $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new Response(null, Response::HTTP_UNAUTHORIZED);
    }

    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function getCredentials(Request $request)
    {
        return [];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        return new User('anon.', null);
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        return true;
    }

    public function createAuthenticatedToken(UserInterface $user, string $providerKey)
    {
        return new AnonymousToken($this->secret, 'anon.', []);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $providerKey)
    {
    }

    public function supportsRememberMe(): bool
    {
        return false;
    }
}
