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

use Symfony\Component\Validator\Constraints\Any;
use Symfony\Component\Validator\Constraints\AnyValidator;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Validation;

class AnyValidatorTest extends AbstractConstraintValidatorTest
{
    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_5;
    }

    protected function createValidator()
    {
        return new AnyValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Any(new Range(array('min' => 4))));

        $this->assertNoViolation();
    }

    public function testWalkSingleConstraint()
    {
        $value = 5;
        $constraint = new Range(array('min' => 4));

        $this->validator->validate($value, new Any($constraint));

        $this->assertNoViolation();
    }

    public function testWalkMultipleConstraints()
    {
        $value = 1;
        $constraint1 = new Range(array('min' => 4));
        $constraint2 = new NotNull();
        $constraints = array($constraint1, $constraint2);

        $this->validator->validate($value, new Any($constraints));

        $this->assertNoViolation();
    }

    public function testNoConstraintValidated()
    {
        $value = 1;
        $constraint1 = new Range(array('min' => 4));
        $constraint2 = new NotNull();
        $constraints = array($constraint1, $constraint2);
        $any = new Any($constraints);

        $this->validator->validate($value, $any);

        $this->assertViolation($any->message);
    }
}
