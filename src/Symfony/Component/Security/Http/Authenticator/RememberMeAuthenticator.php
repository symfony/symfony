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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CookieTheftException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\RememberMe\RememberMeDetails;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;
use Symfony\Component\Security\Http\RememberMe\ResponseListener;

/**
 * The RememberMe *Authenticator* performs remember me authentication.
 *
 * This authenticator is executed whenever a user's session
 * expired and a remember-me cookie was found. This authenticator
 * then "re-authenticates" the user using the information in the
 * cookie.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class RememberMeAuthenticator implements InteractiveAuthenticatorInterface
{
    private RememberMeHandlerInterface $rememberMeHandler;
    private string $secret;
    private TokenStorageInterface $tokenStorage;
    private string $cookieName;
    private ?LoggerInterface $logger;

    public function __construct(RememberMeHandlerInterface $rememberMeHandler, string $secret, TokenStorageInterface $tokenStorage, string $cookieName, LoggerInterface $logger = null)
    {
        $this->rememberMeHandler = $rememberMeHandler;
        $this->secret = $secret;
        $this->tokenStorage = $tokenStorage;
        $this->cookieName = $cookieName;
        $this->logger = $logger;
    }

    public function supports(Request $request): ?bool
    {
        // do not overwrite already stored tokens (i.e. from the session)
        if (null !== $this->tokenStorage->getToken()) {
            return false;
        }

        if (($cookie = $request->attributes->get(ResponseListener::COOKIE_ATTR_NAME)) && null === $cookie->getValue()) {
            return false;
        }

        if (!$request->cookies->has($this->cookieName)) {
            return false;
        }

        $this->logger?->debug('Remember-me cookie detected.');

        // the `null` return value indicates that this authenticator supports lazy firewalls
        return null;
    }

    public function authenticate(Request $request): Passport
    {
        $rawCookie = $request->cookies->get($this->cookieName);
        if (!$rawCookie) {
            throw new \LogicException('No remember-me cookie is found.');
        }

        $rememberMeCookie = RememberMeDetails::fromRawCookie($rawCookie);

        return new SelfValidatingPassport(new UserBadge($rememberMeCookie->getUserIdentifier(), function () use ($rememberMeCookie) {
            return $this->rememberMeHandler->consumeRememberMeCookie($rememberMeCookie);
        }));
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return new RememberMeToken($passport->getUser(), $firewallName, $this->secret);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // let the original request continue
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if (null !== $this->logger) {
            if ($exception instanceof UserNotFoundException) {
                $this->logger->info('User for remember-me cookie not found.', ['exception' => $exception]);
            } elseif ($exception instanceof UnsupportedUserException) {
                $this->logger->warning('User class for remember-me cookie not supported.', ['exception' => $exception]);
            } elseif (!$exception instanceof CookieTheftException) {
                $this->logger->debug('Remember me authentication failed.', ['exception' => $exception]);
            }
        }

        return null;
    }

    public function isInteractive(): bool
    {
        return true;
    }
}
