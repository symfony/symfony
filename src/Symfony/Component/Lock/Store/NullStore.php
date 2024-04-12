<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Store;

use Symfony\Component\Lock\BlockingSharedLockStoreInterface;
use Symfony\Component\Lock\Key;

/**
 * NullStore is a PersistingStoreInterface implementation which discards all operations.
 *
 * @author Pavel Bartoň <barton@webwings.cz>
 */
class NullStore implements BlockingSharedLockStoreInterface
{
    public function save(Key $key): void
    {
    }

    public function saveRead(Key $key): void
    {
    }

    public function waitAndSaveRead(Key $key): void
    {
    }

    public function delete(Key $key): void
    {
    }

    public function exists(Key $key): bool
    {
        return false;
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
    }
}
