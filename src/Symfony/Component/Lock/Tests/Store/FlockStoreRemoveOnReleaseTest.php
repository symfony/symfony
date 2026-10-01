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
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Test\AbstractStoreTestCase;

/**
 * Runs the shared store test suite against a FlockStore that removes the lock
 * file on release, to make sure the option does not break any store contract.
 */
class FlockStoreRemoveOnReleaseTest extends AbstractStoreTestCase
{
    use BlockingStoreTestTrait;
    use SharedLockStoreTestTrait;
    use UnserializableTestTrait;

    protected function getStore(): PersistingStoreInterface
    {
        return new FlockStore(null, true);
    }

    public function testLockFileIsRemovedOnRelease()
    {
        $key = new Key(__METHOD__);

        $file = FlockStoreTest::getLockFile($key);
        @unlink($file);

        // The lock file is kept when the lock is released by default
        $store = new FlockStore();
        $store->save($key);
        $this->assertFileExists($file);
        $store->delete($key);
        $this->assertFileExists($file);

        // It is removed when the option is enabled
        $store = new FlockStore(null, true);
        $store->save($key);
        $this->assertFileExists($file);
        $store->delete($key);
        $this->assertFileDoesNotExist($file);
    }

    public function testSharedLockFileIsKeptOnRelease()
    {
        $store = new FlockStore(null, true);

        $key = new Key(__METHOD__);

        $file = FlockStoreTest::getLockFile($key);
        @unlink($file);

        $store->saveRead($key);
        $this->assertFileExists($file);

        $store->delete($key);
        $this->assertFileExists($file, 'The file of a shared lock is kept so that other readers do not keep holding a removed file.');

        @unlink($file);
    }

    /**
     * Ensures that removing the lock file on release does not let two processes hold the lock.
     *
     * The parent holds the lock while the child waits for it. When the parent releases the lock,
     * it removes the file the child is waiting on. The child must detect that the file it locked
     * was replaced and retry on the new one, instead of holding the removed file while the parent
     * creates a new one.
     */
    #[RequiresPhpExtension('pcntl')]
    public function testRemoveLockFileOnReleaseDoesNotBreakWaitingProcesses()
    {
        // Use a unique key so that the lock file cannot collide with a leftover from a
        // previous crashed run or with another concurrent phpunit run on the same machine
        $key = new Key(uniqid(__METHOD__, true));

        [$parentSocket, $childSocket] = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);

        if ($childPid = pcntl_fork()) {
            fclose($childSocket);
            $store = new FlockStore(null, true);

            try {
                $store->save($key);
                // Tell the child it can start waiting for the lock
                fwrite($parentSocket, "start\n");
                // Wait for the child to be about to wait on the lock
                $this->assertSame("waiting\n", fgets($parentSocket));
                // Give the child some time to actually block on the lock
                usleep(50000);

                // Release the lock and remove the file the child is waiting on
                $store->delete($key);

                // Wait for the child to acquire the lock
                $this->assertSame("acquired\n", fgets($parentSocket));

                try {
                    $store->save($key);
                    $this->fail('The store saved a key locked by another process.');
                } catch (LockConflictedException $e) {
                }

                // Tell the child it can release the lock
                fwrite($parentSocket, "release\n");
                $this->assertSame("done\n", fgets($parentSocket));
            } finally {
                fclose($parentSocket);
            }

            pcntl_waitpid($childPid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status), 'The child process could not acquire the lock');
        } else {
            fclose($parentSocket);
            $store = new FlockStore(null, true);

            try {
                // Wait for the parent to hold the lock
                if ("start\n" !== fgets($childSocket)) {
                    exit(1);
                }
                // Tell the parent we are about to wait on the lock
                fwrite($childSocket, "waiting\n");

                $store->waitAndSave($key);

                // Tell the parent we acquired the lock
                fwrite($childSocket, "acquired\n");
                // Wait for the parent to be done checking we hold the lock
                if ("release\n" !== fgets($childSocket)) {
                    exit(1);
                }

                $store->delete($key);
                fwrite($childSocket, "done\n");
                exit(0);
            } catch (\Throwable $e) {
                exit(1);
            }
        }
    }
}
