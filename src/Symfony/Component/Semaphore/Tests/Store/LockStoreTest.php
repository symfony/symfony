<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests\Store;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Semaphore\PersistingStoreInterface;
use Symfony\Component\Semaphore\Store\LockStore;

class LockStoreTest extends AbstractStoreTestCase
{
    public function getStore(): PersistingStoreInterface
    {
        $lock = new FlockStore();
        $factory = new LockFactory($lock);

        return new LockStore($factory);
    }
}
