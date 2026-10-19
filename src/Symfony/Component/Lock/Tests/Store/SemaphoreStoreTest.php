<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\SemaphoreStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension sysvsem
 */
class SemaphoreStoreTest extends AbstractStoreTestCase
{
    use BlockingStoreTestTrait;
    use UnserializableTestTrait;

    protected function getStore(): PersistingStoreInterface
    {
        return new SemaphoreStore();
    }

    public function testResourceRemoval()
    {
        $store = new SemaphoreStore();
        $key = new Key(uniqid(__METHOD__, true));
        $store->waitAndSave($key);

        $semaphore = $key->getState(SemaphoreStore::class);
        $this->assertInstanceOf(\SysvSemaphore::class, $semaphore);

        $store->delete($key);
        $this->assertFalse(@sem_release($semaphore), 'The semaphore should be removed');
    }
}
