<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Test;

use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\Exception\DataKeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\RewrappableDataKeyStoreInterface;
use Symfony\Component\KeyManagement\StoredDataKey;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Array-backed {@see RewrappableDataKeyStoreInterface} for tests.
 *
 * Behaves like a persistent store in every respect that matters to a test: data keys are wrapped
 * through a real KMS client, references are UUIDv7 so they order chronologically the way a database
 * would order them, and rewrapping only touches the wrapping. It merely forgets everything when the
 * process ends, which is why it lives here rather than in the component's public surface.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class InMemoryDataKeyStore implements RewrappableDataKeyStoreInterface
{
    /**
     * @var array<string, StoredDataKey> keyed by reference, in creation order
     */
    private array $rows = [];

    /**
     * @var array<string, DataKeyHandle> keyed by reference
     */
    private array $handles = [];

    /**
     * @var array<string, DataKeyGeneratorInterface>
     */
    private readonly array $clients;

    /**
     * @param array<string, DataKeyGeneratorInterface> $clients       KMS clients indexed by name; defaults to a
     *                                                                single {@see InMemoryKms} under `$client`
     * @param positive-int                             $keyBytes      length of the data keys to generate
     * @param int<0, max>|null                         $maxAgeSeconds age past which {@see current()} rotates;
     *                                                                `0` rotates on every call, `null` never
     */
    public function __construct(
        array $clients = [],
        private readonly string $client = 'default',
        private readonly string $masterKeyId = 'app',
        private readonly int $keyBytes = 32,
        private readonly ?int $maxAgeSeconds = null,
    ) {
        $this->clients = $clients ?: [$this->client => new InMemoryKms()];
    }

    public function current(string $scope): DataKeyHandle
    {
        $row = $this->newest($scope);

        return null !== $row && !$this->isRetired($row) ? $this->handleFor($row) : $this->rotate($scope);
    }

    public function get(string $reference): DataKeyHandle
    {
        return $this->handleFor($this->rows[$reference] ?? throw new DataKeyNotFoundException($reference));
    }

    public function all(?string $client = null): iterable
    {
        foreach ($this->rows as $row) {
            if (null === $client || $client === $row->client) {
                yield $row;
            }
        }
    }

    /**
     * The cached handle survives on purpose: rewrapping changes how the data key is protected, not
     * the key itself, so anything already encrypted with it stays valid.
     */
    public function rewrap(string $reference, Ciphertext $wrapped, string $client): void
    {
        $row = $this->rows[$reference] ?? throw new DataKeyNotFoundException($reference);

        $this->rows[$reference] = new StoredDataKey($reference, $row->scope, $wrapped, $client);
    }

    /**
     * The plaintext is deliberately taken out of the {@see DataKey} and retained: a store exists to
     * unwrap once and encrypt many payloads. The handle takes a buffer of its own as it does so, so
     * the DataKey still wipes what it held.
     */
    public function rotate(string $scope): DataKeyHandle
    {
        $dataKey = $this->clientFor($this->client)->generateDataKey($this->masterKeyId, $this->keyBytes);
        $reference = Uuid::v7()->toBinary();

        $this->rows[$reference] = new StoredDataKey($reference, $scope, $dataKey->wrapped, $this->client);

        return $this->handles[$reference] = new DataKeyHandle($reference, $dataKey);
    }

    /**
     * Drops the retained plaintexts, so the next resolution goes back through the KMS the way a
     * fresh process would.
     */
    public function forget(): void
    {
        foreach ($this->handles as $handle) {
            $handle->release();
        }

        $this->handles = [];
    }

    private function handleFor(StoredDataKey $row): DataKeyHandle
    {
        if (isset($this->handles[$row->reference]) && !$this->handles[$row->reference]->isReleased()) {
            return $this->handles[$row->reference];
        }

        $dataKey = $this->clientFor($row->client)->unwrapDataKey($row->wrapped);

        return $this->handles[$row->reference] = new DataKeyHandle($row->reference, $dataKey);
    }

    private function newest(string $scope): ?StoredDataKey
    {
        $newest = null;
        foreach ($this->rows as $row) {
            if ($scope === $row->scope) {
                $newest = $row;
            }
        }

        return $newest;
    }

    /**
     * The creation instant is read back from the UUIDv7 reference, which is why the store needs no
     * timestamp of its own.
     */
    private function isRetired(StoredDataKey $row): bool
    {
        if (null === $this->maxAgeSeconds) {
            return false;
        }

        $uid = Uuid::fromBinary($row->reference);

        if (!$uid instanceof TimeBasedUidInterface) {
            return false;
        }

        // A UUIDv7 minted while others share its millisecond carries an instant pushed forward to
        // keep the ordering, which can land ahead of the clock read here. Clamping the age at zero
        // keeps such a key from counting as not yet born, so a max age of zero still rotates.
        return max(0, time() - $uid->getDateTime()->getTimestamp()) >= $this->maxAgeSeconds;
    }

    private function clientFor(string $name): DataKeyGeneratorInterface
    {
        return $this->clients[$name] ?? throw new LogicException(\sprintf('No KMS client named "%s" was given to the store; available: "%s".', $name, implode('", "', array_keys($this->clients))));
    }
}
