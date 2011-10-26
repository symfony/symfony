<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraints\SizeLength;
use Symfony\Component\Validator\Constraints\SizeLengthValidator;

class SizeLengthValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new SizeLengthValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new SizeLength(array('min' => 6, 'max' => 10))));
    }

    public function testEmptyStringIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new SizeLength(array('min' => 6, 'max' => 10))));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new SizeLength(array('min' => 6, 'max' => 10)));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value, $skip = false)
    {
        if (!$skip) {
            $constraint = new SizeLength(array('min' => 6, 'max' => 10));
            $this->assertTrue($this->validator->isValid($value, $constraint));
        }
    }

    public function getValidValues()
    {
        return array(
            array(123456),
            array(1234567890),
            array('123456'),
            array('1234567890'),
            array('üüüüüü', !function_exists('mb_strlen')),
            array('üüüüüüüüüü', !function_exists('mb_strlen')),
            array('éééééé', !function_exists('mb_strlen')),
            array('éééééééééé', !function_exists('mb_strlen')),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $skip = false)
    {
        if (!$skip) {
            $constraint = new SizeLength(array('min' => 6, 'max' => 10));
            $this->assertFalse($this->validator->isValid($value, $constraint));
        }
    }

    public function getInvalidValues()
    {
        return array(
            array(12345),
            array(12345678901),
            array('12345'),
            array('12345678901'),
            array('üüüüü', !function_exists('mb_strlen')),
            array('üüüüüüüüüüü', !function_exists('mb_strlen')),
            array('ééééé', !function_exists('mb_strlen')),
            array('ééééééééééé', !function_exists('mb_strlen')),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new SizeLength(array(
            'min' => 5,
            'max' => 10,
            'minMessage' => 'myMinMessage',
            'maxMessage' => 'myMaxMessage',
        ));

        $this->assertFalse($this->validator->isValid('1234', $constraint));
        $this->assertEquals('myMinMessage', $this->validator->getMessageTemplate());
        $this->assertEquals(array(
            '{{ value }}' => '1234',
            '{{ limit }}' => 5,
        ), $this->validator->getMessageParameters());

        $this->assertFalse($this->validator->isValid('12345678901', $constraint));
        $this->assertEquals('myMaxMessage', $this->validator->getMessageTemplate());
        $this->assertEquals(array(
            '{{ value }}' => '12345678901',
            '{{ limit }}' => 10,
        ), $this->validator->getMessageParameters());
    }

    public function testExactErrorMessage()
    {
        $constraint = new SizeLength(array(
            'min' => 5,
            'max' => 5,
        ));

        $this->assertFalse($this->validator->isValid('1234', $constraint));
        $this->assertEquals('This value should have exactly {{ limit }} characters', $this->validator->getMessageTemplate());
        $this->assertEquals(array(
            '{{ value }}' => '1234',
            '{{ limit }}' => 5,
        ), $this->validator->getMessageParameters());
    }
}
