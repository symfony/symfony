<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PasswordHasher\Hasher;

use Symfony\Component\PasswordHasher\Exception\InvalidPasswordException;
use Symfony\Component\PasswordHasher\Exception\LogicException;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Hashes passwords using libsodium.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Zan Baldwin <hello@zanbaldwin.com>
 * @author Dominik Müller <dominik.mueller@jkweb.ch>
 */
final class SodiumPasswordHasher implements PasswordHasherInterface
{
    use CheckPasswordLengthTrait;

    private int $opsLimit;
    private int $memLimit;

    public function __construct(?int $opsLimit = null, ?int $memLimit = null)
    {
        if (!self::isSupported()) {
            throw new LogicException('Libsodium is not available. You should either install the sodium extension or use a different password hasher.');
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

    public function hash(#[\SensitiveParameter] string $plainPassword): string
    {
        if ($this->isPasswordTooLong($plainPassword)) {
            throw new InvalidPasswordException();
        }

        if (\function_exists('sodium_crypto_pwhash_str')) {
            return sodium_crypto_pwhash_str($plainPassword, $this->opsLimit, $this->memLimit);
        }

        if (\extension_loaded('libsodium')) {
            return \Sodium\crypto_pwhash_str($plainPassword, $this->opsLimit, $this->memLimit);
        }

        throw new LogicException('Libsodium is not available. You should either install the sodium extension or use a different password hasher.');
    }

    public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword): bool
    {
        if ('' === $plainPassword) {
            return false;
        }

        if ($this->isPasswordTooLong($plainPassword)) {
            return false;
        }

        if (!str_starts_with($hashedPassword, '$argon')) {
            if (str_starts_with($hashedPassword, '$2') && (72 < \strlen($plainPassword) || str_contains($plainPassword, "\0"))) {
                $plainPassword = base64_encode(hash('sha512', $plainPassword, true));
            }

            // Accept validating non-argon passwords for seamless migrations
            return password_verify($plainPassword, $hashedPassword);
        }

        if (\function_exists('sodium_crypto_pwhash_str_verify')) {
            return sodium_crypto_pwhash_str_verify($hashedPassword, $plainPassword);
        }

        if (\extension_loaded('libsodium')) {
            return \Sodium\crypto_pwhash_str_verify($hashedPassword, $plainPassword);
        }

        return false;
    }

    public function needsRehash(string $hashedPassword): bool
    {
        if (\function_exists('sodium_crypto_pwhash_str_needs_rehash')) {
            return sodium_crypto_pwhash_str_needs_rehash($hashedPassword, $this->opsLimit, $this->memLimit);
        }

        if (\extension_loaded('libsodium')) {
            return \Sodium\crypto_pwhash_str_needs_rehash($hashedPassword, $this->opsLimit, $this->memLimit);
        }

        throw new LogicException('Libsodium is not available. You should either install the sodium extension or use a different password hasher.');
    }
}
