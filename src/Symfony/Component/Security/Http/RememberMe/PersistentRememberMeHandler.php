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
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentTokenInterface;
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
     * Marks a stored token value as bound to the signature properties of the user.
     *
     * Base64 encoded values never start with this character, which tells bound
     * values apart from the ones stored before the properties were bound.
     */
    private const SIGNATURE_MARKER = '~';

    private TokenProviderInterface $tokenProvider;
    private ?TokenVerifierInterface $tokenVerifier;
    private UserProviderInterface $userProvider;
    private ?array $signatureProperties;
    private PropertyAccessorInterface $propertyAccessor;

    /**
     * @param UserProviderInterface          $userProvider
     * @param RequestStack                   $requestStack
     * @param array                          $options
     * @param LoggerInterface|null           $logger
     * @param TokenVerifierInterface|null    $tokenVerifier
     * @param array|null                     $signatureProperties Properties of the User; the stored token is invalidated when these properties change.
     *                                                            Defaults to ["password"], with the properties the user does not expose being skipped
     * @param PropertyAccessorInterface|null $propertyAccessor
     */
    public function __construct(TokenProviderInterface $tokenProvider, #[\SensitiveParameter] $userProvider, $requestStack, $options, $logger = null, $tokenVerifier = null, $signatureProperties = null, $propertyAccessor = null)
    {
        if (\is_string($userProvider)) {
            trigger_deprecation('symfony/security-http', '6.3', 'Calling "%s()" with the secret as the second argument is deprecated. The argument will be dropped in 7.0.', __CLASS__);

            $userProvider = $requestStack;
            $requestStack = $options;
            $options = $logger;
            $logger = $tokenVerifier;
            $tokenVerifier = \func_num_args() > 6 ? func_get_arg(6) : null;
            $signatureProperties = \func_num_args() > 7 ? func_get_arg(7) : null;
            $propertyAccessor = \func_num_args() > 8 ? func_get_arg(8) : null;
        }

        if (!$userProvider instanceof UserProviderInterface) {
            throw new \TypeError(\sprintf('Argument 2 passed to "%s()" must be an instance of "%s", "%s" given.', __CLASS__, UserProviderInterface::class, get_debug_type($userProvider)));
        }

        if (!$requestStack instanceof RequestStack) {
            throw new \TypeError(\sprintf('Argument 3 passed to "%s()" must be an instance of "%s", "%s" given.', __CLASS__, RequestStack::class, get_debug_type($userProvider)));
        }

        if (!\is_array($options)) {
            throw new \TypeError(\sprintf('Argument 4 passed to "%s()" must be an array, "%s" given.', __CLASS__, get_debug_type($userProvider)));
        }

        if (null !== $logger && !$logger instanceof LoggerInterface) {
            throw new \TypeError(\sprintf('Argument 5 passed to "%s()" must be an instance of "%s", "%s" given.', __CLASS__, LoggerInterface::class, get_debug_type($userProvider)));
        }

        if (null !== $tokenVerifier && !$tokenVerifier instanceof TokenVerifierInterface) {
            throw new \TypeError(\sprintf('Argument 6 passed to "%s()" must be an instance of "%s", "%s" given.', __CLASS__, TokenVerifierInterface::class, get_debug_type($userProvider)));
        }

        if (null !== $signatureProperties && !\is_array($signatureProperties)) {
            throw new \TypeError(\sprintf('Argument 7 passed to "%s()" must be an array, "%s" given.', __CLASS__, get_debug_type($signatureProperties)));
        }

        if (null !== $propertyAccessor && !$propertyAccessor instanceof PropertyAccessorInterface) {
            throw new \TypeError(\sprintf('Argument 8 passed to "%s()" must be an instance of "%s", "%s" given.', __CLASS__, PropertyAccessorInterface::class, get_debug_type($propertyAccessor)));
        }

        parent::__construct($userProvider, $requestStack, $options, $logger);

        if (!$tokenVerifier && $tokenProvider instanceof TokenVerifierInterface) {
            $tokenVerifier = $tokenProvider;
        }
        $this->tokenProvider = $tokenProvider;
        $this->tokenVerifier = $tokenVerifier;
        $this->userProvider = $userProvider;
        $this->signatureProperties = $signatureProperties;
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function createRememberMeCookie(UserInterface $user): void
    {
        $series = random_bytes(66);
        $tokenValue = strtr(base64_encode(substr($series, 33)), '+/=', '-_~');
        $series = strtr(base64_encode(substr($series, 0, 33)), '+/=', '-_~');
        $token = new PersistentToken($user::class, $user->getUserIdentifier(), $series, $this->hashTokenValue($tokenValue, $user), new \DateTimeImmutable());

        $this->tokenProvider->createNewToken($token);
        $this->createCookie(new RememberMeDetails($user::class, $user->getUserIdentifier(), time() + $this->options['lifetime'], $series.':'.$tokenValue));
    }

    /**
     * This does not call the parent method: checking the signature properties requires the user, and the
     * user is loaded from the identifier of the persistent token rather than from the untrusted cookie,
     * which also keeps the number of user lookups to one.
     */
    public function consumeRememberMeCookie(RememberMeDetails $rememberMeDetails): UserInterface
    {
        if (!str_contains($rememberMeDetails->getValue(), ':')) {
            throw new AuthenticationException('The cookie is incorrectly formatted.');
        }

        [$series, $tokenValue] = explode(':', $rememberMeDetails->getValue(), 2);
        $persistentToken = $this->tokenProvider->loadTokenBySeries($series);

        if ($persistentToken->getUserIdentifier() !== $rememberMeDetails->getUserIdentifier() || $persistentToken->getClass() !== $rememberMeDetails->getUserFqcn()) {
            throw new AuthenticationException('The cookie\'s hash is invalid.');
        }

        // content of $rememberMeDetails is not trustable. this prevents use of this class
        unset($rememberMeDetails);

        $user = $this->userProvider->loadUserByIdentifier($persistentToken->getUserIdentifier());

        if (!$user instanceof UserInterface) {
            throw new \LogicException(\sprintf('The UserProviderInterface implementation must return an instance of UserInterface, but returned "%s".', get_debug_type($user)));
        }

        $storedTokenValue = $this->hashTokenValue($tokenValue, $user);

        // a token stored before the signature properties were bound to it is accepted once, then bound
        $bindToken = $storedTokenValue !== $tokenValue && !str_starts_with($persistentToken->getTokenValue(), self::SIGNATURE_MARKER);

        if (!$this->verifyTokenValue($persistentToken, $bindToken ? $tokenValue : $storedTokenValue)) {
            throw new CookieTheftException('This token was already used. The account is possibly compromised.');
        }

        $expires = $persistentToken->getLastUsed()->getTimestamp() + $this->options['lifetime'];
        if ($expires < time()) {
            throw new AuthenticationException('The cookie has expired.');
        }

        // when the token is about to be regenerated, binding it here would be a needless extra write
        if ($bindToken && $this->isTokenFresh($persistentToken)) {
            $this->tokenProvider->updateToken($series, $storedTokenValue, \DateTime::createFromInterface($persistentToken->getLastUsed()));
        }

        $this->processRememberMe(new RememberMeDetails(
            $persistentToken->getClass(),
            $persistentToken->getUserIdentifier(),
            $expires,
            $persistentToken->getLastUsed()->getTimestamp().':'.$series.':'.$storedTokenValue.':'.$persistentToken->getClass()
        ), $user);

        $this->logger?->info('Remember-me cookie accepted.');

        return $user;
    }

    public function processRememberMe(RememberMeDetails $rememberMeDetails, UserInterface $user): void
    {
        [$lastUsed, $series, $tokenValue, $class] = explode(':', $rememberMeDetails->getValue(), 4);
        $persistentToken = new PersistentToken($class, $rememberMeDetails->getUserIdentifier(), $series, $tokenValue, new \DateTimeImmutable('@'.$lastUsed));

        if ($this->isTokenFresh($persistentToken)) {
            return;
        }

        $tokenValue = strtr(base64_encode(random_bytes(33)), '+/=', '-_~');
        $storedTokenValue = $this->hashTokenValue($tokenValue, $user);
        $tokenLastUsed = new \DateTime();
        $this->tokenVerifier?->updateExistingToken($persistentToken, $storedTokenValue, $tokenLastUsed);
        $this->tokenProvider->updateToken($series, $storedTokenValue, $tokenLastUsed);

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
     * A token regenerated less than a minute ago does not need to be regenerated again.
     *
     * This keeps concurrent requests that reauthenticate the same user from updating the token several times.
     */
    private function isTokenFresh(PersistentTokenInterface $persistentToken): bool
    {
        return $persistentToken->getLastUsed()->getTimestamp() + 60 >= time();
    }

    private function verifyTokenValue(PersistentTokenInterface $persistentToken, #[\SensitiveParameter] string $tokenValue): bool
    {
        if ($this->tokenVerifier) {
            return $this->tokenVerifier->verifyToken($persistentToken, $tokenValue);
        }

        return hash_equals($persistentToken->getTokenValue(), $tokenValue);
    }

    /**
     * Binds the signature properties of the user to the token value that is stored by the token provider.
     *
     * The cookie carries the plain token value only, so that the stored value cannot be replayed and
     * changing one of the signature properties makes every token of that user unusable.
     *
     * Properties the user does not expose are skipped when the list was not configured explicitly. The
     * token value is then returned unchanged, which is what keeps user classes without any of the default
     * properties working as they did before the properties were bound.
     */
    private function hashTokenValue(#[\SensitiveParameter] string $tokenValue, UserInterface $user): string
    {
        $isDefaultList = null === $this->signatureProperties;
        $signature = hash_init('sha256');
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

            hash_update($signature, ':'.base64_encode($value));
            $isBound = true;
        }

        if (!$isBound) {
            return $tokenValue;
        }

        return self::SIGNATURE_MARKER.strtr(base64_encode(hash('sha256', $tokenValue.'|'.hash_final($signature), true)), '+/=', '-_~');
    }
}
