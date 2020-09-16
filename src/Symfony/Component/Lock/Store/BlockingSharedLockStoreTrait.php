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

use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;

trait BlockingSharedLockStoreTrait
{
    abstract public function saveRead(Key $key);

    public function waitAndSaveRead(Key $key)
    {
        while (true) {
            try {
                $this->saveRead($key);

                return;
            } catch (LockConflictedException $e) {
                usleep((100 + random_int(-10, 10)) * 1000);
            }
        }
    }
}
