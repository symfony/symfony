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

class SigchildEnabledProcessTest extends AbstractProcessTest
{
    /**
     * @expectedException \Symfony\Component\Process\Exception\RuntimeException
     */
    public function testProcessIsSignaledIfStopped()
    {
        parent::testProcessIsSignaledIfStopped();
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\RuntimeException
     */
    public function testProcessWithTermSignal()
    {
        parent::testProcessWithTermSignal();
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\RuntimeException
     */
    public function testProcessIsNotSignaled()
    {
        parent::testProcessIsNotSignaled();
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\RuntimeException
     */
    public function testProcessWithoutTermSignal()
    {
        parent::testProcessWithoutTermSignal();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testGetPid()
    {
        parent::testGetPid();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testGetPidIsNullBeforeStart()
    {
        parent::testGetPidIsNullBeforeStart();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testGetPidIsNullAfterRun()
    {
        parent::testGetPidIsNullAfterRun();
    }

    public function testExitCodeText()
    {
        $process = $this->getProcess('qdfsmfkqsdfmqmsd');
        $process->run();

        $this->assertInternalType('string', $process->getExitCodeText());
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testSignal()
    {
        parent::testSignal();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testProcessWithoutTermSignalIsNotSignaled()
    {
        parent::testProcessWithoutTermSignalIsNotSignaled();
    }

    public function testProcessThrowsExceptionWhenExternallySignaled()
    {
        $this->markTestSkipped('Retrieving Pid is not supported in sigchild environment');
    }

    public function testExitCodeIsAvailableAfterSignal()
    {
        $this->markTestSkipped('Signal is not supported in sigchild environment');
    }

    /**
     * {@inheritdoc}
     */
    protected function getProcess($commandline, $cwd = null, array $env = null, $stdin = null, $timeout = 60, array $options = array())
    {
        $process = new ProcessInSigchildEnvironment($commandline, $cwd, $env, $stdin, $timeout, $options);
        $process->setEnhanceSigchildCompatibility(true);

        return $process;
    }
}
