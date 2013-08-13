<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Tests\Manager;

use Symfony\Component\Process\Tests\ProcessableTestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessableInterface;
use Symfony\Component\Process\Manager\ManagedProcess;

class ManagedProcessTest extends ProcessableTestCase
{
    public function testExecutionsSetters()
    {
        $process = new ManagedProcess($this->createProcessMock(), 3);
        $this->assertEquals(3, $process->getExecutions());

        $process = new ManagedProcess($this->createProcessMock());
        $this->assertEquals(1, $process->getExecutions());
        $process->setExecutions(42);
        $this->assertEquals(42, $process->getExecutions());
    }

    /**
     * @dataProvider provideRunMethods
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     * @expectedExceptionMessage Can not modify executions once the process started. Reset the process before modifying this.
     */
    public function testExecutionsCanNotBeModifiedOnceTheProcessHasRun($runMethod)
    {
        $process = new ManagedProcess($this->createProcessMock(), 3);
        call_user_func(array($process, $runMethod));
        $process->setExecutions(3);
    }

    /**
     * @dataProvider provideRunMethods
     */
    public function testExecutionsCanBeModifiedIfTheProcessIsReset($runMethod)
    {
        $process = new ManagedProcess($this->createProcessMock(), 3);
        call_user_func(array($process, $runMethod));
        $process->reset();
        $process->setExecutions(3);
    }

    public function provideRunMethods()
    {
        return array(array('start'), array('run'));
    }

    /**
     * @dataProvider provideInvalidExecutionsValues
     * @expectedException Symfony\Component\Process\Exception\InvalidArgumentException
     * @expectedExceptionMessage Executions must be a positive value.
     */
    public function testInvalidExecutionsValuesOnConstruct($executions)
    {
        new ManagedProcess($this->createProcessMock(), $executions);
    }

    /**
     * @dataProvider provideInvalidExecutionsValues
     * @expectedException Symfony\Component\Process\Exception\InvalidArgumentException
     * @expectedExceptionMessage Executions must be a positive value.
     */
    public function testInvalidExecutionsValuesSetter($executions)
    {
        $process = new ManagedProcess($this->createProcessMock());
        $process->setExecutions($executions);
    }

    protected function getOutput(ProcessableInterface $process)
    {
        return $process->getManagedProcess()->getOutput();
    }

    public function provideInvalidExecutionsValues()
    {
        return array(array(0), array(-4));
    }

