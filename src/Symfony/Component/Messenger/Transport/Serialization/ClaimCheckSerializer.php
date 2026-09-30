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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ClaimCheckNotFoundException;
use Symfony\Component\Messenger\Exception\ClaimCheckStorageException;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

/**
 * Stores oversized encoded envelopes in a cache pool.
 *
 * When using a SigningSerializer, pass it as the inner serializer. Wrapping this serializer in a SigningSerializer
 * would sign only the claim reference. The stored envelope would remain unsigned, allowing a spoofed type header
 * to bypass signature verification.
 */
final class ClaimCheckSerializer implements SerializerInterface, MessageTypeAwareSerializerInterface
{
    private const HEADER = 'X-Symfony-Messenger-Claim-Check';
    private const TYPE_HEADER = 'X-Symfony-Messenger-Claim-Check-Type';
    private const BUS_HEADER = 'X-Symfony-Messenger-Claim-Check-Bus';

    /**
     * @param CacheItemPoolInterface $pool    Dedicated pool configured to retain claims for the complete message lifetime
     * @param int                    $maxSize Maximum encoded envelope size in bytes
     */
    public function __construct(
        private SerializerInterface $inner,
        private CacheItemPoolInterface $pool,
        private int $maxSize,
    ) {
        if (0 >= $maxSize) {
            throw new InvalidArgumentException('The claim check maximum size must be greater than zero.');
        }
    }

    public function encode(Envelope $envelope): array
    {
        $encodedEnvelope = $this->inner->encode($envelope);
        if ($this->getSize($encodedEnvelope) <= $this->maxSize) {
            return $encodedEnvelope;
        }

        // a claim that could not be retrieved is sent again with its reference, which the payload never left
        if (($message = $envelope->getMessage()) instanceof MessageDecodingFailedException && '1' === ($message->encodedEnvelope['headers'][self::HEADER] ?? null)) {
            return $encodedEnvelope;
        }

        $data = serialize($encodedEnvelope);
        $id = $this->storeClaim($data);
        $claim = [
            'body' => json_encode(['id' => $id, 'digest' => hash('sha256', $data)], \JSON_THROW_ON_ERROR),
            'headers' => [self::HEADER => '1', self::TYPE_HEADER => $envelope->getMessage()::class],
        ];

        // the stamps stay in the claim: a failure to retrieve it still needs the bus of the message
        if ($busNameStamp = $envelope->last(BusNameStamp::class)) {
            $claim['headers'][self::BUS_HEADER] = $busNameStamp->getBusName();
        }

        if ($this->getSize($claim) > $this->maxSize) {
            $this->removeClaim($id);

            throw new ClaimCheckStorageException(\sprintf('The claim check reference is larger than the configured maximum size of %d bytes.', $this->maxSize));
        }

        return $claim;
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        if (!$this->isClaimReference($encodedEnvelope)) {
            return $this->inner->decode($encodedEnvelope);
        }

        try {
            $claim = json_decode($encodedEnvelope['body'], true, flags: \JSON_THROW_ON_ERROR);
            if (!\is_array($claim) || !\is_string($claim['id'] ?? null) || !\is_string($claim['digest'] ?? null)) {
                throw new ClaimCheckStorageException('The claim check reference is invalid.');
            }

            $data = $this->retrieveClaim($claim['id']);
            if (!hash_equals(hash('sha256', $data), $claim['digest'])) {
                throw new ClaimCheckStorageException('The claim check integrity check failed.');
            }

            $claimedEnvelope = $this->decodeClaim($data);
        } catch (\Throwable $e) {
            $failure = MessageDecodingFailedException::wrap($encodedEnvelope, $e->getMessage(), (int) $e->getCode(), $e);

            return \is_string($busName = $encodedEnvelope['headers'][self::BUS_HEADER] ?? null) ? $failure->with(new BusNameStamp($busName)) : $failure;
        }

        if (isset($encodedEnvelope['extra'])) {
            $claimedEnvelope['extra'] = $encodedEnvelope['extra'];
        }

        $envelope = $this->inner->decode($claimedEnvelope);

        // nothing signs the reference: the bus it names must be the one of the claimed envelope
        if (\is_string($busName = $encodedEnvelope['headers'][self::BUS_HEADER] ?? null) && $busName !== ($envelope->last(BusNameStamp::class)?->getBusName() ?? $busName)) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, 'The claim check integrity check failed: the reference names another bus than the claimed envelope.');
        }

        return $envelope;
    }

    public function getMessageType(array $encodedEnvelope): ?string
    {
        if ($this->isClaimReference($encodedEnvelope)) {
            $type = $encodedEnvelope['headers'][self::TYPE_HEADER] ?? null;

            return \is_string($type) ? $type : null;
        }

        return $this->inner instanceof MessageTypeAwareSerializerInterface ? $this->inner->getMessageType($encodedEnvelope) : null;
    }

    /**
     * Tells a claim reference apart from a failure to retrieve one that is sent again.
     *
     * Such a failure keeps the headers of the reference, and the inner serializer adds a "type" header that a reference never has.
     * The inner serializer decodes it then, with its stamps, and the failure carries the reference to retrieve on replay.
     */
    private function isClaimReference(array $encodedEnvelope): bool
    {
        return '1' === ($encodedEnvelope['headers'][self::HEADER] ?? null) && !isset($encodedEnvelope['headers']['type']);
    }

    /**
     * @param array{body: string, headers?: array<string, string>} $encodedEnvelope
     */
    private function getSize(array $encodedEnvelope): int
    {
        $size = \strlen($encodedEnvelope['body'] ?? '');
        foreach ($encodedEnvelope['headers'] ?? [] as $name => $value) {
            $size += \strlen($name) + \strlen($value);
        }

        return $size;
    }

    private function storeClaim(string $data): string
    {
        try {
            $id = bin2hex(random_bytes(16));
            if (!$this->pool->save($this->pool->getItem($id)->set($data))) {
                throw new ClaimCheckStorageException('Unable to store the claim check.');
            }

            return $id;
        } catch (ClaimCheckStorageException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ClaimCheckStorageException('Unable to store the claim check.', previous: $e);
        }
    }

    private function retrieveClaim(string $id): string
    {
        try {
            $item = $this->pool->getItem($id);
            if (!$item->isHit()) {
                throw new ClaimCheckNotFoundException($id);
            }

            if (!\is_string($data = $item->get())) {
                throw new ClaimCheckStorageException('The claimed data is invalid.');
            }

            return $data;
        } catch (ClaimCheckNotFoundException|ClaimCheckStorageException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ClaimCheckStorageException('Unable to retrieve the claim check.', previous: $e);
        }
    }

    private function removeClaim(string $id): void
    {
        try {
            $this->pool->deleteItem($id);
        } catch (\Throwable) {
        }
    }

    /**
     * @return array{body: string, headers?: array<string, string>}
     */
    private function decodeClaim(string $data): array
    {
        $encodedEnvelope = @unserialize($data, ['allowed_classes' => false]);
        if (!\is_array($encodedEnvelope) || !\is_string($encodedEnvelope['body'] ?? null)) {
            throw new ClaimCheckStorageException('The claimed envelope is invalid.');
        }
        if (isset($encodedEnvelope['headers']) && !\is_array($encodedEnvelope['headers'])) {
            throw new ClaimCheckStorageException('The claimed envelope is invalid.');
        }

        foreach ($encodedEnvelope['headers'] ?? [] as $name => $value) {
            if (!\is_string($name) || !\is_string($value)) {
                throw new ClaimCheckStorageException('The claimed envelope is invalid.');
            }
        }

        return $encodedEnvelope;
    }
}
