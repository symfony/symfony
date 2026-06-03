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
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;

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
        $headers = $encodedEnvelope['headers'] ?? [];
        $sign = $headers['Body-Sign'] ?? null;

        try {
            if ($sign && hash_equals(hash_hmac($this->algorithm, $encodedEnvelope['body'] ?? '', $this->signingKey), $sign)) {
                // A valid signature authenticates the message whatever its type: decode it without peeking.
                // The algorithm is implied by the HMAC itself, so the "Sign-Algo" header isn't consulted here.
                unset($encodedEnvelope['headers']['Body-Sign'], $encodedEnvelope['headers']['Sign-Algo']);

                return $this->inner->decode($encodedEnvelope);
            }

            $envelope = null;

            if (!$this->inner instanceof MessageTypeAwareSerializerInterface) {
                $envelope = $this->inner->decode($encodedEnvelope);
                $type = $envelope->getMessage()::class;
            } elseif (null === $type = $this->inner->getMessageType($encodedEnvelope)) {
                throw new InvalidMessageSignatureException('The message could not be verified and its type could not be determined; refusing to decode it.');
            }

            if (!$this->shouldSign($type)) {
                return $envelope ?? $this->inner->decode($encodedEnvelope);
            }

            if (!$sign) {
                throw new InvalidMessageSignatureException(\sprintf('Message "%s" requires a signature but none was found.', $type));
            }

            if ($this->algorithm !== $algo = $headers['Sign-Algo'] ?? $this->algorithm) {
                throw new InvalidMessageSignatureException(\sprintf('Expected "%s" signature algorithm for message "%s", "%s" given.', $this->algorithm, $type, $algo));
            }

            throw new InvalidMessageSignatureException(\sprintf('Invalid signature for message "%s".', $type));
        } catch (\Throwable $e) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, $e->getMessage(), (int) $e->getCode(), $e);
        }
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