    /**
     * @dataProvider provideRunMethods
     */
    public function testRetryIncrementExecutionsAfterARun($runMethod)
    {
        $process = new ManagedProcess($this->createProcessMock(), 4);
        call_user_func(array($process, $runMethod));
        $process->retry();
        $this->assertEquals(4, $process->getExecutions());
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     * @expectedExceptionMessage Process must have run at least once to retry.
     */
    public function testRetryFailsIfProcessDidNotRunYet()
    {
        $process = new ManagedProcess($this->createProcessMock());
        $process->retry();
    }

    public function testResetDoReset()
    {
        $process = new ManagedProcess($this->createProcessMock(), 4);
        $process->run();
        $process->addFailure(new \Exception('failure'));
        $process->reset();

        $this->assertEquals(4, $process->getExecutions());
        $this->assertEquals(array(), $process->getFailures());
        $this->assertFalse($process->hasRun());
    }

    /**
     * @dataProvider provideRunMethods
     */
    public function testHasRun($runMethod)
    {
        $process = new ManagedProcess($this->createProcessMock());
        $this->assertFalse($process->hasRun());
        call_user_func(array($process, $runMethod));
        $this->assertTrue($process->hasRun());
    }

    /**
     * @dataProvider provideRunMethods
     */
    public function testCanRun($runMethod)
    {
        $process = new ManagedProcess($this->createProcessMock(), 2);
        $this->assertTrue($process->canRun());
        call_user_func(array($process, $runMethod));
        $this->assertTrue($process->canRun());
        call_user_func(array($process, $runMethod));
        $this->assertFalse($process->canRun());
        $process->retry();
        $this->assertTrue($process->canRun());
        call_user_func(array($process, $runMethod));
        $this->assertFalse($process->canRun());
    }

    public function testFailuresGetterAndSetters()
    {
        $process = new ManagedProcess($this->createProcessMock());
        $this->assertEquals(array(), $process->getFailures());
        $exception1 = new \Exception('failure #1');
        $process->addFailure($exception1);
        $this->assertSame(array($exception1), $process->getFailures());
        $exception2 = new \Exception('failure #2');
        $process->addFailure($exception2);
        $this->assertSame(array($exception1, $exception2), $process->getFailures());
    }

    public function testManagedProcessGetters()
    {
        $managed1 = $this->createProcessMock();
        $process = new ManagedProcess($managed1);
        $this->assertSame($managed1, $process->getManagedProcess());
    }

    /**
     * @dataProvider provideRunMethods
     */
    public function testRunDecrementsExecutions($runMethod)
    {
        $process = new ManagedProcess($this->createProcessMock(), 3);
        call_user_func(array($process, $runMethod));
        $this->assertEquals(2, $process->getExecutions());
        call_user_func(array($process, $runMethod));
        $this->assertEquals(1, $process->getExecutions());
    }

    /**
     * @dataProvider provideRunMethods
     */
    public function testRunMethodsFailsIfNotRemainingExecutions($runMethod)
    {
        $process = new ManagedProcess($this->createProcessMock());
        call_user_func(array($process, $runMethod));
        $this->setExpectedException('Symfony\Component\Process\Exception\RuntimeException', 'No remaining executions for the managed process.');
        call_user_func(array($process, $runMethod));
    }

    public function testRunReturnsManagedProcessExitCode()
    {
        $callback = function () {};
        $managed = $this->createProcessMock();
        $managed->expects($this->once())
            ->method('run')
            ->with($callback)
            ->will($this->returnValue(42));
        $process = new ManagedProcess($managed);
        $this->assertEquals(42, $process->run($callback));
    }

    public function testStartReturnsTheObject()
    {
        $callback = function () {};
        $managed = $this->createProcessMock();
        $managed->expects($this->once())
            ->method('start')
            ->with($callback)
            ->will($this->returnValue(42));
        $process = new ManagedProcess($managed);
        $this->assertSame($process, $process->start($callback));
    }

    public function testRestartReturnsACloneContainingACloneOfTheManagedProcess()
    {
        $callback = function () {};
        $managed = $this->createProcessMock();
        $cloned = $this->createProcessMock();
        $managed->expects($this->once())
            ->method('restart')
            ->with($callback)
            ->will($this->returnValue($cloned));
        $process = new ManagedProcess($managed);
        $retval = $process->restart($callback);
        $this->assertNotSame($retval, $process);
        $this->assertEquals($retval, $process);
        $this->assertEquals($cloned, $retval->getManagedProcess());
    }

    public function testWaitReturnsManagedProcessExitCode()
    {
        $callback = function () {};
        $managed = $this->createProcessMock();
        $managed->expects($this->once())
            ->method('wait')
            ->with($callback)
            ->will($this->returnValue(42));
        $process = new ManagedProcess($managed);
        $this->assertEquals(42, $process->wait($callback));
    }

    public function testSignalReturnsManagedProcessItSelf()
    {
        $signal = 13;
        $managed = $this->createProcessMock();
        $managed->expects($this->once())
            ->method('signal')
            ->with($signal);
        $process = new ManagedProcess($managed);
        $this->assertSame($process, $process->signal($signal));
    }

    public function testIsSuccessfulReturnsTheManagedProcessResult()
    {
        $managed = $this->createProcessMock();
        $managed->expects($this->once())
            ->method('isSuccessful')
            ->will($this->returnValue('return value'));
        $process = new ManagedProcess($managed);
        $this->assertSame('return value', $process->isSuccessful());
    }

    public function testIsStartedReturnsTheManagedProcessResult()
    {
        $managed = $this->createProcessMock();
        $managed->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue('return value'));
        $process = new ManagedProcess($managed);
        $this->assertSame('return value', $process->isStarted());
    }

    public function testIsRunningReturnsTheManagedProcessResult()
    {
        $managed = $this->createProcessMock();
        $managed->expects($this->once())
            ->method('isRunning')
            ->will($this->returnValue('return value'));
        $process = new ManagedProcess($managed);
        $this->assertSame('return value', $process->isRunning());
    }

    public function testIsTerminatedReturnsTheManagedProcessResult()
    {
        $managed = $this->createProcessMock();
        $managed->expects($this->once())
            ->method('isTerminated')
            ->will($this->returnValue('return value'));
        $process = new ManagedProcess($managed);
        $this->assertSame('return value', $process->isTerminated());
    }

    public function testGetStatusReturnsTheManagedProcessResult()
    {
        $managed = $this->createProcessMock();
        $managed->expects($this->once())
            ->method('getStatus')
            ->will($this->returnValue('return value'));
        $process = new ManagedProcess($managed);
        $this->assertSame('return value', $process->getStatus());
    }

    public function testStopReturnsTheExitCode()
    {
        $timeout = 42;
        $signal = 18;
        $managed = $this->createProcessMock();
        $managed->expects($this->once())
            ->method('stop')
            ->with($timeout, $signal)
            ->will($this->returnValue(127));
        $process = new ManagedProcess($managed);
        $this->assertSame(127, $process->stop($timeout, $signal));
    }

    public function testCheckTimeoutChecksThetimeout()
    {
        $managed = $this->createProcessMock();
        $managed->expects($this->once())
            ->method('checkTimeout');
        $process = new ManagedProcess($managed);
        $process->checkTimeout();
    }

    public function createProcess($commandline, $cwd = null, array $env = null, $stdin = null, $timeout = 60, array $options = array())
    {
        return new ManagedProcess(new Process($commandline, $cwd, $env, $stdin, $timeout, $options));
    }

    private function createProcessMock()
    {
        return $this->getMock('Symfony\Component\Process\ProcessInterface');
    }
}
