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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

trigger_deprecation('symfony/security-http', '5.4', 'The "%s" class is deprecated, use "%s" instead.', TokenBasedRememberMeServices::class, SignatureRememberMeHandler::class);

/**
 * Concrete implementation of the RememberMeServicesInterface providing
 * remember-me capabilities without requiring a TokenProvider.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @deprecated since Symfony 5.4, use {@see SignatureRememberMeHandler} instead
 */
class TokenBasedRememberMeServices extends AbstractRememberMeServices
{
    /**
     * {@inheritdoc}
     */
    protected function processAutoLoginCookie(array $cookieParts, Request $request)
    {
        if (4 !== \count($cookieParts)) {
            throw new AuthenticationException('The cookie is invalid.');
        }

        [$class, $userIdentifier, $expires, $hash] = $cookieParts;
        if (false === $userIdentifier = base64_decode($userIdentifier, true)) {
            throw new AuthenticationException('$userIdentifier contains a character from outside the base64 alphabet.');
        }
        try {
            $userProvider = $this->getUserProvider($class);
            // @deprecated since Symfony 5.3, change to $userProvider->loadUserByIdentifier() in 6.0
            if (method_exists($userProvider, 'loadUserByIdentifier')) {
                $user = $userProvider->loadUserByIdentifier($userIdentifier);
            } else {
                trigger_deprecation('symfony/security-core', '5.3', 'Not implementing method "loadUserByIdentifier()" in user provider "%s" is deprecated. This method will replace "loadUserByUsername()" in Symfony 6.0.', get_debug_type($userProvider));

                $user = $userProvider->loadUserByUsername($userIdentifier);
            }
        } catch (\Exception $e) {
            if (!$e instanceof AuthenticationException) {
                $e = new AuthenticationException($e->getMessage(), $e->getCode(), $e);
            }

            throw $e;
        }

        if (!$user instanceof UserInterface) {
            throw new \RuntimeException(sprintf('The UserProviderInterface implementation must return an instance of UserInterface, but returned "%s".', get_debug_type($user)));
        }

        if (true !== hash_equals($this->generateCookieHash($class, $userIdentifier, $expires, $user->getPassword()), $hash)) {
            throw new AuthenticationException('The cookie\'s hash is invalid.');
        }

        if ($expires < time()) {
            throw new AuthenticationException('The cookie has expired.');
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    protected function onLoginSuccess(Request $request, Response $response, TokenInterface $token)
    {
        $user = $token->getUser();
        $expires = time() + $this->options['lifetime'];
        // @deprecated since Symfony 5.3, change to $user->getUserIdentifier() in 6.0
        $value = $this->generateCookieValue(\get_class($user), method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : $user->getUsername(), $expires, $user->getPassword());

        $response->headers->setCookie(
            new Cookie(
                $this->options['name'],
                $value,
                $expires,
                $this->options['path'],
                $this->options['domain'],
                $this->options['secure'] ?? $request->isSecure(),
                $this->options['httponly'],
                false,
                $this->options['samesite']
            )
        );
    }

    /**
     * Generates the cookie value.
     *
     * @param int         $expires  The Unix timestamp when the cookie expires
     * @param string|null $password The encoded password
     *
     * @return string
     */
    protected function generateCookieValue(string $class, string $userIdentifier, int $expires, ?string $password)
    {
        // $userIdentifier is encoded because it might contain COOKIE_DELIMITER,
        // we assume other values don't
        return $this->encodeCookie([
            $class,
            base64_encode($userIdentifier),
            $expires,
            $this->generateCookieHash($class, $userIdentifier, $expires, $password),
        ]);
    }

    /**
     * Generates a hash for the cookie to ensure it is not being tampered with.
     *
     * @param int         $expires  The Unix timestamp when the cookie expires
     * @param string|null $password The encoded password
     *
     * @return string
     */
    protected function generateCookieHash(string $class, string $userIdentifier, int $expires, ?string $password)
    {
        return hash_hmac('sha256', $class.self::COOKIE_DELIMITER.$userIdentifier.self::COOKIE_DELIMITER.$expires.self::COOKIE_DELIMITER.$password, $this->getSecret());
    }
}
