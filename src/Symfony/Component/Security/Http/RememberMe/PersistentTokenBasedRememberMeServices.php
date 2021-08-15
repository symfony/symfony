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

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentTokenInterface;
use Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CookieTheftException;

trigger_deprecation('symfony/security-http', '5.4', 'The "%s" class is deprecated, use "%s" instead.', PersistentTokenBasedRememberMeServices::class, PersistentRememberMeHandler::class);

/**
 * Concrete implementation of the RememberMeServicesInterface which needs
 * an implementation of TokenProviderInterface for providing remember-me
 * capabilities.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @deprecated since Symfony 5.4, use {@see PersistentRememberMeHandler} instead
 */
class PersistentTokenBasedRememberMeServices extends AbstractRememberMeServices
{
    private const HASHED_TOKEN_PREFIX = 'sha256_';

    /** @var TokenProviderInterface */
    private $tokenProvider;

    public function setTokenProvider(TokenProviderInterface $tokenProvider)
    {
        $this->tokenProvider = $tokenProvider;
    }

    /**
     * {@inheritdoc}
     */
    protected function cancelCookie(Request $request)
    {
        // Delete cookie on the client
        parent::cancelCookie($request);

        // Delete cookie from the tokenProvider
        if (null !== ($cookie = $request->cookies->get($this->options['name']))
            && 2 === \count($parts = $this->decodeCookie($cookie))
        ) {
            [$series] = $parts;
            $this->tokenProvider->deleteTokenBySeries($series);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function processAutoLoginCookie(array $cookieParts, Request $request)
    {
        if (2 !== \count($cookieParts)) {
            throw new AuthenticationException('The cookie is invalid.');
        }

        [$series, $tokenValue] = $cookieParts;
        $persistentToken = $this->tokenProvider->loadTokenBySeries($series);

        if (!$this->isTokenValueValid($persistentToken, $tokenValue)) {
            throw new CookieTheftException('This token was already used. The account is possibly compromised.');
        }

        if ($persistentToken->getLastUsed()->getTimestamp() + $this->options['lifetime'] < time()) {
            throw new AuthenticationException('The cookie has expired.');
        }

        $tokenValue = base64_encode(random_bytes(64));
        $this->tokenProvider->updateToken($series, $this->generateHash($tokenValue), new \DateTime());
        $request->attributes->set(self::COOKIE_ATTR_NAME,
            new Cookie(
                $this->options['name'],
                $this->encodeCookie([$series, $tokenValue]),
                time() + $this->options['lifetime'],
                $this->options['path'],
                $this->options['domain'],
                $this->options['secure'] ?? $request->isSecure(),
                $this->options['httponly'],
                false,
                $this->options['samesite']
            )
        );

        $userProvider = $this->getUserProvider($persistentToken->getClass());
        // @deprecated since Symfony 5.3, change to $persistentToken->getUserIdentifier() in 6.0
        if (method_exists($persistentToken, 'getUserIdentifier')) {
            $userIdentifier = $persistentToken->getUserIdentifier();
        } else {
            trigger_deprecation('symfony/security-core', '5.3', 'Not implementing method "getUserIdentifier()" in persistent token "%s" is deprecated. This method will replace "loadUserByUsername()" in Symfony 6.0.', get_debug_type($persistentToken));

            $userIdentifier = $persistentToken->getUsername();
        }

        // @deprecated since Symfony 5.3, change to $userProvider->loadUserByIdentifier() in 6.0
        if (method_exists($userProvider, 'loadUserByIdentifier')) {
            return $userProvider->loadUserByIdentifier($userIdentifier);
        } else {
            trigger_deprecation('symfony/security-core', '5.3', 'Not implementing method "loadUserByIdentifier()" in user provider "%s" is deprecated. This method will replace "loadUserByUsername()" in Symfony 6.0.', get_debug_type($userProvider));

            return $userProvider->loadUserByUsername($userIdentifier);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function onLoginSuccess(Request $request, Response $response, TokenInterface $token)
    {
        $series = base64_encode(random_bytes(64));
        $tokenValue = base64_encode(random_bytes(64));

        $this->tokenProvider->createNewToken(
            new PersistentToken(
                \get_class($user = $token->getUser()),
                // @deprecated since Symfony 5.3, change to $user->getUserIdentifier() in 6.0
                method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : $user->getUsername(),
                $series,
                $this->generateHash($tokenValue),
                new \DateTime()
            )
        );

        $response->headers->setCookie(
            new Cookie(
                $this->options['name'],
                $this->encodeCookie([$series, $tokenValue]),
                time() + $this->options['lifetime'],
                $this->options['path'],
                $this->options['domain'],
                $this->options['secure'] ?? $request->isSecure(),
                $this->options['httponly'],
                false,
                $this->options['samesite']
            )
        );
    }

    private function generateHash(string $tokenValue): string
    {
        return self::HASHED_TOKEN_PREFIX.hash_hmac('sha256', $tokenValue, $this->getSecret());
    }

    private function isTokenValueValid(PersistentTokenInterface $persistentToken, string $tokenValue): bool
    {
        if (0 === strpos($persistentToken->getTokenValue(), self::HASHED_TOKEN_PREFIX)) {
            return hash_equals($persistentToken->getTokenValue(), $this->generateHash($tokenValue));
        }

        return hash_equals($persistentToken->getTokenValue(), $tokenValue);
    }
}
