<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption;

use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\MalformedCipherException;

/**
 * This class is responsible for creating and parsing Symfony Encryption Tokens.
 * It is an helper class for the EncryptionInterface implementations. In all common
 * scenarios, one does not need to interact with this class directly.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class SymfonyEncryptionToken implements \Stringable
{
    /**
     * Algorithm used to encrypt the message.
     */
    private string $algorithm;
    private string $version;
    private string $ciphertext;

    /**
     * @var array<string, string>
     */
    private array $headers = [];

    private function __construct()
    {
    }

    /**
     * @param array<string, string> $headers keys MUST be ascii values only
     */
    public static function create(string $algorithm, string $ciphertext, array $headers = []): self
    {
        $model = new self();
        $model->algorithm = $algorithm;
        $model->ciphertext = $ciphertext;
        $model->headers = $headers;

        return $model;
    }

    /**
     * Take a string representation of the ciphertext and parse it into an object.
     *
     * @throws MalformedCipherException
     */
    public static function parse(string $input): self
    {
        $parts = explode('.', $input);
        if (!\is_array($parts) || 3 !== \count($parts)) {
            throw new MalformedCipherException();
        }

        [$headersString, $ciphertext, $hashSignature] = $parts;

        $headersString = self::base64UrlDecode($headersString);
        $ciphertext = self::base64UrlDecode($ciphertext);
        $hashSignature = self::base64UrlDecode($hashSignature);

        // Check if data has been modified
        $hash = hash('sha256', $headersString.$ciphertext);
        if (!hash_equals($hash, $hashSignature)) {
            throw new MalformedCipherException();
        }

        $headers = json_decode($headersString, true);
        if (!\is_array($headers) || !\array_key_exists('alg', $headers) || !\array_key_exists('ver', $headers)) {
            throw new MalformedCipherException();
        }

        foreach ($headers as $name => $value) {
            $headers[$name] = self::base64UrlDecode($value);
        }

        $model = new self();
        $model->algorithm = $headers['alg'];
        unset($headers['alg']);
        $model->version = $headers['ver'];
        unset($headers['ver']);
        $model->headers = $headers;
        $model->ciphertext = $ciphertext;

        return $model;
    }

    public function __toString(): string
    {
        return $this->getString();
    }

    public function getString(): string
    {
        $headers = $this->headers;
        $headers['alg'] = $this->algorithm;
        $headers['ver'] = (isset($headers['ver']) && '' !== $headers['ver']) ? $headers['ver'] : '1';
        foreach ($headers as $name => $value) {
            $headers[$name] = self::base64UrlEncode($value);
        }
        $headers = json_encode($headers);

        return sprintf('%s.%s.%s',
            self::base64UrlEncode($headers),
            self::base64UrlEncode($this->ciphertext),
            self::base64UrlEncode(hash('sha256', $headers.$this->ciphertext))
        );
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getCiphertext(): string
    {
        return $this->ciphertext;
    }

    public function hasHeader(string $name): bool
    {
        return \array_key_exists($name, $this->headers);
    }

    public function getHeader(string $name): string
    {
        if ($this->hasHeader($name)) {
            return $this->headers[$name];
        }

        throw new DecryptionException(sprintf('The expected header "%s" is not found.', $name));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $decodedContent = base64_decode(strtr($data, '-_', '+/'), true);

        if (!\is_string($decodedContent)) {
            throw new MalformedCipherException('Could not base64 decode the content.');
        }

        return $decodedContent;
    }
}
