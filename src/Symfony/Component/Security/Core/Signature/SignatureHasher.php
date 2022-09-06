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
    private $propertyAccessor;
    private $signatureProperties;
    private $secret;
    private $expiredSignaturesStorage;
    private $maxUses;

    /**
     * @param array                        $signatureProperties      Properties of the User; the hash is invalidated if these properties change
     * @param ExpiredSignatureStorage|null $expiredSignaturesStorage If provided, secures a sequence of hashes that are expired
     * @param int|null                     $maxUses                  Used together with $expiredSignatureStorage to allow a maximum usage of a hash
     */
    public function __construct(PropertyAccessorInterface $propertyAccessor, array $signatureProperties, string $secret, ExpiredSignatureStorage $expiredSignaturesStorage = null, int $maxUses = null)
    {
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
     *
     * @throws InvalidSignatureException If the signature does not match the provided parameters
     * @throws ExpiredSignatureException If the signature is no longer valid
     */
    public function acceptSignatureHash(string $userIdentifier, int $expires, string $hash): void
    {
        if ($expires < time()) {
            throw new ExpiredSignatureException('Signature has expired.');
        }
        $hmac = substr($hash, 0, 44);
        $payload = substr($hash, 44).':'.$expires.':'.$userIdentifier;

        if (!hash_equals($hmac, $this->generateHash($payload))) {
            throw new InvalidSignatureException('Invalid or expired signature.');
        }
    }

    /**
     * Verifies the hash using the provided user and expire time.
     *
     * @param int    $expires The expiry time as a unix timestamp
     * @param string $hash    The plaintext hash provided by the request
     *
     * @throws InvalidSignatureException If the signature does not match the provided parameters
     * @throws ExpiredSignatureException If the signature is no longer valid
     */
    public function verifySignatureHash(UserInterface $user, int $expires, string $hash): void
    {
        if ($expires < time()) {
            throw new ExpiredSignatureException('Signature has expired.');
        }

        if (!hash_equals($hash, $this->computeSignatureHash($user, $expires))) {
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
     */
    public function computeSignatureHash(UserInterface $user, int $expires): string
    {
        $userIdentifier = method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : $user->getUsername();
        $fieldsHash = hash_init('sha256');

        foreach ($this->signatureProperties as $property) {
            $value = $this->propertyAccessor->getValue($user, $property) ?? '';
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('c');
            }

            if (!\is_scalar($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
                throw new \InvalidArgumentException(sprintf('The property path "%s" on the user object "%s" must return a value that can be cast to a string, but "%s" was returned.', $property, \get_class($user), get_debug_type($value)));
            }
            hash_update($fieldsHash, ':'.base64_encode($value));
        }

        $fieldsHash = strtr(base64_encode(hash_final($fieldsHash, true)), '+/=', '-_~');

        return $this->generateHash($fieldsHash.':'.$expires.':'.$userIdentifier).$fieldsHash;
    }

    private function generateHash(string $tokenValue): string
    {
        return strtr(base64_encode(hash_hmac('sha256', $tokenValue, $this->secret, true)), '+/=', '-_~');
    }
}
