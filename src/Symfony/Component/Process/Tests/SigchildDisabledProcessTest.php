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

class SigchildDisabledProcessTest extends AbstractProcessTest
{
    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testGetExitCode()
    {
        parent::testGetExitCode();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testExitCodeCommandFailed()
    {
        parent::testExitCodeCommandFailed();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testProcessIsSignaledIfStopped()
    {
        parent::testProcessIsSignaledIfStopped();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testProcessWithTermSignal()
    {
        parent::testProcessWithTermSignal();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testProcessIsNotSignaled()
    {
        parent::testProcessIsNotSignaled();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testProcessWithoutTermSignal()
    {
        parent::testProcessWithoutTermSignal();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testExitCodeText()
    {
        $process = $this->getProcess('qdfsmfkqsdfmqmsd');
        $process->run();

        $process->getExitCodeText();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testIsSuccessful()
    {
        parent::testIsSuccessful();
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\RuntimeException
     */
    public function testIsNotSuccessful()
    {
        parent::testIsNotSuccessful();
    }

    /**
     * {@inheritdoc}
     */
    protected function getProcess($commandline, $cwd = null, array $env = null, $stdin = null, $timeout = 60, array $options = array())
    {
        $process = new ProcessInSigchildEnvironment($commandline, $cwd, $env, $stdin, $timeout, $options);
        $process->setEnhanceSigchildCompatibility(false);

        return $process;
    }
}
