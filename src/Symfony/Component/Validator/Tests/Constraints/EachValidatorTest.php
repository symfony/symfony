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

use Symfony\Component\Validator\Constraints\Each;
use Symfony\Component\Validator\Constraints\EachValidator;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class EachValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new EachValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Each(new Range(['min' => 4])));
        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedValueException
     */
    public function testThrowsExceptionIfNotTraversable()
    {
        $this->validator->validate('foo.barbar', new Each(new Range(['min' => 4])));
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkSingleConstraint($array)
    {
        $constraint = new Range(['min' => 4]);
        $i = 0;
        foreach ($array as $key => $value) {
            $this->expectValidateValueAt($i++, '['.$key.']', $value, [$constraint]);
        }
        $this->validator->validate($array, new Each($constraint));
        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkMultipleConstraints($array)
    {
        $constraint1 = new Range(['min' => 4]);
        $constraint2 = new NotNull();
        $constraints = [$constraint1, $constraint2];
        $i = 0;
        foreach ($array as $key => $value) {
            $this->expectValidateValueAt($i++, '['.$key.']', $value, [$constraint1, $constraint2]);
        }
        $this->validator->validate($array, new Each($constraints));
        $this->assertNoViolation();
    }

    public function getValidArguments()
    {
        return [
            [[5, 6, 7]],
            [new \ArrayObject([5, 6, 7])],
        ];
    }
}
