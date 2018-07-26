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
     * @requires extension posix
     * @requires function pcntl_sigwaitinfo
     */
    public function testBlockingLocks()
    {
        // Amount a microsecond used to order async actions
        $clockDelay = 50000;

        if (\PHP_VERSION_ID < 50600 || \defined('HHVM_VERSION_ID')) {
            $this->markTestSkipped('The PHP engine does not keep resource in child forks');

            return;
        }

        /** @var StoreInterface $store */
        $store = $this->getStore();
        $key = new Key(uniqid(__METHOD__, true));
        $parentPID = posix_getpid();

        // Block SIGHUP signal
        pcntl_sigprocmask(SIG_BLOCK, array(SIGHUP));

        if ($childPID = pcntl_fork()) {
            // Wait the start of the child
            pcntl_sigwaitinfo(array(SIGHUP), $info);

            try {
                // This call should failed given the lock should already by acquired by the child
                $store->save($key);
                $this->fail('The store saves a locked key.');
            } catch (LockConflictedException $e) {
            }

            // send the ready signal to the child
            posix_kill($childPID, SIGHUP);

            // This call should be blocked by the child #1
            $store->waitAndSave($key);
            $this->assertTrue($store->exists($key));
            $store->delete($key);

            // Now, assert the child process worked well
            pcntl_waitpid($childPID, $status1);
            $this->assertSame(0, pcntl_wexitstatus($status1), 'The child process couldn\'t lock the resource');
        } else {
            // Block SIGHUP signal
            pcntl_sigprocmask(SIG_BLOCK, array(SIGHUP));
            try {
                $store->save($key);
                // send the ready signal to the parent
                posix_kill($parentPID, SIGHUP);

                // Wait for the parent to be ready
                pcntl_sigwaitinfo(array(SIGHUP), $info);

                // Wait ClockDelay to let parent assert to finish
                usleep($clockDelay);
                $store->delete($key);
                exit(0);
            } catch (\Exception $e) {
                exit(1);
            }
        }
    }
}
