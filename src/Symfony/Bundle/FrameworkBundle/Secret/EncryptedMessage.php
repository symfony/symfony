<?php

namespace Symfony\Bundle\FrameworkBundle\Secret;

class EncryptedMessage
{
    /**
     * @var string
     */
    private $ciphertext;

    /**
     * @var string
     */
    private $nonce;

    public function __construct(string $ciphertext, string $nonce)
    {
        $this->ciphertext = $ciphertext;
        $this->nonce = $nonce;
    }

    public function __toString()
    {
        return $this->nonce.$this->ciphertext;
    }

    public function getCiphertext(): string
    {
        return $this->ciphertext;
    }

    public function getNonce(): string
    {
        return $this->nonce;
    }

    public static function createFromString(string $message): self
    {
        if (\strlen($message) < SODIUM_CRYPTO_STREAM_NONCEBYTES) {
            throw new \RuntimeException('Invalid ciphertext. Message is too short.');
        }

        $nonce = substr($message, 0, SODIUM_CRYPTO_STREAM_NONCEBYTES);
        $ciphertext = substr($message, SODIUM_CRYPTO_STREAM_NONCEBYTES);

        return new self($ciphertext, $nonce);
    }
}
