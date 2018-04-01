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

use Symphony\Component\Validator\Constraints\Regex;
use Symphony\Component\Validator\Constraints\RegexValidator;
use Symphony\Component\Validator\Test\ConstraintValidatorTestCase;

class RegexValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new RegexValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Regex(array('pattern' => '/^[0-9]+$/')));

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Regex(array('pattern' => '/^[0-9]+$/')));

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symphony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Regex(array('pattern' => '/^[0-9]+$/')));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $constraint = new Regex(array('pattern' => '/^[0-9]+$/'));
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public function getValidValues()
    {
        return array(
            array(0),
            array('0'),
            array('090909'),
            array(90909),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value)
    {
        $constraint = new Regex(array(
            'pattern' => '/^[0-9]+$/',
            'message' => 'myMessage',
        ));

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setCode(Regex::REGEX_FAILED_ERROR)
            ->assertRaised();
    }

    public function getInvalidValues()
    {
        return array(
            array('abcd'),
            array('090foo'),
        );
    }
}
