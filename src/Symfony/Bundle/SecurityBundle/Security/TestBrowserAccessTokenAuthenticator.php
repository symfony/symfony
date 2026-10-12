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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\EntryPoint\FallbackAuthenticationEntryPointInterface;

/**
 * Authenticates the user of KernelBrowser::loginUser() on a stateless firewall that uses the "access_token" authenticator.
 *
 * A stateless firewall doesn't read the session, so the test client sends its token with each request, in a request attribute.
 * Only code that runs in the same process can set this attribute.
 * The user is loaded like for a real access token, and the token attributes given to loginUser() are added to the authenticated token.
 * When the request contains its own access token, the decorated authenticator handles it.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class TestBrowserAccessTokenAuthenticator implements AuthenticatorInterface, FallbackAuthenticationEntryPointInterface
{
    public function __construct(
        private readonly AuthenticatorInterface&AuthenticationEntryPointInterface $authenticator,
        private readonly string $firewallName,
        private readonly ?UserProviderInterface $userProvider = null,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $supports = $this->authenticator->supports($request);

        return false === $supports && $this->getTestToken($request) ? null : $supports;
    }

    public function authenticate(Request $request): Passport
    {
        if (false !== $this->authenticator->supports($request) || !$token = $this->getTestToken($request)) {
            return $this->authenticator->authenticate($request);
        }

        $user = $token->getUser();
        $passport = new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), $this->userProvider ? $this->userProvider->loadUserByIdentifier(...) : static fn () => $user));
        $passport->setAttribute(self::class, $token->getAttributes());

        return $passport;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = $this->authenticator->createToken($passport, $firewallName);

        if (null !== $attributes = $passport->getAttribute(self::class)) {
            $token->setAttributes($attributes + $token->getAttributes());
        }

        return $token;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->authenticator->onAuthenticationSuccess($request, $token, $firewallName);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->authenticator->onAuthenticationFailure($request, $exception);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->authenticator->start($request, $authException);
    }

    private function getTestToken(Request $request): ?TokenInterface
    {
        $token = $request->attributes->get('_security_test_tokens')[$this->firewallName] ?? null;

        return $token instanceof TokenInterface && $token->getUser() ? $token : null;
    }
}
