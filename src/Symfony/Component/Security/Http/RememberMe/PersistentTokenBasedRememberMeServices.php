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

use Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CookieTheftException;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Concrete implementation of the RememberMeServicesInterface which needs
 * an implementation of TokenProviderInterface for providing remember-me
 * capabilities.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class PersistentTokenBasedRememberMeServices extends AbstractRememberMeServices
{
    private $tokenProvider;

    /**
     * Sets the token provider
     *
     * @param TokenProviderInterface $tokenProvider
     */
    public function setTokenProvider(TokenProviderInterface $tokenProvider)
    {
        $this->tokenProvider = $tokenProvider;
    }

    /**
     * {@inheritDoc}
     */
    public function logout(Request $request, Response $response, TokenInterface $token)
    {
        parent::logout($request, $response, $token);

        if (null !== ($cookie = $request->cookies->get($this->options['name']))
            && count($parts = $this->decodeCookie($cookie)) === 2
        ) {
            list($series, $tokenValue) = $parts;
            $this->tokenProvider->deleteTokenBySeries($series);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function processAutoLoginCookie(array $cookieParts, Request $request)
    {
        if (count($cookieParts) !== 2) {
            throw new AuthenticationException('The cookie is invalid.');
        }

        list($series, $tokenValue) = $cookieParts;
        $persistentToken = $this->tokenProvider->loadTokenBySeries($series);

        if ($persistentToken->getTokenValue() !== $tokenValue) {
            $this->tokenProvider->deleteTokenBySeries($series);

            throw new CookieTheftException('This token was already used. The account is possibly compromised.');
        }

        if ($persistentToken->getLastUsed()->getTimestamp() + $this->options['lifetime'] < time()) {
            throw new AuthenticationException('The cookie has expired.');
        }

        $series = $persistentToken->getSeries();
        $tokenValue = $this->generateRandomValue();
        $this->tokenProvider->updateToken($series, $tokenValue, new \DateTime());
        $request->attributes->set(self::COOKIE_ATTR_NAME,
            new Cookie(
                $this->options['name'],
                $this->encodeCookie(array($series, $tokenValue)),
                time() + $this->options['lifetime'],
                $this->options['path'],
                $this->options['domain'],
                $this->options['secure'],
                $this->options['httponly']
            )
        );

        return $this->getUserProvider($persistentToken->getClass())->loadUserByUsername($persistentToken->getUsername());
    }

    /**
     * {@inheritDoc}
     */
    protected function onLoginSuccess(Request $request, Response $response, TokenInterface $token)
    {
        $series = $this->generateRandomValue();
        $tokenValue = $this->generateRandomValue();

        $this->tokenProvider->createNewToken(
            new PersistentToken(
                get_class($user = $token->getUser()),
                $user->getUsername(),
                $series,
                $tokenValue,
                new \DateTime()
            )
        );

        $response->headers->setCookie(
            new Cookie(
                $this->options['name'],
                $this->encodeCookie(array($series, $tokenValue)),
                time() + $this->options['lifetime'],
                $this->options['path'],
                $this->options['domain'],
                $this->options['secure'],
                $this->options['httponly']
            )
        );
    }

    /**
     * Generates a cryptographically strong random value
     *
     * @return string
     */
    protected function generateRandomValue()
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes(64, $strong);

            if (true === $strong && false !== $bytes) {
                return base64_encode($bytes);
            }
        }

        if (null !== $this->logger) {
            $this->logger->warn('Could not produce a cryptographically strong random value. Please install/update the OpenSSL extension.');
        }

        return base64_encode(hash('sha512', uniqid(mt_rand(), true), true));
    }
}
