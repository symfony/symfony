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
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Test\AbstractStoreTestCase;

class FlockStoreRemoveOnReleaseTest extends AbstractStoreTestCase
{
    use BlockingStoreTestTrait;
    use SharedLockStoreTestTrait;
    use UnserializableTestTrait;

    private string $lockPath;
    private array $processes = [];

    protected function setUp(): void
    {
        $this->lockPath = sys_get_temp_dir().'/'.uniqid('sf-flock-', true);
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if (\is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }

        if (is_dir($this->lockPath)) {
            foreach (glob($this->lockPath.'/*') as $file) {
                @unlink($file);
            }
            @rmdir($this->lockPath);
        }
    }

    protected function getStore(): PersistingStoreInterface
    {
        return new FlockStore(null, true);
    }

    public function testLockFileIsRemovedOnRelease()
    {
        $key = new Key(__METHOD__);

        $store = new FlockStore($this->lockPath);
        $store->save($key);
        $store->delete($key);
        $this->assertCount(1, glob($this->lockPath.'/*.lock'));

        $store = new FlockStore($this->lockPath, true);
        $store->save($key);
        $this->assertCount(1, glob($this->lockPath.'/*.lock'));
        $store->delete($key);
        $this->assertSame([], glob($this->lockPath.'/*.lock'));
    }

    public function testSharedLockFileIsKeptOnRelease()
    {
        $store = new FlockStore($this->lockPath, true);
        $key = new Key(__METHOD__);

        $store->saveRead($key);
        $store->delete($key);
        $this->assertCount(1, glob($this->lockPath.'/*.lock'), 'The file of a shared lock is kept so that other readers do not keep holding a removed file.');
    }

    public function testWaitingProcessLocksTheNewFileWhenTheLockFileIsRemoved()
    {
        $store = new FlockStore($this->lockPath, true);
        $key = new Key(__METHOD__);
        $store->save($key);

        [$waiter] = $this->startWaiters($key, 1);
        $store->delete($key);

        $this->assertWaiterAcquiresTheLock($waiter, $store, $key);
    }

    public function testWaitingProcessLocksTheNewFileWhenTheLockFileIsRenamed()
    {
        $store = new FlockStore($this->lockPath, true);
        $key = new Key(__METHOD__);
        $store->save($key);
        [$file] = glob($this->lockPath.'/*.lock');

        [$waiter] = $this->startWaiters($key, 1);
        // NFS renames a removed file that is still open instead of unlinking it, so its link count stays at 1
        rename($file, $file.'.nfs123');
        $store->delete($key);

        $this->assertWaiterAcquiresTheLock($waiter, $store, $key);
    }

    public function testWaitingProcessesLockTheNewFilesInTurnWhenTheLockFileIsRemoved()
    {
        $store = new FlockStore($this->lockPath, true);
        $key = new Key(__METHOD__);

        // The processes race for the removed file, so several rounds make a failure more likely to show up
        for ($round = 0; $round < 3; ++$round) {
            $store->save($key);

            $waiters = $this->startWaiters($key, 2);
            $store->delete($key);

            while ($waiters) {
                $read = array_map(static fn ($waiter) => $waiter[2], $waiters);
                $write = $except = null;
                $this->assertSame(1, stream_select($read, $write, $except, 10), 'Exactly one waiting process acquires the lock.');

                $i = array_key_first($read);
                $this->assertWaiterAcquiresTheLock($waiters[$i], $store, $key);
                unset($waiters[$i]);
            }
        }
    }

    private function startWaiters(Key $key, int $count): array
    {
        $waiters = [];

        for ($i = 0; $i < $count; ++$i) {
            $this->processes[] = $process = proc_open([\PHP_BINARY, '-n', __DIR__.'/../Fixtures/flock_waiter.php', $this->lockPath, (string) $key], [['pipe', 'r'], ['socket']], $pipes);
            stream_set_timeout($pipes[1], 10);
            $waiters[] = [$process, $pipes[0], $pipes[1]];
        }

        foreach ($waiters as [, , $output]) {
            $this->assertSame("waiting\n", fgets($output));
        }

        // Gives the waiting processes time to block on the lock file
        usleep(100000);

        return $waiters;
    }

    private function assertWaiterAcquiresTheLock(array $waiter, FlockStore $store, Key $key): void
    {
        [$process, $input, $output] = $waiter;

        $this->assertSame("acquired\n", fgets($output));

        try {
            $store->save($key);
            $this->fail('The store saved a key locked by another process.');
        } catch (LockConflictedException) {
        }

        fwrite($input, "release\n");
        $this->assertSame("released\n", fgets($output));
        $this->assertSame(0, proc_close($process));
    }
}
