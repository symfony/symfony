<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Secret\Encoder;

use Symfony\Bundle\FrameworkBundle\Exception\EncryptionKeyNotFoundException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Tobias Schultze <http://tobion.de>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class SodiumEncoder implements EncoderInterface
{
    private $encryptionKey;
    private $encryptionKeyPath;

    public function __construct(string $encryptionKeyPath)
    {
        if (!\function_exists('\sodium_crypto_stream_xor')) {
            throw new \RuntimeException('The "sodium" PHP extension is not loaded.');
        }

        $this->encryptionKeyPath = $encryptionKeyPath;
    }

    /**
     * {@inheritdoc}
     */
    public function generateKeys(bool $override = false): array
    {
        if (!$override && file_exists($this->encryptionKeyPath)) {
            throw new \LogicException(sprintf('A key already exists in "%s".', $this->encryptionKeyPath));
        }

        $this->encryptionKey = null;

        $encryptionKey = sodium_crypto_stream_keygen();
        (new Filesystem())->dumpFile($this->encryptionKeyPath, $encryptionKey);
        sodium_memzero($encryptionKey);

        return [$this->encryptionKeyPath];
    }

    /**
     * {@inheritdoc}
     */
    public function encrypt(string $secret): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_STREAM_NONCEBYTES);

        $key = $this->getKey();
        $encryptedSecret = sodium_crypto_stream_xor($secret, $nonce, $key);
        sodium_memzero($secret);
        sodium_memzero($key);

        return $this->encode($nonce, $encryptedSecret);
    }

    public function decrypt(string $encryptedSecret): string
    {
        [$nonce, $encryptedSecret] = $this->decode($encryptedSecret);

        $key = $this->getKey();
        $secret = sodium_crypto_stream_xor($encryptedSecret, $nonce, $key);
        sodium_memzero($key);

        return $secret;
    }

    private function getKey(): string
    {
        if (isset($this->encryptionKey)) {
            return $this->encryptionKey;
        }
        if (!is_file($this->encryptionKeyPath)) {
            throw new EncryptionKeyNotFoundException($this->encryptionKeyPath);
        }

        return $this->encryptionKey = file_get_contents($this->encryptionKeyPath);
    }

    private function encode(string $nonce, string $encryptedSecret): string
    {
        return $nonce.$encryptedSecret;
    }

    /**
     * @return array [$nonce, $encryptedSecret]
     */
    private function decode(string $message): array
    {
        if (\strlen($message) < \SODIUM_CRYPTO_STREAM_NONCEBYTES) {
            throw new \UnexpectedValueException(sprintf('Invalid encrypted secret, message should be at least %s chars long.', \SODIUM_CRYPTO_STREAM_NONCEBYTES));
        }

        $nonce = substr($message, 0, \SODIUM_CRYPTO_STREAM_NONCEBYTES);
        $encryptedSecret = substr($message, \SODIUM_CRYPTO_STREAM_NONCEBYTES);

        return [$nonce, $encryptedSecret];
    }
}
