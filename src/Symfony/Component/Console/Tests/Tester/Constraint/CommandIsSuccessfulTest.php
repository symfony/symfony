<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Tester\Constraint;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestFailure;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\Constraint\CommandIsSuccessful;

final class CommandIsSuccessfulTest extends TestCase
{
    public function testConstraint()
    {
        $constraint = new CommandIsSuccessful();

        $this->assertTrue($constraint->evaluate(Command::SUCCESS, '', true));
        $this->assertFalse($constraint->evaluate(Command::FAILURE, '', true));
        $this->assertFalse($constraint->evaluate(Command::INVALID, '', true));
    }

    /**
     * @dataProvider providesUnsuccessful
     */
    public function testUnsuccessfulCommand(string $expectedException, int $exitCode)
    {
        $constraint = new CommandIsSuccessful();

        try {
            $constraint->evaluate($exitCode);
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString('Failed asserting that the command is successful.', TestFailure::exceptionToString($e));
            $this->assertStringContainsString($expectedException, TestFailure::exceptionToString($e));

            return;
        }

        $this->fail();
    }

    public function providesUnsuccessful(): iterable
    {
        yield 'Failed' => ['Command failed.', Command::FAILURE];
        yield 'Invalid' => ['Command was invalid.', Command::INVALID];
        yield 'Exit code 3' => ['Command returned exit status 3.', 3];
    }
}
