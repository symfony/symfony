<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Encryption;

use Symfony\Component\Encryption\Engine\EngineSelector;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;
use Symfony\Component\Encryption\Exception\SignatureVerificationException;
use Symfony\Component\Encryption\Key\KeyPair;
use Symfony\Component\Encryption\Key\PrivateKey;
use Symfony\Component\Encryption\Key\PublicKey;

/**
 * Digital signatures. The default is Ed25519; RSA and ECDSA P-256 are available
 * via generateKeyPair('rsa') / generateKeyPair('ecdsa-p256').
 *
 * Detached: signDetached returns a base64 signature; verifyDetached checks it.
 * Attached: signAttached returns base64(length-prefixed signature‖message);
 * openAttached verifies and returns the original message, or throws.
 *
 * The backend is selected automatically and hidden; signatures dispatch on the
 * key's algorithm.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
final class Signer implements SignerInterface
{
    private const DEFAULT_ALGORITHM = 'ed25519';

    public function __construct(
        private readonly EngineSelector $engines = new EngineSelector(),
    ) {
    }

    #[\Override]
    public function generateKeyPair(string $algorithm = self::DEFAULT_ALGORITHM): KeyPair
    {
        $engine = $this->engines->signatureEngine($algorithm);
        [$public, $private] = $engine->generateKeyPair();

        return new KeyPair(
            PublicKey::fromBytes($algorithm, 'signing', $public),
            PrivateKey::fromBytes($algorithm, 'signing', $private),
        );
    }

    #[\Override]
    public function signDetached(string $message, PrivateKey $key): string
    {
        $this->assertSigningKey($key);

        return Encoding::toBase64($this->engines->signatureEngine($key->algorithm())->sign($message, $key->bytes()));
    }

    #[\Override]
    public function verifyDetached(string $signature, string $message, PublicKey $key): bool
    {
        $this->assertSigningKey($key);

        try {
            $raw = Encoding::fromBase64($signature);
        } catch (InvalidArgumentException) {
            return false;
        }

        return $this->engines->signatureEngine($key->algorithm())->verify($raw, $message, $key->bytes());
    }

    #[\Override]
    public function signAttached(string $message, PrivateKey $key): string
    {
        $this->assertSigningKey($key);
        $signature = $this->engines->signatureEngine($key->algorithm())->sign($message, $key->bytes());

        return Encoding::toBase64(pack('n', \strlen($signature)) . $signature . $message);
    }

    #[\Override]
    public function openAttached(string $signedMessage, PublicKey $key): string
    {
        $this->assertSigningKey($key);

        try {
            $raw = Encoding::fromBase64($signedMessage);
        } catch (InvalidArgumentException $e) {
            throw new SignatureVerificationException('Malformed signed message.', 0, $e);
        }

        if (\strlen($raw) < 2) {
            throw new SignatureVerificationException('Signed message is too short to be valid.');
        }

        /** @var array{1: int} $header */
        $header = unpack('n', substr($raw, 0, 2));
        $signatureLength = $header[1];
        if (\strlen($raw) < 2 + $signatureLength) {
            throw new SignatureVerificationException('Signed message is malformed.');
        }

        $signature = substr($raw, 2, $signatureLength);
        $message = substr($raw, 2 + $signatureLength);

        if (!$this->engines->signatureEngine($key->algorithm())->verify($signature, $message, $key->bytes())) {
            throw new SignatureVerificationException('Signature verification failed.');
        }

        return $message;
    }

    private function assertSigningKey(PublicKey|PrivateKey $key): void
    {
        if ('signing' !== $key->purpose()) {
            throw new InvalidKeyException(\sprintf('Expected a signing key; got purpose "%s".', $key->purpose()));
        }
    }
}
