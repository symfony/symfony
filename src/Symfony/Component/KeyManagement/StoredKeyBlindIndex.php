<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement;

use Symfony\Component\KeyManagement\BlindIndex\AbstractBlindIndex;
use Symfony\Component\KeyManagement\BlindIndex\AlgorithmInterface;
use Symfony\Component\KeyManagement\BlindIndex\CoveringProjectionInterface;
use Symfony\Component\KeyManagement\BlindIndex\ProjectionInterface;
use Symfony\Component\KeyManagement\Exception\DataKeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;

/**
 * A {@see BlindIndexInterface} over a data key a {@see DataKeyStoreInterface} holds.
 *
 * The store opens the key once for every index over it, and for whatever else encrypts under it, so
 * two indexed columns of one key cost one opening instead of one each. Each index names itself, so
 * sharing the key does not make them tag a value alike.
 *
 * By reference and not by scope, because {@see DataKeyStoreInterface::current()} is free to retire
 * a key and mint another, which for an index means every tag already written stops matching. Take
 * the reference once and record it:
 *
 *     $reference = $store->current('blind-index/tenant-42')->reference;
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class StoredKeyBlindIndex extends AbstractBlindIndex
{
    /**
     * @param string $reference Reference of the stored data key the tags are derived under, which may serve other indexes but never rotates
     * @param string $name      Names this index among those over that key, and keys its derivation
     */
    public function __construct(
        private readonly DataKeyStoreInterface $store,
        private readonly string $reference,
        string $name,
        ProjectionInterface|CoveringProjectionInterface $projection,
        ?AlgorithmInterface $algorithm = null,
    ) {
        parent::__construct($name, $projection, $algorithm);
    }

    /**
     * @throws DataKeyNotFoundException  If the store no longer holds the index key
     * @throws DecryptionFailedException If the index key cannot be unwrapped
     */
    protected function open(): DataKeyHandle
    {
        return $this->store->get($this->reference);
    }
}
