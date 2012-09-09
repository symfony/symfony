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

use Symfony\Component\Process\Process;

class SimpleProcessTest extends AbstractProcessTest
{
    private $enabledSigchild = false;

    public function setUp()
    {
        ob_start();
        phpinfo(INFO_GENERAL);

        $this->enabledSigchild = false !== strpos(ob_get_clean(), '--enable-sigchild');
    }

    public function testGetExitCode()
    {
        $this->skipIfPHPSigchild();
        parent::testGetExitCode();
    }

    public function testExitCodeCommandFailed()
    {
        $this->skipIfPHPSigchild();
        parent::testExitCodeCommandFailed();
    }

    public function testProcessIsSignaledIfStopped()
    {
        $this->skipIfPHPSigchild();
        parent::testProcessIsSignaledIfStopped();
    }

    public function testProcessWithTermSignal()
    {
        $this->skipIfPHPSigchild();
        parent::testProcessWithTermSignal();
    }

    public function testProcessIsNotSignaled()
    {
        $this->skipIfPHPSigchild();
        parent::testProcessIsNotSignaled();
    }

    public function testProcessWithoutTermSignal()
    {
        $this->skipIfPHPSigchild();
        parent::testProcessWithoutTermSignal();
    }

    public function testExitCodeText()
    {
        $this->skipIfPHPSigchild();
        parent::testExitCodeText();
    }

    public function testIsSuccessful()
    {
        $this->skipIfPHPSigchild();
        parent::testIsSuccessful();
    }

    public function testIsNotSuccessful()
    {
        $this->skipIfPHPSigchild();
        parent::testIsNotSuccessful();
    }

    public function testGetPid()
    {
        $this->skipIfPHPSigchild();
        parent::testGetPid();
    }

    public function testGetPidIsNullBeforeStart()
    {
        $this->skipIfPHPSigchild();
        parent::testGetPidIsNullBeforeStart();
    }

    public function testGetPidIsNullAfterRun()
    {
        $this->skipIfPHPSigchild();
        parent::testGetPidIsNullAfterRun();
    }

    public function testSignal()
    {
        $this->skipIfPHPSigchild();
        parent::testSignal();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\LogicException
     */
    public function testSignalProcessNotRunning()
    {
        $this->skipIfPHPSigchild();
        parent::testSignalProcessNotRunning();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testSignalWithWrongIntSignal()
    {
        $this->skipIfPHPSigchild();
        parent::testSignalWithWrongIntSignal();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testSignalWithWrongNonIntSignal()
    {
        $this->skipIfPHPSigchild();
        parent::testSignalWithWrongNonIntSignal();
    }

    /**
     * {@inheritdoc}
     */
    protected function getProcess($commandline, $cwd = null, array $env = null, $stdin = null, $timeout = 60, array $options = array())
    {
        return new Process($commandline, $cwd, $env, $stdin, $timeout, $options);
    }

    private function skipIfPHPSigchild()
    {
        if ($this->enabledSigchild) {
            $this->markTestSkipped('Your PHP has been compiled with --enable-sigchild, this test can not be executed');
        }
    }
}
