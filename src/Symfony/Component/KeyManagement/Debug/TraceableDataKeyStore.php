<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Debug;

use Symfony\Component\KeyManagement\DataCollector\KeyManagementDataCollector;
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\DataKeyStoreInterface;
use Symfony\Component\KeyManagement\RewrappableDataKeyStoreInterface;

/**
 * Reports to a {@see KeyManagementDataCollector} the data keys an application asks a store for.
 *
 * This is where the point of a store shows: the collector nests under each call the KMS round
 * trips it made, so a `current()` reporting none is a key the store already held in memory, and
 * the reference it returns is what tells which stored key a payload was written against.
 *
 * Only the encryption path is decorated. The administration half,
 * {@see RewrappableDataKeyStoreInterface}, is left to the store itself: rewrapping runs from the
 * console over every row, where a profiler panel has nothing to say, and a decorator claiming that
 * interface over a store that declines it would break the same capability detection
 * {@see TraceableKms} exists to preserve.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class TraceableDataKeyStore implements DataKeyStoreInterface
{
    use TracesCalls;

    public function __construct(
        private readonly DataKeyStoreInterface $store,
        KeyManagementDataCollector $collector,
        string $name,
    ) {
        $this->collector = $collector;
        $this->tracedService = $name;
        $this->tracedBackend = get_debug_type($store);
    }

    /**
     * The decorated store, for whoever needs to look past the decorator, and for the rewrapping
     * half this one does not claim.
     */
    public function getStore(): DataKeyStoreInterface
    {
        return $this->store;
    }

    public function current(string $scope): DataKeyHandle
    {
        $start = microtime(true);
        $call = ['key' => $scope];

        try {
            $handle = $this->store->current($scope);
            $call['reference'] = $handle->reference;

            return $handle;
        } catch (\Throwable $e) {
            $call['error'] = self::describe($e);

            throw $e;
        } finally {
            $this->record(KeyManagementDataCollector::LAYER_STORE, 'current', $start, $call);
        }
    }

    public function get(string $reference): DataKeyHandle
    {
        $start = microtime(true);
        $call = ['reference' => $reference];

        try {
            return $this->store->get($reference);
        } catch (\Throwable $e) {
            $call['error'] = self::describe($e);

            throw $e;
        } finally {
            $this->record(KeyManagementDataCollector::LAYER_STORE, 'get', $start, $call);
        }
    }
}
