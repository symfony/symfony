<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Signature;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\Signature\Exception\ExpiredSignatureException;
use Symfony\Component\Security\Core\Signature\Exception\InvalidSignatureException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Creates and validates secure hashes used in login links and remember-me cookies.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class SignatureHasher
{
    /**
     * @param array                        $signatureProperties      Properties of the User; the hash is invalidated if these properties change
     * @param ExpiredSignatureStorage|null $expiredSignaturesStorage If provided, secures a sequence of hashes that are expired
     * @param int|null                     $maxUses                  Used together with $expiredSignatureStorage to allow a maximum usage of a hash
     */
    public function __construct(
        private PropertyAccessorInterface $propertyAccessor,
        private array $signatureProperties,
        #[\SensitiveParameter] private string $secret,
        private ?ExpiredSignatureStorage $expiredSignaturesStorage = null,
        private ?int $maxUses = null,
    ) {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }
    }

    /**
     * Verifies the hash using the provided user identifier and expire time.
     *
     * This method must be called before the user object is loaded from a provider.
     *
     * @param int                               $expires    The expiry time as a unix timestamp
     * @param string                            $hash       The plaintext hash provided by the request
     * @param array<string, \Stringable|scalar> $parameters Extra values covered by the signature
     *
     * @throws InvalidSignatureException If the signature does not match the provided parameters
     * @throws ExpiredSignatureException If the signature is no longer valid
     */
    public function acceptSignatureHash(string $userIdentifier, int $expires, string $hash/* , array $parameters = [] */): void
    {
        $parameters = 3 < \func_num_args() ? func_get_arg(3) : [];

        if ($expires < time()) {
            throw new ExpiredSignatureException('Signature has expired.');
        }
        $hmac = substr($hash, 0, 44);
        $payload = substr($hash, 44).':'.$expires.':'.$userIdentifier.self::squashParameters($parameters);

        if (!hash_equals($hmac, $this->generateHash($payload))) {
            throw new InvalidSignatureException('Invalid or expired signature.');
        }
    }

    /**
     * Verifies the hash using the provided user and expire time.
     *
     * @param int                               $expires    The expiry time as a unix timestamp
     * @param string                            $hash       The plaintext hash provided by the request
     * @param array<string, \Stringable|scalar> $parameters Extra values covered by the signature
     *
     * @throws InvalidSignatureException If the signature does not match the provided parameters
     * @throws ExpiredSignatureException If the signature is no longer valid
     */
    public function verifySignatureHash(UserInterface $user, int $expires, string $hash/* , array $parameters = [] */): void
    {
        $parameters = 3 < \func_num_args() ? func_get_arg(3) : [];

        if ($expires < time()) {
            throw new ExpiredSignatureException('Signature has expired.');
        }

        if (!hash_equals($hash, $this->computeSignatureHash($user, $expires, $parameters))) {
            throw new InvalidSignatureException('Invalid or expired signature.');
        }

        if ($this->expiredSignaturesStorage && $this->maxUses && $this->expiredSignaturesStorage->incrementUsages($hash) > $this->maxUses) {
            throw new ExpiredSignatureException(\sprintf('Signature can only be used "%d" times.', $this->maxUses));
        }
    }

    /**
     * Computes the secure hash for the provided user and expire time.
     *
     * @param int                               $expires    The expiry time as a unix timestamp
     * @param array<string, \Stringable|scalar> $parameters Extra values covered by the signature
     */
    public function computeSignatureHash(UserInterface $user, int $expires/* , array $parameters = [] */): string
    {
        $parameters = 2 < \func_num_args() ? func_get_arg(2) : [];
        $userIdentifier = $user->getUserIdentifier();
        $fieldsHash = hash_init('sha256');

        foreach ($this->signatureProperties as $property) {
            $value = $this->propertyAccessor->getValue($user, $property) ?? '';
            if ($value instanceof \UnitEnum) {
                $value = serialize($value);
            } else {
                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format('c');
                } elseif (!\is_scalar($value) && !$value instanceof \Stringable) {
                    throw new \InvalidArgumentException(\sprintf('The property path "%s" on the user object "%s" must return a value that can be cast to a string, but "%s" was returned.', $property, $user::class, get_debug_type($value)));
                }
                $value = base64_encode($value);
            }
            hash_update($fieldsHash, ':'.$value);
        }

        hash_update($fieldsHash, self::squashParameters($parameters));

        $fieldsHash = strtr(base64_encode(hash_final($fieldsHash, true)), '+/=', '-_~');

        return $this->generateHash($fieldsHash.':'.$expires.':'.$userIdentifier.self::squashParameters($parameters)).$fieldsHash;
    }

    /**
     * Renders extra parameters as a single string that can take part in the signature.
     *
     * @param array<string, \Stringable|scalar> $parameters
     */
    private static function squashParameters(array $parameters): string
    {
        if (!$parameters) {
            return '';
        }

        ksort($parameters);

        $result = '';

        foreach ($parameters as $key => $value) {
            if (!\is_scalar($value) && !$value instanceof \Stringable) {
                throw new \InvalidArgumentException(\sprintf('Login link parameter "%s" must be a scalar or a stringable object, "%s" given.', $key, get_debug_type($value)));
            }

            // booleans travel in the query string as "0" and "1", sign them the same way
            $result .= ':'.base64_encode($key).'_'.base64_encode(\is_bool($value) ? (string) (int) $value : (string) $value);
        }

        return $result;
    }

    private function generateHash(string $tokenValue): string
    {
        return strtr(base64_encode(hash_hmac('sha256', $tokenValue, $this->secret, true)), '+/=', '-_~');
    }
}
