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
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * @author Sebastian Marek <proofek@gmail.com>
 */
class ProcessFailedExceptionTest extends TestCase
{
    /**
     * tests ProcessFailedException throws exception if the process was successful.
     */
    public function testProcessFailedExceptionThrowsException()
    {
        $process = $this->getMockBuilder('Symfony\Component\Process\Process')->setMethods(['isSuccessful'])->setConstructorArgs(['php'])->getMock();
        $process->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a failed process, but the given process was successful.');

        new ProcessFailedException($process);
    }

    /**
     * tests ProcessFailedException uses information from process output
     * to generate exception message.
     */
    public function testProcessFailedExceptionPopulatesInformationFromProcessOutput()
    {
        $cmd = 'php';
        $exitCode = 1;
        $exitText = 'General error';
        $output = 'Command output';
        $errorOutput = 'FATAL: Unexpected error';
        $workingDirectory = getcwd();

        $process = $this->getMockBuilder('Symfony\Component\Process\Process')->setMethods(['isSuccessful', 'getOutput', 'getErrorOutput', 'getExitCode', 'getExitCodeText', 'isOutputDisabled', 'getWorkingDirectory'])->setConstructorArgs([$cmd])->getMock();
        $process->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(false);

        $process->expects($this->once())
            ->method('getOutput')
            ->willReturn($output);

        $process->expects($this->once())
            ->method('getErrorOutput')
            ->willReturn($errorOutput);

        $process->expects($this->once())
            ->method('getExitCode')
            ->willReturn($exitCode);

        $process->expects($this->once())
            ->method('getExitCodeText')
            ->willReturn($exitText);

        $process->expects($this->once())
            ->method('isOutputDisabled')
            ->willReturn(false);

        $process->expects($this->once())
            ->method('getWorkingDirectory')
            ->willReturn($workingDirectory);

        $exception = new ProcessFailedException($process);

        $this->assertEquals(
            "The command \"$cmd\" failed.\n\nExit Code: $exitCode($exitText)\n\nWorking directory: {$workingDirectory}\n\nOutput:\n================\n{$output}\n\nError Output:\n================\n{$errorOutput}",
            $exception->getMessage()
        );
    }

    /**
     * Tests that ProcessFailedException does not extract information from
     * process output if it was previously disabled.
     */
    public function testDisabledOutputInFailedExceptionDoesNotPopulateOutput()
    {
        $cmd = 'php';
        $exitCode = 1;
        $exitText = 'General error';
        $workingDirectory = getcwd();

        $process = $this->getMockBuilder('Symfony\Component\Process\Process')->setMethods(['isSuccessful', 'isOutputDisabled', 'getExitCode', 'getExitCodeText', 'getOutput', 'getErrorOutput', 'getWorkingDirectory'])->setConstructorArgs([$cmd])->getMock();
        $process->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(false);

        $process->expects($this->never())
            ->method('getOutput');

        $process->expects($this->never())
            ->method('getErrorOutput');

        $process->expects($this->once())
            ->method('getExitCode')
            ->willReturn($exitCode);

        $process->expects($this->once())
            ->method('getExitCodeText')
            ->willReturn($exitText);

        $process->expects($this->once())
            ->method('isOutputDisabled')
            ->willReturn(true);

        $process->expects($this->once())
            ->method('getWorkingDirectory')
            ->willReturn($workingDirectory);

        $exception = new ProcessFailedException($process);

        $this->assertEquals(
            "The command \"$cmd\" failed.\n\nExit Code: $exitCode($exitText)\n\nWorking directory: {$workingDirectory}",
            $exception->getMessage()
        );
    }
}
