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

    protected function skipIfPHPSigchild()
    {
        ob_start();
        phpinfo(INFO_GENERAL);

        if (false !== strpos(ob_get_clean(), '--enable-sigchild')) {
            $this->markTestSkipped('Your PHP has been compiled with --enable-sigchild, this test can not be executed');
        }
    }

    protected function getProcess($commandline, $cwd = null, array $env = null, $stdin = null, $timeout = 60, array $options = array())
    {
        return new Process($commandline, $cwd, $env, $stdin, $timeout, $options);
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
}
