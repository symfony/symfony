<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\LockRegistry;

class LockRegistryTest extends TestCase
{
    public function testFiles()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('LockRegistry is disabled on Windows');
        }
        $lockFiles = LockRegistry::setFiles([]);
        LockRegistry::setFiles($lockFiles);
        $expected = array_map('realpath', glob(__DIR__.'/../Adapter/*.php'));
        $this->assertSame($expected, $lockFiles);
    }

    public function testLockIsDisabledOnCli()
    {
        $logger = new class extends AbstractLogger {
            public array $messages = [];

            public function log($level, $message, array $context = []): void
            {
                $this->messages[] = $message;
            }
        };

        $pool = new FilesystemAdapter('lock-registry', 0, sys_get_temp_dir().'/symfony-cache-lock-registry');
        $pool->clear();
        $pool->setLogger($logger);

        $this->assertSame('bar', $pool->get('foo', static fn () => 'bar'));
        $this->assertSame([], $logger->messages);
    }

    /**
     * @dataProvider provideResettablePools
     */
    public function testResetClosesIdleLockHandles(\Closure $createPool)
    {
        $file = tempnam(sys_get_temp_dir(), 'sf_lock_');
        $previousFiles = LockRegistry::setFiles([$file]);
        $fallback = $this->createStub(AdapterInterface::class);
        $fallback->method('getItem')->willReturnCallback((new ArrayAdapter())->getItem(...));
        $fallback->method('save')->willReturn(true);
        $pool = $createPool($fallback);
        $pool->setCallbackWrapper(LockRegistry::compute(...));

        try {
            for ($i = 0; $i < 2; ++$i) {
                $this->assertSame('value', $pool->get('reset_'.$i, static fn () => 'value', \INF));
                $handles = array_filter(get_resources('stream'), static fn ($stream) => $file === stream_get_meta_data($stream)['uri']);
                $this->assertCount(1, $handles);

                $pool->reset();

                $this->assertFalse(\is_resource(reset($handles)));
            }
        } finally {
            $pool->clear();
            LockRegistry::setFiles($previousFiles);
            unlink($file);
        }
    }

    public static function provideResettablePools(): iterable
    {
        yield 'filesystem' => [static fn () => new FilesystemAdapter('lock-reset', 0, sys_get_temp_dir().'/symfony-cache-lock-registry')];
        yield 'filesystem tags' => [static fn () => new FilesystemTagAwareAdapter('lock-reset', 0, sys_get_temp_dir().'/symfony-cache-lock-registry')];
        yield 'chain' => [static fn () => new ChainAdapter([new ArrayAdapter()])];
        yield 'tags' => [static fn () => new TagAwareAdapter(new ArrayAdapter())];
        yield 'proxy' => [static fn ($fallback) => new ProxyAdapter($fallback)];
    }

    public function testResetKeepsAnActiveLockUntilTheComputationFinishes()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Independent file handles cannot be locked on Windows');
        }

        $file = tempnam(sys_get_temp_dir(), 'sf_lock_');
        $previousFiles = LockRegistry::setFiles([$file]);
        $pool = new ChainAdapter([new ArrayAdapter()]);
        $pool->setCallbackWrapper(LockRegistry::compute(...));
        $competingLock = fopen($file, 'r+');
        $handle = null;

        try {
            $this->assertSame('value', $pool->get('reset', function () use ($pool, $file, $competingLock, &$handle) {
                $handles = array_filter(get_resources('stream'), static fn ($stream) => $stream !== $competingLock && $file === stream_get_meta_data($stream)['uri']);
                $this->assertCount(1, $handles);
                $handle = reset($handles);
                $pool->reset();

                $this->assertTrue(\is_resource($handle));
                $this->assertFalse(flock($competingLock, \LOCK_EX | \LOCK_NB));

                return 'value';
            }));
            $this->assertFalse(\is_resource($handle));
            $this->assertTrue(flock($competingLock, \LOCK_EX | \LOCK_NB));
        } finally {
            fclose($competingLock);
            LockRegistry::setFiles($previousFiles);
            unlink($file);
        }
    }

    public function testResetKeepsTheHandleOfAWaitingComputationOpen()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Independent file handles cannot be locked on Windows');
        }

        $file = tempnam(sys_get_temp_dir(), 'sf_lock_');
        $previousFiles = LockRegistry::setFiles([$file]);
        $competingLock = fopen($file, 'r+');
        flock($competingLock, \LOCK_SH);
        $handle = null;
        $pool = new class extends ArrayAdapter {
            public \Closure $onGet;

            public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
            {
                return ($this->onGet)($callback);
            }
        };
        $pool->onGet = function (callable $callback) use ($pool, $file, $competingLock, &$handle) {
            flock($competingLock, \LOCK_UN);
            $handles = array_filter(get_resources('stream'), static fn ($stream) => $stream !== $competingLock && $file === stream_get_meta_data($stream)['uri']);
            $this->assertCount(1, $handles);
            $handle = reset($handles);

            LockRegistry::reset();
            $this->assertTrue(\is_resource($handle));

            return $callback($pool->getItem('waiting'));
        };
        $save = false;

        try {
            $this->assertSame('value', LockRegistry::compute(static fn () => 'value', $pool->getItem('waiting'), $save, $pool));
            $this->assertFalse(\is_resource($handle));
        } finally {
            fclose($competingLock);
            LockRegistry::setFiles($previousFiles);
            unlink($file);
        }
    }

    public function testResetClosesHandlesWhenTheCallbackThrows()
    {
        $file = tempnam(sys_get_temp_dir(), 'sf_lock_');
        $previousFiles = LockRegistry::setFiles([$file]);
        $pool = new ChainAdapter([new ArrayAdapter()]);
        $pool->setCallbackWrapper(LockRegistry::compute(...));
        $handle = null;

        try {
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage('Computation failed');
            $pool->get('exception', function () use ($pool, $file, &$handle) {
                $handles = array_filter(get_resources('stream'), static fn ($stream) => $file === stream_get_meta_data($stream)['uri']);
                $this->assertCount(1, $handles);
                $handle = reset($handles);
                $pool->reset();

                throw new \LogicException('Computation failed');
            });
        } finally {
            $this->assertFalse(\is_resource($handle));
            LockRegistry::setFiles($previousFiles);
            unlink($file);
        }
    }

    public function testWaitLoopEndsBeforeMaxExecutionTime()
    {
        $elapsed = $this->computeWhileSlotIsLocked(requestAge: 0.0);

        $this->assertGreaterThan(0.5, $elapsed, 'The wait loop should use the time budget that is left');
        $this->assertLessThan(2.0, $elapsed, 'The wait loop should end before max_execution_time');
    }

    public function testWaitLoopEndsAtOnceWhenMaxExecutionTimeIsNear()
    {
        $elapsed = $this->computeWhileSlotIsLocked(requestAge: 1.5);

        $this->assertLessThan(0.5, $elapsed, 'The wait loop should end before max_execution_time');
    }

    /**
     * Calls LockRegistry::compute() for an item whose slot is read-locked by another process, so
     * that the wait loop succeeds and the pool is asked for the item. Computing that nested item
     * evicts its own slot, while the outer call still holds the handle of the first one.
     */
    public function testEvictingASlotKeepsTheHandlesOfTheOtherSlotsOpen()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('LockRegistry is disabled on Windows');
        }
        if (!\function_exists('proc_open')) {
            $this->markTestSkipped('proc_open() is required');
        }

        $logger = new class extends AbstractLogger {
            public array $messages = [];

            public function log($level, $message, array $context = []): void
            {
                $this->messages[] = $message;
            }
        };

        $files = [tempnam(sys_get_temp_dir(), 'sf_lock'), tempnam(sys_get_temp_dir(), 'sf_lock')];
        $sharedFile = $files[abs(crc32('foo')) % 2];
        $wedgedFile = $files[abs(crc32('bar')) % 2];

        $script = 'flock($h = fopen($argv[1], "r+"), (int) $argv[2]) || exit(1); echo "locked\n"; fgets(STDIN);';
        $descriptors = [['pipe', 'r'], ['pipe', 'w'], ['redirect', 1]];
        $processes = [];

        foreach (['shared' => [$sharedFile, \LOCK_SH], 'wedged' => [$wedgedFile, \LOCK_EX]] as $name => [$file, $operation]) {
            $process = proc_open([\PHP_BINARY, '-n', '-r', $script, '--', $file, $operation], $descriptors, $pipes);
            $this->assertSame("locked\n", fgets($pipes[1]));
            $processes[$name] = [$process, $pipes];
        }

        $release = static function (string $name) use (&$processes) {
            if (null === $process = $processes[$name] ?? null) {
                return;
            }
            unset($processes[$name]);
            fclose($process[1][0]);
            fclose($process[1][1]);
            proc_terminate($process[0]);
            proc_close($process[0]);
        };

        $pool = new class extends ArrayAdapter {
            public \Closure $onGet;

            public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
            {
                return ($this->onGet)($callback);
            }
        };
        $pool->onGet = static function (callable $callback) use ($pool, $release) {
            $release('shared');
            $save = false;

            return LockRegistry::compute($callback, $pool->getItem('bar'), $save, new ArrayAdapter());
        };

        $item = $pool->getItem('foo');
        $previousFiles = LockRegistry::setFiles($files);
        $previousLimit = (int) \ini_get('max_execution_time');
        $previousRequestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $save = true;

        try {
            $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 1.5;
            set_time_limit(2);

            $this->assertSame('bar', LockRegistry::compute(static fn () => 'bar', $item, $save, $pool, null, $logger));
            $this->assertSame([
                'Item "{key}" is locked, waiting for it to be released',
                'Item "{key}" not found while lock was released, now retrying',
                'Lock acquired, now computing item "{key}"',
            ], $logger->messages);
        } finally {
            set_time_limit($previousLimit);
            if (null === $previousRequestTime) {
                unset($_SERVER['REQUEST_TIME_FLOAT']);
            } else {
                $_SERVER['REQUEST_TIME_FLOAT'] = $previousRequestTime;
            }
            LockRegistry::setFiles($previousFiles);
            $release('shared');
            $release('wedged');
            array_map('unlink', $files);
        }
    }

    /**
     * Calls LockRegistry::compute() while another process holds the lock of the item,
     * with a max_execution_time of 2 seconds of which $requestAge seconds are already spent.
     *
     * @return float The number of seconds it took for the callback to run
     */
    private function computeWhileSlotIsLocked(float $requestAge): float
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('LockRegistry is disabled on Windows');
        }
        if (!\function_exists('proc_open')) {
            $this->markTestSkipped('proc_open() is required');
        }

        $logger = new class extends AbstractLogger {
            public array $messages = [];

            public function log($level, $message, array $context = []): void
            {
                $this->messages[] = $message;
            }
        };

        $files = [tempnam(sys_get_temp_dir(), 'sf_lock'), tempnam(sys_get_temp_dir(), 'sf_lock')];
        $pool = new ArrayAdapter();
        $item = $pool->getItem('foo');

        $script = 'flock($h = fopen($argv[1], "r+"), LOCK_EX) || exit(1); echo "locked\n"; fgets(STDIN);';
        $process = proc_open([\PHP_BINARY, '-n', '-r', $script, '--', $files[abs(crc32($item->getKey())) % 2]], [['pipe', 'r'], ['pipe', 'w'], ['redirect', 1]], $pipes);
        $this->assertSame("locked\n", fgets($pipes[1]));

        $previousFiles = LockRegistry::setFiles($files);
        $previousLimit = (int) \ini_get('max_execution_time');
        $previousRequestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $save = true;

        try {
            $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - $requestAge;
            set_time_limit(2);
            $start = microtime(true);

            $this->assertSame('bar', LockRegistry::compute(static fn () => 'bar', $item, $save, $pool, null, $logger));
            $this->assertSame([
                'Item "{key}" is locked, waiting for it to be released',
                'Lock on item "{key}" timed out, evicting slot',
                'Lock acquired, now computing item "{key}"',
            ], $logger->messages);

            return microtime(true) - $start;
        } finally {
            set_time_limit($previousLimit);
            if (null === $previousRequestTime) {
                unset($_SERVER['REQUEST_TIME_FLOAT']);
            } else {
                $_SERVER['REQUEST_TIME_FLOAT'] = $previousRequestTime;
            }
            LockRegistry::setFiles($previousFiles);
            fclose($pipes[0]);
            fclose($pipes[1]);
            proc_terminate($process);
            proc_close($process);
            array_map('unlink', $files);
        }
    }
}
