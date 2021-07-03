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

        try {
            $constraint->evaluate(Command::FAILURE);
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString('Failed asserting that the command is successful.', TestFailure::exceptionToString($e));

            return;
        }

        $this->fail();
    }
}
