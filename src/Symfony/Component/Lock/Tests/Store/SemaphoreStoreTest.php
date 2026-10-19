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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Lock\Test\AbstractStoreTestCase;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
#[RequiresPhpExtension('sysvsem')]
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
        $key = new Key(__METHOD__);
        $store->waitAndSave($key);

        $semaphore = $key->getState(SemaphoreStore::class);
        $this->assertInstanceOf(\SysvSemaphore::class, $semaphore);

        $store->delete($key);
        $this->assertFalse(@sem_release($semaphore), 'The semaphore should be removed');
    }

    public function testProjectIdSeparatesLocks()
    {
        $keyName = __METHOD__;
        $storeA = new SemaphoreStore('project-a');
        $storeB = new SemaphoreStore('project-b');

        $keyA = new Key($keyName);
        $keyB = new Key($keyName);

        $storeA->save($keyA);
        $storeB->save($keyB);

        $this->assertTrue($storeA->exists($keyA));
        $this->assertTrue($storeB->exists($keyB));

        $storeA->delete($keyA);
        $storeB->delete($keyB);
    }

    public function testSameProjectIdCollidesOnSameKey()
    {
        $keyName = __METHOD__;
        $storeA = new SemaphoreStore('shared-project');
        $storeB = new SemaphoreStore('shared-project');

        $keyA = new Key($keyName);
        $keyB = new Key($keyName);

        $storeA->save($keyA);

        try {
            $this->expectException(LockConflictedException::class);
            $storeB->save($keyB);
        } finally {
            $storeA->delete($keyA);
            $storeB->delete($keyB);
        }
    }
}
