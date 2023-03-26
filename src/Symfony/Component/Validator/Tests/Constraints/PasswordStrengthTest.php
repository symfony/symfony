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
        $this->assertEquals(2, $constraint->minScore);
        $this->assertEquals([], $constraint->restrictedData);
    }

    public function testConstructorWithParameters()
    {
        $constraint = new PasswordStrength([
            'minScore' => 3,
            'restrictedData' => ['foo', 'bar'],
        ]);

        $this->assertEquals(3, $constraint->minScore);
        $this->assertEquals(['foo', 'bar'], $constraint->restrictedData);
    }

    public function testInvalidScoreOfZero()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The parameter "minScore" of the "Symfony\Component\Validator\Constraints\PasswordStrength" constraint must be an integer between 1 and 4.');
        new PasswordStrength(['minScore' => 0]);
    }

    public function testInvalidScoreOfFive()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The parameter "minScore" of the "Symfony\Component\Validator\Constraints\PasswordStrength" constraint must be an integer between 1 and 4.');
        new PasswordStrength(['minScore' => 5]);
    }

    public function testInvalidRestrictedData()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The parameter "restrictedData" of the "Symfony\Component\Validator\Constraints\PasswordStrength" constraint must be a list of strings.');
        new PasswordStrength(['restrictedData' => [123]]);
    }
}
