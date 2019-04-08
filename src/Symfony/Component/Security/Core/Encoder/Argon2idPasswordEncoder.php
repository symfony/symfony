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
 * Hashes passwords using the Argon2id algorithm.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @final
 */
class Argon2idPasswordEncoder extends BasePasswordEncoder implements SelfSaltingEncoderInterface
{
    use Argon2Trait;

    /**
     * @internal
     */
    public const HASH_PREFIX = '$argon2id';

    public static function isSupported()
    {
        return \defined('PASSWORD_ARGON2ID') || \defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13');
    }

    /**
     * {@inheritdoc}
     */
    public function encodePassword($raw, $salt)
    {
        if ($this->isPasswordTooLong($raw)) {
            throw new BadCredentialsException('Invalid password.');
        }
        if (\defined('PASSWORD_ARGON2ID')) {
            return $this->encodePasswordNative($raw, \PASSWORD_ARGON2ID);
        }
        if (!\defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) {
            throw new LogicException('Algorithm "argon2id" is not supported. Please install the libsodium extension or upgrade to PHP 7.3+.');
        }

        $hash = \sodium_crypto_pwhash_str(
            $raw,
            \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
        );
        \sodium_memzero($raw);

        return $hash;
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid($encoded, $raw, $salt)
    {
        if (0 !== strpos($encoded, self::HASH_PREFIX)) {
            return false;
        }

        if (\defined('PASSWORD_ARGON2ID')) {
            return !$this->isPasswordTooLong($raw) && password_verify($raw, $encoded);
        }

        if (\function_exists('sodium_crypto_pwhash_str_verify')) {
            $valid = !$this->isPasswordTooLong($raw) && \sodium_crypto_pwhash_str_verify($encoded, $raw);
            \sodium_memzero($raw);

            return $valid;
        }

        throw new LogicException('Algorithm "argon2id" is not supported. Please install the libsodium extension or upgrade to PHP 7.3+.');
    }
}
