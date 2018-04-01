<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Constraints;

use Symphony\Component\Validator\Constraints\Blank;
use Symphony\Component\Validator\Constraints\BlankValidator;
use Symphony\Component\Validator\Test\ConstraintValidatorTestCase;

class BlankValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new BlankValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Blank());

        $this->assertNoViolation();
    }

    public function testBlankIsValid()
    {
        $this->validator->validate('', new Blank());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $valueAsString)
    {
        $constraint = new Blank(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $valueAsString)
            ->setCode(Blank::NOT_BLANK_ERROR)
            ->assertRaised();
    }

    public function getInvalidValues()
    {
        return array(
            array('foobar', '"foobar"'),
            array(0, '0'),
            array(false, 'false'),
            array(1234, '1234'),
        );
    }
}
