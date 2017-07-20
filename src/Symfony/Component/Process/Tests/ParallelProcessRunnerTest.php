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
use Symfony\Component\Process\ParallelProcessRunner;

/**
 * @author John Nickell <email@johnnickell.com>
 */
class ParallelProcessRunnerTest extends TestCase
{
    private static $phpBin;

    public static function setUpBeforeClass()
    {
        $phpBin = new PhpExecutableFinder();
        self::$phpBin = getenv('SYMFONY_PROCESS_PHP_TEST_BINARY') ?: ('phpdbg' === PHP_SAPI ? 'php' : $phpBin->find());
    }

    public function testRunningProcessesInSequence()
    {
        $output = array();
        $callback = function ($type, $data) use (&$output) {
            $output[] = $data;
        };

        $processRunner = new ParallelProcessRunner(1);
        $processRunner->add($this->getProcessForCode('usleep(10000); echo "foo";'), $callback);
        $processRunner->add($this->getProcessForCode('usleep(10000); echo "bar";'), $callback);
        $processRunner->run();

        $this->assertSame(array('foo', 'bar'), $output);
    }

    public function testRunningProcessesInParallel()
    {
        $output = array();
        $callback = function ($type, $data) use (&$output) {
            $output[] = $data;
        };

        $processRunner = new ParallelProcessRunner(2);
        $processRunner->add($this->getProcessForCode('echo "foo";'), $callback);
        $processRunner->add($this->getProcessForCode('echo "bar";'), $callback);
        $processRunner->run();

        // cannot guarantee order
        $this->assertContains('bar', $output);
        $this->assertContains('foo', $output);
    }

    public function testThatFailedProcessesCanBeIgnored()
    {
        $processRunner = new ParallelProcessRunner();
        $process = $this->getProcessForCode('throw new Exception();');
        $processRunner->add($process);
        $processRunner->run(ParallelProcessRunner::IGNORE_ON_ERROR);

        $this->assertFalse($process->isSuccessful());
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function testThatFailedProcessesThrowException()
    {
        $processRunner = new ParallelProcessRunner();
        $processRunner->add($this->getProcessForCode('throw new Exception();'));
        $processRunner->run();
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
