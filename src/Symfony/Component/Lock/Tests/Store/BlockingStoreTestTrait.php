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
        $clockDelay = 30000;

        if (PHP_VERSION_ID < 50600 || defined('HHVM_VERSION_ID')) {
            $this->markTestSkipped('The PHP engine does not keep resource in child forks');

            return;
        }

        /** @var StoreInterface $store */
        $store = $this->getStore();
        $key = new Key(uniqid(__METHOD__, true));

        if ($childPID1 = pcntl_fork()) {
            if ($childPID2 = pcntl_fork()) {
                if ($childPID3 = pcntl_fork()) {
                    // This is the parent, wait for the end of child process to assert their results
                    pcntl_waitpid($childPID1, $status1);
                    pcntl_waitpid($childPID2, $status2);
                    pcntl_waitpid($childPID3, $status3);
                    $this->assertSame(0, pcntl_wexitstatus($status1));
                    $this->assertSame(0, pcntl_wexitstatus($status2));
                    $this->assertSame(3, pcntl_wexitstatus($status3));
                } else {
                    usleep(2 * $clockDelay);

                    try {
                        // This call should failed given the lock should already by acquired by the child #1
                        $store->save($key);
                        exit(0);
                    } catch (\Exception $e) {
                        exit(3);
                    }
                }
            } else {
                usleep(1 * $clockDelay);

                try {
                    // This call should be block by the child #1
                    $store->waitAndSave($key);
                    $this->assertTrue($store->exists($key));
                    $store->delete($key);
                    exit(0);
                } catch (\Exception $e) {
                    exit(2);
                }
            }
        } else {
            try {
                $store->save($key);
                // Wait 3 ClockDelay to let other child to be initialized
                usleep(3 * $clockDelay);
                $store->delete($key);
                exit(0);
            } catch (\Exception $e) {
                exit(1);
            }
        }
    }
}
