<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\Constraint\CommandFailed;
use Symfony\Component\Console\Tester\Constraint\CommandIsInvalid;
use Symfony\Component\Console\Tester\Constraint\CommandIsSuccessful;
use Symfony\Component\Console\Tester\ExecutionResult;

trait ConsoleCommandAssertionsTrait
{
    /**
     * Runs a console command and returns the execution result.
     *
     * @param array                           $input             An array of command arguments and options
     * @param string[]                        $interactiveInputs An array of strings representing each input passed to the command input stream
     * @param array<\Closure(string): string> $normalizers
     */
    public static function runCommand(string $name, array $input = [], array $interactiveInputs = [], ?bool $interactive = null, ?bool $decorated = null, ?int $verbosity = null, array $normalizers = []): ExecutionResult
    {
        $application = new Application(static::getContainer()->get('kernel'));
        $command = $application->find($name);
        $commandTester = new CommandTester($command);

        return $commandTester->run($input, $interactiveInputs, $interactive, $decorated, $verbosity, $normalizers);
    }

    public function assertCommandIsSuccessful(ExecutionResult $result, string $message = ''): void
    {
        $this->assertThat($result->statusCode, new CommandIsSuccessful(), $message);
    }

    public function assertCommandFailed(ExecutionResult $result, string $message = ''): void
    {
        $this->assertThat($result->statusCode, new CommandFailed(), $message);
    }

    public function assertCommandIsInvalid(ExecutionResult $result, string $message = ''): void
    {
        $this->assertThat($result->statusCode, new CommandIsInvalid(), $message);
    }

    public function assertCommandResultEquals(ExecutionResult $result, ?int $expectedStatusCode = null, ?string $expectedOutput = null, ?string $expectedErrorOutput = null, ?string $expectedDisplay = null, string $message = ''): void
    {
        $expected = [];
        $actual = [];

        if (null !== $expectedStatusCode) {
            $expected['statusCode'] = $expectedStatusCode;
            $actual['statusCode'] = $result->statusCode;
        }
        if (null !== $expectedOutput) {
            $expected['output'] = $expectedOutput;
            $actual['output'] = $result->getOutput();
        }
        if (null !== $expectedErrorOutput) {
            $expected['errorOutput'] = $expectedErrorOutput;
            $actual['errorOutput'] = $result->getErrorOutput();
        }
        if (null !== $expectedDisplay) {
            $expected['display'] = $expectedDisplay;
            $actual['display'] = $result->getDisplay();
        }

        $this->assertEquals($expected, $actual, $message);
    }
}
