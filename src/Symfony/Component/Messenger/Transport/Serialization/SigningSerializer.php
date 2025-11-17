<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Serialization;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidMessageSignatureException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class SigningSerializer implements SerializerInterface
{
    /**
     * @param list<class-string> $signedMessageTypes
     */
    public function __construct(
        private SerializerInterface $inner,
        #[\SensitiveParameter] private string|\Stringable $signingKey,
        private array $signedMessageTypes,
        private string $algorithm = 'sha256',
    ) {
    }

    public function encode(Envelope $envelope): array
    {
        $encoded = $this->inner->encode($envelope);
        $type = $envelope->getMessage()::class;

        if ($this->shouldSign($type)) {
            $encoded['headers']['Body-Sign'] = hash_hmac($this->algorithm, $encoded['body'] ?? '', $this->signingKey);
            $encoded['headers']['Sign-Algo'] = $this->algorithm;
        }

        return $encoded;
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $envelope = $this->inner->decode($encodedEnvelope);
        $type = $envelope->getMessage()::class;

        if (!$this->shouldSign($type)) {
            return $envelope;
        }

        $headers = $encodedEnvelope['headers'] ?? [];

        if (!$sign = $headers['Body-Sign'] ?? null) {
            throw new InvalidMessageSignatureException(\sprintf('Message "%s" requires a signature but none was found.', $type));
        }

        if ($this->algorithm !== $algo = $headers['Sign-Algo'] ?? $this->algorithm) {
            throw new InvalidMessageSignatureException(\sprintf('Expected "%s" signature algorithm for message "%s", "%s" given.', $this->algorithm, $type, $algo));
        }

        $expected = hash_hmac($algo, $encodedEnvelope['body'] ?? '', $this->signingKey);
        if (!hash_equals($sign, $expected)) {
            throw new InvalidMessageSignatureException(\sprintf('Invalid signature for message "%s".', $type));
        }

        unset($headers['Body-Sign'], $headers['Sign-Algo']);
        $encodedEnvelope['headers'] = $headers;

        return $envelope;
    }

    private function shouldSign(string $type): bool
    {
        foreach ($this->signedMessageTypes as $signedType) {
            if (is_a($type, $signedType, true)) {
                return true;
            }
        }

        return false;
    }
}
