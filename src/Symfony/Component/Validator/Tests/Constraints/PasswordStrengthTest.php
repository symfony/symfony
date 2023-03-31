<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

class PasswordStrengthTest extends TestCase
{
    public function testConstructor()
    {
        $constraint = new PasswordStrength();
        $this->assertSame(2, $constraint->minScore);
    }

    public function testConstructorWithParameters()
    {
        $constraint = new PasswordStrength(minScore: PasswordStrength::STRENGTH_STRONG);

        $this->assertSame(PasswordStrength::STRENGTH_STRONG, $constraint->minScore);
    }

    public function testInvalidScoreOfZero()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The parameter "minScore" of the "Symfony\Component\Validator\Constraints\PasswordStrength" constraint must be an integer between 1 and 4.');
        new PasswordStrength(minScore: PasswordStrength::STRENGTH_VERY_WEAK);
    }

    public function testInvalidScoreOfFive()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The parameter "minScore" of the "Symfony\Component\Validator\Constraints\PasswordStrength" constraint must be an integer between 1 and 4.');
        new PasswordStrength(minScore: 5);
    }
}
