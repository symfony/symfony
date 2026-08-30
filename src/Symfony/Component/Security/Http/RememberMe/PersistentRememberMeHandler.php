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
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
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
    /**
     * Separates the random part of a token value from the digest of the signature properties of the user.
     *
     * Token values are base64 encoded, so they never contain this character: a stored
     * value without it was written before the properties were bound to the token.
     */
    private const DIGEST_SEPARATOR = '.';

    private PropertyAccessorInterface $propertyAccessor;

    /**
     * @param array|null $signatureProperties Properties of the User; the stored token is invalidated when these properties change.
     *                                        Defaults to ["password"], with the properties the user does not expose being skipped
     */
    public function __construct(
        private TokenProviderInterface $tokenProvider,
        UserProviderInterface $userProvider,
        RequestStack $requestStack,
        array $options,
        ?LoggerInterface $logger = null,
        private ?TokenVerifierInterface $tokenVerifier = null,
        private ?array $signatureProperties = null,
        ?PropertyAccessorInterface $propertyAccessor = null,
    ) {
        parent::__construct($userProvider, $requestStack, $options, $logger);

        if (!$tokenVerifier && $tokenProvider instanceof TokenVerifierInterface) {
            $this->tokenVerifier = $tokenProvider;
        }
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function createRememberMeCookie(UserInterface $user): void
    {
        $series = random_bytes(66);
        $tokenValue = strtr(base64_encode(substr($series, 33)), '+/=', '-_~');
        $series = strtr(base64_encode(substr($series, 0, 33)), '+/=', '-_~');
        if (null !== $digest = $this->hashSignatureProperties($user)) {
            $tokenValue .= self::DIGEST_SEPARATOR.$digest;
        }
        if (method_exists(PersistentToken::class, 'getClass')) {
            $token = new PersistentToken($user::class, $user->getUserIdentifier(), $series, $tokenValue, new \DateTimeImmutable(), false);
        } else {
            $token = new PersistentToken($user->getUserIdentifier(), $series, $tokenValue, new \DateTimeImmutable());
        }

        $this->tokenProvider->createNewToken($token);
        $this->createCookie(RememberMeDetails::fromPersistentToken($token, time() + $this->options['lifetime']));
    }

    public function consumeRememberMeCookie(RememberMeDetails $rememberMeDetails): UserInterface
    {
        if (!str_contains($rememberMeDetails->getValue(), ':')) {
            throw new AuthenticationException('The cookie is incorrectly formatted.');
        }

        [$series, $tokenValue] = explode(':', $rememberMeDetails->getValue(), 2);
        $token = $this->tokenProvider->loadTokenBySeries($series);

        if ($token->getUserIdentifier() !== $rememberMeDetails->getUserIdentifier()) {
            throw new AuthenticationException('The cookie\'s hash is invalid.');
        }

        // content of $rememberMeDetails is not trustable. this prevents use of this class
        unset($rememberMeDetails);

        if ($this->tokenVerifier) {
            $isTokenValid = $this->tokenVerifier->verifyToken($token, $tokenValue);
        } else {
            $isTokenValid = hash_equals($token->getTokenValue(), $tokenValue);
        }
        if (!$isTokenValid) {
            throw new CookieTheftException('This token was already used. The account is possibly compromised.');
        }

        $expires = $token->getLastUsed()->getTimestamp() + $this->options['lifetime'];
        if ($expires < time()) {
            throw new AuthenticationException('The cookie has expired.');
        }

        return parent::consumeRememberMeCookie(new RememberMeDetails(
            $token->getUserIdentifier(),
            $expires,
            $token->getLastUsed()->getTimestamp().':'.$series.':'.$token->getTokenValue().':'.(method_exists($token, 'getClass') ? $token->getClass(false) : ''),
        ));
    }

    public function processRememberMe(RememberMeDetails $rememberMeDetails, UserInterface $user): void
    {
        [$lastUsed, $series, $tokenValue, $class] = explode(':', $rememberMeDetails->getValue(), 4);
        if (method_exists(PersistentToken::class, 'getClass')) {
            $token = new PersistentToken($class, $rememberMeDetails->getUserIdentifier(), $series, $tokenValue, new \DateTimeImmutable('@'.$lastUsed), false);
        } else {
            $token = new PersistentToken($rememberMeDetails->getUserIdentifier(), $series, $tokenValue, new \DateTimeImmutable('@'.$lastUsed));
        }

        $digest = $this->hashSignatureProperties($user);
        $storedDigest = false === ($i = strpos($tokenValue, self::DIGEST_SEPARATOR)) ? null : substr($tokenValue, $i + 1);

        if (null !== $storedDigest && (null === $digest || !hash_equals($storedDigest, $digest))) {
            throw new AuthenticationException('The cookie\'s hash is invalid.');
        }

        // if a token was regenerated less than a minute ago, there is no need to regenerate it
        // if multiple concurrent requests reauthenticate a user we do not want to update the token several times
        if ($token->getLastUsed()->getTimestamp() + 60 >= time()) {
            return;
        }

        $tokenValue = strtr(base64_encode(random_bytes(33)), '+/=', '-_~');
        if (null !== $digest) {
            $tokenValue .= self::DIGEST_SEPARATOR.$digest;
        }
        $tokenLastUsed = new \DateTime();
        $this->tokenVerifier?->updateExistingToken($token, $tokenValue, $tokenLastUsed);
        $this->tokenProvider->updateToken($series, $tokenValue, $tokenLastUsed);

        $this->createCookie($rememberMeDetails->withValue($series.':'.$tokenValue));
    }

    public function clearRememberMeCookie(): void
    {
        parent::clearRememberMeCookie();

        $cookie = $this->requestStack->getMainRequest()->cookies->get($this->options['name']);
        if (null === $cookie) {
            return;
        }

        try {
            $rememberMeDetails = RememberMeDetails::fromRawCookie($cookie);
        } catch (AuthenticationException) {
            // malformed cookie should not fail the response and can be simply ignored
            return;
        }
        [$series] = explode(':', $rememberMeDetails->getValue());
        $this->tokenProvider->deleteTokenBySeries($series);
    }

    /**
     * @internal
     */
    public function getTokenProvider(): TokenProviderInterface
    {
        return $this->tokenProvider;
    }

    /**
     * Returns a digest of the signature properties of the user, or null when there is nothing to bind.
     *
     * Properties the user does not expose are skipped when the list was not configured explicitly,
     * which keeps user classes without any of the default properties working as before.
     */
    private function hashSignatureProperties(UserInterface $user): ?string
    {
        $isDefaultList = null === $this->signatureProperties;
        $digest = hash_init('sha256');
        $isBound = false;

        foreach ($this->signatureProperties ?? ['password'] as $property) {
            if ($isDefaultList && !$this->propertyAccessor->isReadable($user, $property)) {
                continue;
            }

            $value = $this->propertyAccessor->getValue($user, $property) ?? '';
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('c');
            }

            if (!\is_scalar($value) && !$value instanceof \Stringable) {
                throw new \InvalidArgumentException(\sprintf('The property path "%s" on the user object "%s" must return a value that can be cast to a string, but "%s" was returned.', $property, $user::class, get_debug_type($value)));
            }

            hash_update($digest, ':'.base64_encode($value));
            $isBound = true;
        }

        if (!$isBound) {
            return null;
        }

        // 24 bytes keep the token value within the 88 characters of the documented "rememberme_token.value" column
        return strtr(base64_encode(substr(hash_final($digest, true), 0, 24)), '+/', '-_');
    }
}
