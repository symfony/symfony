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

use Symfony\Component\Process\Process,
    Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * @author Sebastian Marek <proofek@gmail.com>
 */
class ProcessFailedExceptionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * tests ProcessFailedException throws exception if the process was successful
     */
    public function testProcessFailedExceptionThrowsException()
    {
        $process = $this->getMock(
            'Symfony\Component\Process\Process',
            array('isSuccessful'),
            array('php')
        );
        $process->expects($this->once())
            ->method('isSuccessful')
            ->will($this->returnValue(true));

        $this->setExpectedException(
            '\InvalidArgumentException',
            'Expected a failed process, but the given process was successful.'
        );
        $exception = new ProcessFailedException($process);
    }

    /**
     * tests ProcessFailedException uses information from process output
     * to generate exception message
     */
    public function testProcessFailedExceptionPopulatesInformationFromProcessOutput()
    {
        $cmd = 'php';
        $output = "Command output";
        $errorOutput = "FATAL: Unexpected error";

        $process = $this->getMock(
            'Symfony\Component\Process\Process',
            array('isSuccessful', 'getOutput', 'getErrorOutput'),
            array($cmd)
        );
        $process->expects($this->once())
            ->method('isSuccessful')
            ->will($this->returnValue(false));
        $process->expects($this->once())
            ->method('getOutput')
            ->will($this->returnValue($output));
        $process->expects($this->once())
            ->method('getErrorOutput')
            ->will($this->returnValue($errorOutput));

        $exception = new ProcessFailedException($process);

        $this->assertEquals(
            "The command \"$cmd\" failed.\n\nOutput:\n================\n{$output}\n\nError Output:\n================\n{$errorOutput}",
            $exception->getMessage()
        );
    }
}
