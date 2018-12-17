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

/**
 * Argon2iPasswordEncoder uses the Argon2i hashing algorithm.
 *
 * @author Zan Baldwin <hello@zanbaldwin.com>
 * @author Dominik Müller <dominik.mueller@jkweb.ch>
 */
class Argon2iPasswordEncoder extends BasePasswordEncoder implements SelfSaltingEncoderInterface
{
    use Argon2Trait;

    public static function isSupported()
    {
        if (\defined('PASSWORD_ARGON2I')) {
            return true;
        }

        if (\class_exists('ParagonIE_Sodium_Compat') && \method_exists('ParagonIE_Sodium_Compat', 'crypto_pwhash_is_available')) {
            return \ParagonIE_Sodium_Compat::crypto_pwhash_is_available();
        }

        return \function_exists('sodium_crypto_pwhash_str') || \extension_loaded('libsodium');
    }

    /**
     * {@inheritdoc}
     */
    public function encodePassword($raw, $salt)
    {
        if ($this->isPasswordTooLong($raw)) {
            throw new BadCredentialsException('Invalid password.');
        }

        if (\PHP_VERSION_ID >= 70200 && \defined('PASSWORD_ARGON2I')) {
            return $this->encodePasswordNative($raw, \PASSWORD_ARGON2I);
        } elseif (\function_exists('sodium_crypto_pwhash_str')) {
            if (0 === strpos($hash = $this->encodePasswordSodiumFunction($raw), Argon2idPasswordEncoder::HASH_PREFIX)) {
                @trigger_error(sprintf('Using "%s" while only the "argon2id" algorithm is supported is deprecated since Symfony 4.3, use "%s" instead.', __CLASS__, Argon2idPasswordEncoder::class), E_USER_DEPRECATED);
            }

            return $hash;
        }
        if (\extension_loaded('libsodium')) {
            return $this->encodePasswordSodiumExtension($raw);
        }

        throw new \LogicException('Argon2i algorithm is not supported. Please install the libsodium extension or upgrade to PHP 7.2+.');
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid($encoded, $raw, $salt)
    {
        if ($this->isPasswordTooLong($raw)) {
            return false;
        }

        if (\PHP_VERSION_ID >= 70200 && \defined('PASSWORD_ARGON2I')) {
            // If $encoded was created via "sodium_crypto_pwhash_str()", the hashing algorithm may be "argon2id" instead of "argon2i"
            if ($isArgon2id = (0 === strpos($encoded, Argon2idPasswordEncoder::HASH_PREFIX))) {
                @trigger_error(sprintf('Calling "%s()" with a password hashed using argon2id is deprecated since Symfony 4.3, use "%s" instead.', __METHOD__, Argon2idPasswordEncoder::class), E_USER_DEPRECATED);
            }

            // Remove the right part of the OR in 5.0
            if (\defined('PASSWORD_ARGON2I') || $isArgon2id && \defined('PASSWORD_ARGON2ID')) {
                return password_verify($raw, $encoded);
            }
        }
        if (\function_exists('sodium_crypto_pwhash_str_verify')) {
            $valid = !$this->isPasswordTooLong($raw) && \sodium_crypto_pwhash_str_verify($encoded, $raw);
            \sodium_memzero($raw);

            return $valid;
        }
        if (\extension_loaded('libsodium')) {
            $valid = !$this->isPasswordTooLong($raw) && \Sodium\crypto_pwhash_str_verify($encoded, $raw);
            \Sodium\memzero($raw);

            return $valid;
        }

        throw new \LogicException('Argon2i algorithm is not supported. Please install the libsodium extension or upgrade to PHP 7.2+.');
    }

    private function encodePasswordSodiumExtension($raw)
    {
        $hash = \Sodium\crypto_pwhash_str(
            $raw,
            \Sodium\CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            \Sodium\CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
        );
        \Sodium\memzero($raw);

        return $hash;
    }
}
