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
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
trait BlockingStoreTestTrait
{
    /**
     * @see AbstractStoreTestCase::getStore()
     *
     * @return PersistingStoreInterface
     */
    abstract protected function getStore();

    /**
     * Tests blocking locks thanks to pcntl.
     *
     * This test is time sensible: the $clockDelay could be adjust.
     *
     * It also fails when run with the global ./phpunit test suite.
     *
     * @group transient
     *
     * @requires extension pcntl
     * @requires extension posix
     * @requires function pcntl_sigwaitinfo
     */
    public function testBlockingLocks()
    {
        // Amount of microseconds we should wait without slowing things down too much
        $clockDelay = 50000;

        $key = new Key(uniqid(__METHOD__, true));
        $parentPID = posix_getpid();
        var_dump(__METHOD__);
        var_dump($parentPID);

        // Block SIGHUP signal
        pcntl_sigprocmask(\SIG_BLOCK, [\SIGHUP]);

        if ($childPID = pcntl_fork()) {
            var_dump($childPID);
            // Wait the start of the child
            pcntl_sigwaitinfo([\SIGHUP], $info);
            print_r($info);

            $store = $this->getStore();
            var_dump(get_class($store));
            try {
                // This call should fail given the lock should already be acquired by the child
                $store->save($key);
                $this->fail('The store saves a locked key.');
            } catch (LockConflictedException $e) {
                var_dump($e->getMessage());
            } finally {
                // send the ready signal to the child
                posix_kill($childPID, \SIGHUP);
            }

            // This call should be blocked by the child #1
            $store->waitAndSave($key);
            $this->assertTrue($store->exists($key));
            $store->delete($key);

            // Now, assert the child process worked well
            pcntl_waitpid($childPID, $status1);
            var_dump($status1);
            var_dump('pcntl_wifexited');
            var_dump(pcntl_wifexited($status1));
            var_dump('pcntl_wifstopped');
            var_dump(pcntl_wifstopped($status1));
            var_dump('pcntl_wifsignaled');
            var_dump(pcntl_wifsignaled($status1));
            var_dump('pcntl_wexitstatus');
            var_dump(pcntl_wexitstatus($status1));
            var_dump('pcntl_wtermsig');
            var_dump(pcntl_wtermsig($status1));
            var_dump('pcntl_wstopsig');
            var_dump(pcntl_wstopsig($status1));
            $this->assertSame(0, pcntl_wexitstatus($status1), 'The child process couldn\'t lock the resource');
        } else {
            // Block SIGHUP signal
            pcntl_sigprocmask(\SIG_BLOCK, [\SIGHUP]);

            try {
                var_dump('child process');
                $store = $this->getStore();
                $store->save($key);
                // send the ready signal to the parent
                posix_kill($parentPID, \SIGHUP);

                // Wait for the parent to be ready
                pcntl_sigwaitinfo([\SIGHUP], $info);

                // Wait ClockDelay to let parent assert to finish
                usleep($clockDelay);
                $store->delete($key);
                var_dump('terminate gracefully');
                exit(3);
            } catch (\Throwable $e) {
                posix_kill($parentPID, \SIGHUP);
                var_dump('kill');
                exit(5);
            }
        }
    }
}
