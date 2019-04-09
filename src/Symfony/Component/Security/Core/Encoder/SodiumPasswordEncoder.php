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
 *
 * @final
 */
class SodiumPasswordEncoder extends BasePasswordEncoder implements SelfSaltingEncoderInterface
{
    public static function isSupported(): bool
    {
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

        if (\function_exists('sodium_crypto_pwhash_str')) {
            return \sodium_crypto_pwhash_str(
                $raw,
                \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
            );
        }

        if (\extension_loaded('libsodium')) {
            return \Sodium\crypto_pwhash_str(
                $raw,
                \Sodium\CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                \Sodium\CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
            );
        }

        throw new LogicException('Libsodium is not available. You should either install the sodium extension, upgrade to PHP 7.2+ or use a different encoder.');
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid($encoded, $raw, $salt)
    {
        if ($this->isPasswordTooLong($raw)) {
            return false;
        }

        if (\function_exists('sodium_crypto_pwhash_str_verify')) {
            return \sodium_crypto_pwhash_str_verify($encoded, $raw);
        }

        if (\extension_loaded('libsodium')) {
            return \Sodium\crypto_pwhash_str_verify($encoded, $raw);
        }

        throw new LogicException('Libsodium is not available. You should either install the sodium extension, upgrade to PHP 7.2+ or use a different encoder.');
    }
}
