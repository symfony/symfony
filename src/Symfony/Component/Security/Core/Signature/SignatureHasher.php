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
    private PropertyAccessorInterface $propertyAccessor;
    private array $signatureProperties;
    private string $secret;
    private ?ExpiredSignatureStorage $expiredSignaturesStorage;
    private ?int $maxUses;

    /**
     * @param array                        $signatureProperties      Properties of the User; the hash is invalidated if these properties change
     * @param ExpiredSignatureStorage|null $expiredSignaturesStorage If provided, secures a sequence of hashes that are expired
     * @param int|null                     $maxUses                  Used together with $expiredSignatureStorage to allow a maximum usage of a hash
     */
    public function __construct(PropertyAccessorInterface $propertyAccessor, array $signatureProperties, #[\SensitiveParameter] string $secret, ExpiredSignatureStorage $expiredSignaturesStorage = null, int $maxUses = null)
    {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }

        $this->propertyAccessor = $propertyAccessor;
        $this->signatureProperties = $signatureProperties;
        $this->secret = $secret;
        $this->expiredSignaturesStorage = $expiredSignaturesStorage;
        $this->maxUses = $maxUses;
    }

    /**
     * Verifies the hash using the provided user identifier and expire time.
     *
     * This method must be called before the user object is loaded from a provider.
     *
     * @param int    $expires The expiry time as a unix timestamp
     * @param string $hash    The plaintext hash provided by the request
     * @param array<string, mixed> $parameters  Additional key-value pairs that should be part of the signature
     *
     * @throws InvalidSignatureException If the signature does not match the provided parameters
     * @throws ExpiredSignatureException If the signature is no longer valid
     */
    public function acceptSignatureHash(string $userIdentifier, int $expires, string $hash, array $parameters = []): void
    {
        if ($expires < time()) {
            throw new ExpiredSignatureException('Signature has expired.');
        }

        $hmac = substr($hash, 0, 44);
        $payload = substr($hash, 44).':'.$expires.':'.$userIdentifier.$this->squashParameters($parameters);

        if (!hash_equals($hmac, $this->generateHash($payload))) {
            throw new InvalidSignatureException('Invalid or expired signature.');
        }
    }

    /**
     * Verifies the hash using the provided user and expire time.
     *
     * @param int    $expires The expiry time as a unix timestamp
     * @param string $hash    The plaintext hash provided by the request
     * @param array<string, mixed> $parameters  Additional key-value pairs that should be part of the signature
     *
     * @throws InvalidSignatureException If the signature does not match the provided parameters
     * @throws ExpiredSignatureException If the signature is no longer valid
     */
    public function verifySignatureHash(UserInterface $user, int $expires, string $hash, array $parameters = []): void
    {
        if ($expires < time()) {
            throw new ExpiredSignatureException('Signature has expired.');
        }

        if (!hash_equals($hash, $this->computeSignatureHash($user, $expires, $parameters))) {
            throw new InvalidSignatureException('Invalid or expired signature.');
        }

        if ($this->expiredSignaturesStorage && $this->maxUses) {
            if ($this->expiredSignaturesStorage->countUsages($hash) >= $this->maxUses) {
                throw new ExpiredSignatureException(sprintf('Signature can only be used "%d" times.', $this->maxUses));
            }

            $this->expiredSignaturesStorage->incrementUsages($hash);
        }
    }

    /**
     * Computes the secure hash for the provided user and expire time.
     *
     * @param int $expires The expiry time as a unix timestamp
     * @param array<string, mixed> $parameters Additional key-value pairs that should be part of the signature
     */
    public function computeSignatureHash(UserInterface $user, int $expires, array $parameters = []): string
    {
        $userIdentifier = $user->getUserIdentifier();
        $fieldsHash = hash_init('sha256');

        foreach ($this->signatureProperties as $property) {
            $value = $this->propertyAccessor->getValue($user, $property) ?? '';
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('c');
            }

            if (!\is_scalar($value) && !$value instanceof \Stringable) {
                throw new \InvalidArgumentException(sprintf('The property path "%s" on the user object "%s" must return a value that can be cast to a string, but "%s" was returned.', $property, $user::class, get_debug_type($value)));
            }
            hash_update($fieldsHash, ':'.base64_encode($value));
        }

        hash_update($fieldsHash, $this->squashParameters($parameters));

        $fieldsHash = strtr(base64_encode(hash_final($fieldsHash, true)), '+/=', '-_~');

        return $this->generateHash($fieldsHash.':'.$expires.':'.$userIdentifier.$this->squashParameters($parameters)).$fieldsHash;
    }

    /**
     * Produces a single string of supplied key-value pairs usable during the hashing process.
     *
     * @param array<string, mixed> $parameters
     * @return string
     */
    private function squashParameters(array $parameters): string
    {
        $result = '';

        if (empty($parameters)) {
            return $result;
        }

        ksort($parameters);

        foreach ($parameters as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('c');
            }

            if (!\is_scalar($value) && !$value instanceof \Stringable) {
                throw new \InvalidArgumentException(sprintf('Parameter "%s" must be a value that can be cast to a string, but "%s" was provided.', $key, $value));
            }

            $result .= ':'.base64_encode($key).'_'.base64_encode($value);
        }

        return $result;
    }

    private function generateHash(string $tokenValue): string
    {
        return strtr(base64_encode(hash_hmac('sha256', $tokenValue, $this->secret, true)), '+/=', '-_~');
    }
}
