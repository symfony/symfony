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
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
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
