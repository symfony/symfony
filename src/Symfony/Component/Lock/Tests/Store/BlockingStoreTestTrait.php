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

use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\StoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
trait BlockingStoreTestTrait
{
    /**
     * @see AbstractStoreTest::getStore()
     */
    abstract protected function getStore();

    /**
     * Tests blocking locks thanks to pcntl.
     *
     * This test is time sensible: the $clockDelay could be adjust.
     *
     * @requires extension pcntl
     */
    public function testBlockingLocks()
    {
        // Amount a microsecond used to order async actions
        $clockDelay = 50000;

        /** @var StoreInterface $store */
        $store = $this->getStore();
        $key = new Key(uniqid(__METHOD__, true));

        if ($childPID1 = pcntl_fork()) {
            // give time to fork to start
            usleep(2 * $clockDelay);

            try {
                // This call should failed given the lock should already by acquired by the child #1
                $store->save($key);
                $this->fail('The store saves a locked key.');
            } catch (LockConflictedException $e) {
            }

            // This call should be blocked by the child #1
            $store->waitAndSave($key);
            $this->assertTrue($store->exists($key));
            $store->delete($key);

            // Now, assert the child process worked well
            pcntl_waitpid($childPID1, $status1);
            $this->assertSame(0, pcntl_wexitstatus($status1), 'The child process couldn\'t lock the resource');
        } else {
            try {
                $store->save($key);
                // Wait 3 ClockDelay to let parent process to finish
                usleep(3 * $clockDelay);
                $store->delete($key);
                exit(0);
            } catch (\Exception $e) {
                exit(1);
            }
        }
    }
}
