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

        return base64_encode($cipher).'.sodium_secretbox.'.base64_encode($nonce);
    }

    public function decrypt(string $message): string
    {
        // Make sure the message has two periods
        $parts = explode('.', $message);
        if ($parts === false || count($parts) !== 3) {
            // TODO throw specific exception
            throw new \InvalidArgumentException('Message is malformed.');
        }

        [$cipher, $algorithm, $nonce] = $parts;

        if ($algorithm !== 'sodium_secretbox') {
            // TODO throw specific exception
            throw new \InvalidArgumentException('Unknown algorithm.');
        }

        $ciphertext = base64_decode($cipher, true);
        $nonce = base64_decode($nonce, true);
        $key = $this->getSodiumKey($this->secret);

        /*
         * A \SodiumException "nonce size should be SODIUM_CRYPTO_SECRETBOX_NONCEBYTES bytes" can occur if $nonce is not of the correct length
         */
        try {
            return sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        } catch (\SodiumException $exception) {
            // TODO throw more specific exception
            throw new \InvalidArgumentException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    private function getSodiumKey(string $secret): string
    {
        $secretLength = strlen($secret);
        if ($secretLength > SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return substr($secret, 0, \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        }
        if ($secretLength < SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return sodium_pad($secret, \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        }

        return $secret;
    }
}
