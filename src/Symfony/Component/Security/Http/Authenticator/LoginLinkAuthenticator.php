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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\LoginLink\Exception\InvalidLoginLinkAuthenticationException;
use Symfony\Component\Security\Http\LoginLink\Exception\InvalidLoginLinkExceptionInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class LoginLinkAuthenticator extends AbstractAuthenticator implements InteractiveAuthenticatorInterface
{
    private array $options;

    public function __construct(
        private LoginLinkHandlerInterface $loginLinkHandler,
        private HttpUtils $httpUtils,
        private AuthenticationSuccessHandlerInterface $successHandler,
        private AuthenticationFailureHandlerInterface $failureHandler,
        array $options,
    ) {
        $this->options = $options + ['check_post_only' => false];
    }

    public function supports(Request $request): ?bool
    {
        return ($this->options['check_post_only'] ? $request->isMethod('POST') : true)
            && $this->httpUtils->checkRequestPath($request, $this->options['check_route']);
    }

    public function authenticate(Request $request): Passport
    {
        if (!$username = $request->query->get('user') ?? (!\in_array($request->getMethod(), ['GET', 'HEAD'], true) ? $request->request->get('user') : null)) {
            throw new InvalidLoginLinkAuthenticationException('Missing user from link.');
        }

        $userBadge = new UserBadge($username, function () use ($request) {
            try {
                $user = $this->loginLinkHandler->consumeLoginLink($request);
            } catch (InvalidLoginLinkExceptionInterface $e) {
                throw new InvalidLoginLinkAuthenticationException('Login link could not be validated.', 0, $e);
            }

            return $user;
        });

        return new SelfValidatingPassport($userBadge, [new RememberMeBadge()]);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }

    public function isInteractive(): bool
    {
        return true;
    }
}
