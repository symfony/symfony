<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * This is an authentication event that includes sensitive data.
 *
 * @author Rob Frawley 2nd <rmf@src.run>
 */
class AuthenticationSensitiveEvent extends Event
{
    private $preAuthenticationToken;
    private $authenticationToken;
    private $authenticationProviderClassName;

    public function __construct(TokenInterface $preAuthenticationToken, TokenInterface $authenticationToken, ?string $authenticationProviderClassName = null)
    {
        $this->preAuthenticationToken = $preAuthenticationToken;
        $this->authenticationToken = $authenticationToken;
        $this->authenticationProviderClassName = $authenticationProviderClassName;
    }

    public function getPreAuthenticationToken(): TokenInterface
    {
        return $this->preAuthenticationToken;
    }

    public function getAuthenticationToken(): TokenInterface
    {
        return $this->authenticationToken;
    }

    public function getAuthenticationProviderClassName(): ?string
    {
        return $this->authenticationProviderClassName;
    }

    /**
     * Tries to extract the credentials password, first from the post-auth token and second from the pre-auth token.
     * It uses either a custom extraction closure (optionally passed as its first and only argument) or the default
     * extraction implementation. The default extractor fetches the token's credentials and directly returns it if
     * the value is a scalar or object that implements a "__toString()" method. If the credentials val is an array
     * the first "password", "api_key", "api-key", or "secret" index value (that exists and is non-false after being
     * cast to a sting using the prior described method) is returned. Lastly, if none of the previous conditions are
     * met, "null" is returned.
     *
     * @param \Closure|null $extractor An optional custom token credentials password extraction \Closure that is
     *                                 provided an auth token (as an instance of TokenInterface) and an auth event
     *                                 (as an instance of AuthenticationSensitiveEvent). This closure is called
     *                                 first with the final-auth token and second with the pre-auth token, returning
     *                                 early if a non-null/non-empty scalar/castable-object value is returned.
     *
     * @return string|null Either a credentials password/secret/auth_key is returned or null on extraction failure
     */
    public function getAuthenticationTokenPassword(?\Closure $extractor = null): ?string
    {
        $extractor = $extractor ?? function (TokenInterface $token): ?string {
            return $this->tryCoercibleCredentialsPasswordToString($credentials = $token->getCredentials())
                ?: $this->tryArrayFindCredentialsPasswordToString($credentials);
        };

        return ($extractor($this->authenticationToken, $this) ?: null)
            ?: ($extractor($this->preAuthenticationToken, $this) ?: null);
    }

    private function tryCoercibleCredentialsPasswordToString($credentials): ?string
    {
        return is_scalar($credentials) || method_exists($credentials, '__toString')
            ? $credentials
            : null;
    }

    private function tryArrayFindCredentialsPasswordToString($credentials): ?string
    {
        if (\is_array($credentials)) {
            foreach (['password', 'api_key', 'api-key', 'secret'] as $index) {
                if ($c = $this->tryCoercibleCredentialsPasswordToString($credentials[$index] ?? null)) {
                    return $c;
                }
            }
        }

        return null;
    }
}
