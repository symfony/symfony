<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Encryption;

use Symfony\Component\Security\Core\Exception\DecryptionException;
use Symfony\Component\Security\Core\Exception\MalformedCipherException;
use Symfony\Component\Security\Core\Exception\UnsupportedAlgorithmException;

class SymmetricEncryption
{
    /**
     * @var string application secret
     */
    private $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function encrypt(string $text): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($text, $nonce, $this->getSodiumKey($this->secret));

        return sprintf('%s.%s.%s', base64_encode($cipher), base64_encode('sodium_secretbox'), base64_encode($nonce));
    }

    public function decrypt(string $message): string
    {
        // Make sure the message has two periods
        $parts = explode('.', $message);
        if (false === $parts || 3 !== \count($parts)) {
            throw new MalformedCipherException();
        }

        [$cipher, $algorithm, $nonce] = $parts;

        $algorithm = base64_decode($algorithm);
        if ('sodium_secretbox' !== $algorithm) {
            throw new UnsupportedAlgorithmException($algorithm);
        }

        $ciphertext = base64_decode($cipher, true);
        $nonce = base64_decode($nonce, true);
        $key = $this->getSodiumKey($this->secret);

        try {
            return sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        } catch (\SodiumException $exception) {
            throw new DecryptionException(sprintf('Failed to decrypt key with algorithm "%s"', $algorithm), 0, $exception);
        }
    }

    private function getSodiumKey(string $secret): string
    {
        $secretLength = \strlen($secret);
        if ($secretLength > \SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return substr($secret, 0, \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        }
        if ($secretLength < \SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return sodium_pad($secret, \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        }

        return $secret;
    }
}
