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
 * @author Hamza Amrouche <hamza.simperfit@gmail.com>
 */
interface BlockingStoreInterface
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

    /**
     * Checks if the store can wait until a key becomes free before storing the resource.
     */
    public function supportsWaitAndSave(): bool;
}
