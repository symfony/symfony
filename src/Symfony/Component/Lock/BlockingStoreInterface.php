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
 * @author Hamza Amrouche <hamza.simperfit@gmail.com>
 */
interface BlockingStoreInterface extends PersistingStoreInterface
{
    /**
     * Waits until a key becomes free, then stores the resource.
     *
     * @return void
     *
     * @throws LockConflictedException
     */
    public function waitAndSave(Key $key);
}
