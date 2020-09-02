<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Encoder;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\LogicException;

/**
 * Hashes passwords using libsodium.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Zan Baldwin <hello@zanbaldwin.com>
 * @author Dominik Müller <dominik.mueller@jkweb.ch>
 */
final class SodiumPasswordEncoder implements PasswordEncoderInterface, SelfSaltingEncoderInterface
{
    private const MAX_PASSWORD_LENGTH = 4096;

    private $opsLimit;
    private $memLimit;

    public function __construct(int $opsLimit = null, int $memLimit = null)
    {
        if (!self::isSupported()) {
            throw new LogicException('Libsodium is not available. You should either install the sodium extension, upgrade to PHP 7.2+ or use a different encoder.');
        }

        $this->opsLimit = $opsLimit ?? max(4, \defined('SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE') ? \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE : 4);
        $this->memLimit = $memLimit ?? max(64 * 1024 * 1024, \defined('SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE') ? \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE : 64 * 1024 * 1024);

        if (3 > $this->opsLimit) {
            throw new \InvalidArgumentException('$opsLimit must be 3 or greater.');
        }

        if (10 * 1024 > $this->memLimit) {
            throw new \InvalidArgumentException('$memLimit must be 10k or greater.');
        }
    }

    public static function isSupported(): bool
    {
        return version_compare(\extension_loaded('sodium') ? \SODIUM_LIBRARY_VERSION : phpversion('libsodium'), '1.0.14', '>=');
    }

    /**
     * {@inheritdoc}
     */
    public function encodePassword(string $raw, ?string $salt): string
    {
        if (\strlen($raw) > self::MAX_PASSWORD_LENGTH) {
            throw new BadCredentialsException('Invalid password.');
        }

        if (\function_exists('sodium_crypto_pwhash_str')) {
            return sodium_crypto_pwhash_str($raw, $this->opsLimit, $this->memLimit);
        }

        if (\extension_loaded('libsodium')) {
            return \Sodium\crypto_pwhash_str($raw, $this->opsLimit, $this->memLimit);
        }

        throw new LogicException('Libsodium is not available. You should either install the sodium extension, upgrade to PHP 7.2+ or use a different encoder.');
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid(string $encoded, string $raw, ?string $salt): bool
    {
        if ('' === $raw) {
            return false;
        }

        if (\strlen($raw) > self::MAX_PASSWORD_LENGTH) {
            return false;
        }

        if (0 !== strpos($encoded, '$argon')) {
            // Accept validating non-argon passwords for seamless migrations
            return (72 >= \strlen($raw) || 0 !== strpos($encoded, '$2')) && password_verify($raw, $encoded);
        }

        if (\function_exists('sodium_crypto_pwhash_str_verify')) {
            return sodium_crypto_pwhash_str_verify($encoded, $raw);
        }

        if (\extension_loaded('libsodium')) {
            return \Sodium\crypto_pwhash_str_verify($encoded, $raw);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function needsRehash(string $encoded): bool
    {
        if (\function_exists('sodium_crypto_pwhash_str_needs_rehash')) {
            return sodium_crypto_pwhash_str_needs_rehash($encoded, $this->opsLimit, $this->memLimit);
        }

        if (\extension_loaded('libsodium')) {
            return \Sodium\crypto_pwhash_str_needs_rehash($encoded, $this->opsLimit, $this->memLimit);
        }

        throw new LogicException('Libsodium is not available. You should either install the sodium extension, upgrade to PHP 7.2+ or use a different encoder.');
    }
}
