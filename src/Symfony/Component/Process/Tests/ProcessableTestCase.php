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

use Symfony\Component\Process\ProcessableInterface;

abstract class ProcessableTestCase extends \PHPUnit_Framework_TestCase
{
    public function testStopWithTimeoutIsActuallyWorking()
    {
        $this->verifyPosixIsEnabled();

        // exec is mandatory here since we send a signal to the process
        // see https://github.com/symfony/symfony/issues/5030 about prepending
        // command with exec
        $p = $this->createProcess('exec php '.__DIR__.'/NonStopableProcess.php 3');
        $p->start();
        usleep(100000);
        $start = microtime(true);
        $p->stop(1.1, SIGKILL);
        while ($p->isRunning()) {
            usleep(1000);
        }
        $duration = microtime(true) - $start;

        $this->assertLessThan(1.8, $duration);
    }

    public function testCallbackIsExecutedForOutput()
    {
        $p = $this->createProcess(sprintf('php -r %s', escapeshellarg('echo \'foo\';')));

        $called = false;
        $p->run(function ($type, $buffer) use (&$called) {
            $called = $buffer === 'foo';
        });

        $this->assertTrue($called, 'The callback should be executed with the output');
    }

    public function testStartIsNonBlocking()
    {
        $process = $this->createProcess('php -r "sleep(4);"');
        $start = microtime(true);
        $process->start();
        $end = microtime(true);
        $this->assertLessThan(1 , $end-$start);
    }

    public function testStatus()
    {
        $process = $this->createProcess('php -r "usleep(500000);"');
        $this->assertFalse($process->isRunning());
        $this->assertFalse($process->isStarted());
        $this->assertFalse($process->isTerminated());
        $this->assertSame(ProcessableInterface::STATUS_READY, $process->getStatus());
        $process->start();
        $this->assertTrue($process->isRunning());
        $this->assertTrue($process->isStarted());
        $this->assertFalse($process->isTerminated());
        $this->assertSame(ProcessableInterface::STATUS_STARTED, $process->getStatus());
        $process->wait();
        $this->assertFalse($process->isRunning());
        $this->assertTrue($process->isStarted());
        $this->assertTrue($process->isTerminated());
        $this->assertSame(ProcessableInterface::STATUS_TERMINATED, $process->getStatus());
    }

    public function testStop()
    {
        $process = $this->createProcess('php -r "sleep(4);"');
        $process->start();
        $this->assertTrue($process->isRunning());
        $process->stop();
        $this->assertFalse($process->isRunning());
    }

    public function testIsSuccessful()
    {
        $process = $this->createProcess('php -m');
        $process->run();
        $this->assertTrue($process->isSuccessful());
    }

    public function testIsNotSuccessful()
    {
        $process = $this->createProcess('php -r "sleep(4);"');
        $process->start();
        $this->assertTrue($process->isRunning());
        $process->stop();
        $this->assertFalse($process->isSuccessful());
    }

    public function testIsSuccessfulOnlyAfterTerminated()
    {
        $process = $this->createProcess('php -r "sleep(1);"');
        $process->start();
        while ($process->isRunning()) {
            $this->assertFalse($process->isSuccessful());
            usleep(300000);
        }

        $this->assertTrue($process->isSuccessful());
        $process = $this->createProcess('sleep 1');
    }

    public function testRestart()
    {
        $process1 = $this->createProcess('php -r "echo getmypid();"');
        $process1->run();
        $process2 = $process1->restart();
        $process2->wait();

        // Ensure that both processed finished and the output is numeric
        $this->assertFalse($process1->isRunning());
        $this->assertFalse($process2->isRunning());
        $this->assertTrue(is_numeric($this->getOutput($process1)));
        $this->assertTrue(is_numeric($this->getOutput($process2)));

        // Ensure that restart returned a new process by check that the output is different
        $this->assertNotEquals($this->getOutput($process1), $this->getOutput($process2));
    }

    public function testSignal()
    {
        $this->verifyPosixIsEnabled();

        $process = $this->createProcess('exec php -f ' . __DIR__ . '/SignalListener.php');
        $process->start();
        usleep(500000);
        $process->signal(SIGUSR1);

        while ($process->isRunning() && false === strpos($this->getOutput($process), 'Caught SIGUSR1')) {
            usleep(10000);
        }

        $this->assertEquals('Caught SIGUSR1', $this->getOutput($process));
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\LogicException
     */
    public function testSignalProcessNotRunning()
    {
        $this->verifyPosixIsEnabled();
        $process = $this->createProcess('php -m');
        $process->signal(SIGHUP);
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testSignalWithWrongIntSignal()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('POSIX signals do not work on windows');
        }

        $process = $this->createProcess('php -r "sleep(3);"');
        $process->start();
        $process->signal(-4);
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testSignalWithWrongNonIntSignal()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('POSIX signals do not work on windows');
        }

        $process = $this->createProcess('php -r "sleep(3);"');
        $process->start();
        $process->signal('Céphalopodes');
    }

    protected function verifyPosixIsEnabled()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('POSIX signals do not work on windows');
        }
        if (!defined('SIGUSR1')) {
            $this->markTestSkipped('The pcntl extension is not enabled');
        }
    }

    /**
     * @return string
     */
    abstract protected function getOutput(ProcessableInterface $process);

    /**
     * @return ProcessableInterface
     */
    abstract protected function createProcess($commandline);
}
