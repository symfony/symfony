<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessManager;

/**
 * @author John Nickell <email@johnnickell.com>
 */
class ProcessManagerTest extends TestCase
{
    private static $phpBin;

    public static function setUpBeforeClass()
    {
        $phpBin = new PhpExecutableFinder();
        self::$phpBin = getenv('SYMFONY_PROCESS_PHP_TEST_BINARY') ?: ('phpdbg' === PHP_SAPI ? 'php' : $phpBin->find());
    }

    public function testRunningProcessesInSequence()
    {
        $output = [];
        $callback = function ($type, $data) use (&$output) {
            $output[] = $data;
        };

        $processManager = new ProcessManager(1);
        $processManager->add($this->getProcessForCode('usleep(10000); echo "foo";'), $callback);
        $processManager->add($this->getProcessForCode('echo "bar";'), $callback);
        $processManager->run();

        $this->assertSame(['foo', 'bar'], $output);
    }

    public function testRunningProcessesInParallel()
    {
        $output = [];
        $callback = function ($type, $data) use (&$output) {
            $output[] = $data;
        };

        $processManager = new ProcessManager(2);
        $processManager->add($this->getProcessForCode('usleep(10000); echo "foo";'), $callback);
        $processManager->add($this->getProcessForCode('echo "bar";'), $callback);
        $processManager->run();

        $this->assertSame(['bar', 'foo'], $output);
    }

    public function testThatFailedProcessesCanBeIgnored()
    {
        $processManager = new ProcessManager();
        $process = $this->getProcessForCode('throw new Exception();');
        $processManager->add($process);
        $processManager->run(ProcessManager::IGNORE_ON_ERROR);

        $this->assertFalse($process->isSuccessful());
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function testThatFailedProcessesThrowException()
    {
        $processManager = new ProcessManager();
        $processManager->add($this->getProcessForCode('throw new Exception();'));
        $processManager->run();
    }

    /**
     * @param string      $commandline
     * @param null|string $cwd
     * @param null|array  $env
     * @param null|string $input
     * @param int         $timeout
     * @param array       $options
     *
     * @return Process
     */
    private function getProcess($commandline, $cwd = null, array $env = null, $input = null, $timeout = 60)
    {
        $process = new Process($commandline, $cwd, $env, $input, $timeout);
        $process->inheritEnvironmentVariables();

        return $process;
    }

    /**
     * @return Process
     */
    private function getProcessForCode($code, $cwd = null, array $env = null, $input = null, $timeout = 60)
    {
        return $this->getProcess(array(self::$phpBin, '-r', $code), $cwd, $env, $input, $timeout);
    }
}
