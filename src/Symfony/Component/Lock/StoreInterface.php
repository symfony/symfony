<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock;

use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\NotSupportedException;

/**
 * StoreInterface defines an interface to manipulate a lock store.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @deprecated since Symfony 4.4, use PersistingStoreInterface and BlockingStoreInterface instead
 */
interface StoreInterface extends PersistingStoreInterface
{
    /**
     * Waits until a key becomes free, then stores the resource.
     *
     * If the store does not support this feature it should throw a NotSupportedException.
     *
     * @throws LockConflictedException
     * @throws NotSupportedException
     */
    public function waitAndSave(Key $key);
}
