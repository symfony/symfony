<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tester;

use Symfony\Component\Console\Tester\Constraint\CommandFailed;
use Symfony\Component\Console\Tester\Constraint\CommandIsInvalid;
use Symfony\Component\Console\Tester\Constraint\CommandIsSuccessful;

/**
 * @psalm-require-extends \PHPUnit\Framework\TestCase
 *
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
trait ConsoleAssertionsTrait
{
    public function assertIsSuccessful(ExecutionResult $result, string $message = ''): void
    {
        $this->assertThat($result->statusCode, new CommandIsSuccessful(), $message);
    }

    public function assertFailed(ExecutionResult $result, string $message = ''): void
    {
        $this->assertThat($result->statusCode, new CommandFailed(), $message);
    }

    public function assertIsInvalid(ExecutionResult $result, string $message = ''): void
    {
        $this->assertThat($result->statusCode, new CommandIsInvalid(), $message);
    }

    public function assertResultEquals(ExecutionResult $result, ?int $expectedStatusCode = null, ?string $expectedOutput = null, ?string $expectedErrorOutput = null, ?string $expectedDisplay = null, string $message = ''): void
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
