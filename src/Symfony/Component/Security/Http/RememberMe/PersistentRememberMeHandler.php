<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\RememberMe;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface;
use Symfony\Component\Security\Core\Authentication\RememberMe\TokenVerifierInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CookieTheftException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Implements remember-me tokens using a {@see TokenProviderInterface}.
 *
 * This requires storing remember-me tokens in a database. This allows
 * more control over the invalidation of remember-me tokens. See
 * {@see SignatureRememberMeHandler} if you don't want to use a database.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class PersistentRememberMeHandler extends AbstractRememberMeHandler
{
    private $tokenProvider;
    private $tokenVerifier;
    private $secret;

    public function __construct(TokenProviderInterface $tokenProvider, string $secret, UserProviderInterface $userProvider, RequestStack $requestStack, array $options, LoggerInterface $logger = null, TokenVerifierInterface $tokenVerifier = null)
    {
        parent::__construct($userProvider, $requestStack, $options, $logger);

        if (!$tokenVerifier && $tokenProvider instanceof TokenVerifierInterface) {
            $tokenVerifier = $tokenProvider;
        }
        $this->tokenProvider = $tokenProvider;
        $this->tokenVerifier = $tokenVerifier;
        $this->secret = $secret;
    }

    /**
     * {@inheritdoc}
     */
    public function createRememberMeCookie(UserInterface $user): void
    {
        $series = base64_encode(random_bytes(64));
        $tokenValue = $this->generateHash(base64_encode(random_bytes(64)));
        $token = new PersistentToken(\get_class($user), method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : $user->getUsername(), $series, $tokenValue, new \DateTime());

        $this->tokenProvider->createNewToken($token);
        $this->createCookie(RememberMeDetails::fromPersistentToken($token, time() + $this->options['lifetime']));
    }

    /**
     * {@inheritdoc}
     */
    public function processRememberMe(RememberMeDetails $rememberMeDetails, UserInterface $user): void
    {
        if (!str_contains($rememberMeDetails->getValue(), ':')) {
            throw new AuthenticationException('The cookie is incorrectly formatted.');
        }

        [$series, $tokenValue] = explode(':', $rememberMeDetails->getValue());
        $persistentToken = $this->tokenProvider->loadTokenBySeries($series);

        if ($this->tokenVerifier) {
            $isTokenValid = $this->tokenVerifier->verifyToken($persistentToken, $tokenValue);
            $tokenValue = $persistentToken->getTokenValue();
        } else {
            $isTokenValid = hash_equals($persistentToken->getTokenValue(), $tokenValue);
        }
        if (!$isTokenValid) {
            throw new CookieTheftException('This token was already used. The account is possibly compromised.');
        }

        if ($persistentToken->getLastUsed()->getTimestamp() + $this->options['lifetime'] < time()) {
            throw new AuthenticationException('The cookie has expired.');
        }

        // if a token was regenerated less than a minute ago, there is no need to regenerate it
        // if multiple concurrent requests reauthenticate a user we do not want to update the token several times
        if ($persistentToken->getLastUsed()->getTimestamp() + 60 < time()) {
            $tokenValue = $this->generateHash(base64_encode(random_bytes(64)));
            $tokenLastUsed = new \DateTime();
            if ($this->tokenVerifier) {
                $this->tokenVerifier->updateExistingToken($persistentToken, $tokenValue, $tokenLastUsed);
            }
            $this->tokenProvider->updateToken($series, $tokenValue, $tokenLastUsed);
        }

        $this->createCookie($rememberMeDetails->withValue($series.':'.$tokenValue));
    }

    /**
     * {@inheritdoc}
     */
    public function clearRememberMeCookie(): void
    {
        parent::clearRememberMeCookie();

        $cookie = $this->requestStack->getMainRequest()->cookies->get($this->options['name']);
        if (null === $cookie) {
            return;
        }

        $rememberMeDetails = RememberMeDetails::fromRawCookie($cookie);
        [$series, ] = explode(':', $rememberMeDetails->getValue());
        $this->tokenProvider->deleteTokenBySeries($series);
    }

    /**
     * @internal
     */
    public function getTokenProvider(): TokenProviderInterface
    {
        return $this->tokenProvider;
    }

    private function generateHash(string $tokenValue): string
    {
        return hash_hmac('sha256', $tokenValue, $this->secret);
    }
}
