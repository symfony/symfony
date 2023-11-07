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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\Constraint\CommandIsFaulty;

final class CommandIsFaultyTest extends TestCase
{
    public function testConstraint()
    {
        $constraint = new CommandIsFaulty();

        $this->assertFalse($constraint->evaluate(Command::SUCCESS, returnResult: true));
        $this->assertTrue($constraint->evaluate(Command::FAILURE, returnResult: true));
        $this->assertTrue($constraint->evaluate(Command::INVALID, returnResult: true));
    }

    public function testSuccessfulCommand()
    {
        $constraint = new CommandIsFaulty();

        try {
            $constraint->evaluate(Command::SUCCESS);
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString('Failed asserting that the command is faulty.', $e->getMessage());

            return;
        }

        $this->fail();
    }
}
