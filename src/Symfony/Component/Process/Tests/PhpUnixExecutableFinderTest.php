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
use Symfony\Component\Process\PhpUnixExecutableFinder;
use Symfony\Component\Process\Contracts\ExecutableFinderInterface;
use Symfony\Component\Process\Contracts\CommandExecutorInterface;
use Symfony\Component\Process\Exception\PhpUnixExecutableNotFoundException;

/**
 * @author Pululu Kinanga Andre <pululuandre@gmail.com>
 */
class PhpUnixExecutableFinderTest extends TestCase
{
    private ?ExecutableFinderInterface $defaultExecutableFinder;
    private ?CommandExecutorInterface $commandExecutor;
    private PhpUnixExecutableFinder $phpExecutableFinder;

    /**
     * Set up the necessary mocks and the PhpUnixExecutableFinder instance.
     */
    protected function setUp(): void
    {
        // Create a mock for ExecutableFinderInterface
        $this->defaultExecutableFinder = $this->createMock(ExecutableFinderInterface::class);

        // Use a custom CommandExecutor implementation for simulating shell commands
        $this->commandExecutor = $this->createMock(CommandExecutorInterface::class);

        // Create the PhpUnixExecutableFinder instance
        $this->phpExecutableFinder = new PhpUnixExecutableFinder($this->commandExecutor, $this->defaultExecutableFinder);
    }

    /**
     * Test that the find method returns the correct PHP executable path for the default executable.
     */
    public function testFindDefaultExecutable(): void
    {
        // Mock the behavior of the defaultExecutableFinder to return a valid PHP path
        $this->defaultExecutableFinder
            ->expects($this->once())
            ->method('find')
            ->willReturn('/usr/bin/php');

        $result = $this->phpExecutableFinder->find();
        $this->assertEquals('/usr/bin/php', $result);
    }

    /**
     * Test that the find method returns the correct PHP executable path for a specific version.
     */
    public function testFindSpecificVersionExecutable(): void
    {
        // Simulate command execution returning the correct path for php8.3
        $this->commandExecutor
            ->expects($this->once())
            ->method('execute')
            ->with('command -v php8.3')
            ->willReturn('/usr/bin/php8.3');

        $result = $this->phpExecutableFinder->find('8.3');
        $this->assertEquals('/usr/bin/php8.3', $result);
    }

    /**
     * Test the find method throws PhpUnixExecutableNotFoundException if the specific version executable is not found.
     */
    public function testFindSpecificVersionExecutableThrowsException(): void
    {
        // Simulate command execution returning an empty result (no PHP executable found)
        $this->commandExecutor
            ->expects($this->once())
            ->method('execute')
            ->with('command -v php8.3')
            ->willReturn('');

        $this->expectException(PhpUnixExecutableNotFoundException::class);
        $this->expectExceptionMessage('PHP executable not found for the given version');

        $this->phpExecutableFinder->find('8.3');
    }

    /**
     * Test the find method when defaultExecutableFinder is null.
     */
    public function testFindWhenDefaultExecutableFinderIsNull(): void
    {
        // Set defaultExecutableFinder to null by creating a new PhpUnixExecutableFinder instance
        $this->phpExecutableFinder = new PhpUnixExecutableFinder($this->commandExecutor, null);

        // Simulate command execution returning the correct path for php
        $this->commandExecutor
            ->expects($this->once())
            ->method('execute')
            ->with('command -v php')
            ->willReturn('/usr/bin/php');

        // The find method should return the PHP executable path
        $result = $this->phpExecutableFinder->find();
        $this->assertEquals('/usr/bin/php', $result);
    }
}
