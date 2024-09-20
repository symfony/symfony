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

use Symfony\Component\Validator\Constraints\CompoundValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Tests\Fixtures\DummyCompoundConstraint;

class CompoundValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): CompoundValidator
    {
        return new CompoundValidator();
    }

    public function testValidValue()
    {
        $this->validator->validate('foo', new DummyCompoundConstraint());

        $this->assertNoViolation();
    }

    public function testValidateWithConstraints()
    {
        $value = 'foo';
        $constraint = new DummyCompoundConstraint();

        $this->expectValidateValue(0, $value, $constraint->constraints);

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }
}
