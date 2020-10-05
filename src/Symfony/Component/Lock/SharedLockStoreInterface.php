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

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface SharedLockStoreInterface extends PersistingStoreInterface
{
    /**
     * Stores the resource if it's not locked for reading by someone else.
     *
     * @throws LockConflictedException
     */
    public function saveRead(Key $key);
}
