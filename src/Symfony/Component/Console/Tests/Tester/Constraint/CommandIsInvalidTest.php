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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\Constraint\CommandIsInvalid;

final class CommandIsInvalidTest extends TestCase
{
    public function testConstraint()
    {
        $constraint = new CommandIsInvalid();

        $this->assertFalse($constraint->evaluate(Command::SUCCESS, '', true));
        $this->assertFalse($constraint->evaluate(Command::FAILURE, '', true));
        $this->assertTrue($constraint->evaluate(Command::INVALID, '', true));
    }

    #[DataProvider('providesUnsuccessful')]
    public function testUnsuccessfulCommand(string $expectedException, int $exitCode)
    {
        $constraint = new CommandIsInvalid();

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessageMatches('/Failed asserting that the command is invalid\..*'.$expectedException.'/s');
        $constraint->evaluate($exitCode);
    }

    public static function providesUnsuccessful(): iterable
    {
        yield 'Successful' => ['Command was successful.', Command::SUCCESS];
        yield 'Failed' => ['Command failed.', Command::FAILURE];
        yield 'Exit code 3' => ['Command returned exit status 3.', 3];
    }
}
