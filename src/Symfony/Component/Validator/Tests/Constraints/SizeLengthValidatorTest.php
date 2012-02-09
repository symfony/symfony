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

use Symfony\Component\Validator\Constraints\SizeLength;
use Symfony\Component\Validator\Constraints\SizeLengthValidator;

class SizeLengthValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new SizeLengthValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->context = null;
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new SizeLength(array('min' => 6, 'max' => 10)));
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new SizeLength(array('min' => 6, 'max' => 10)));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new SizeLength(array('min' => 6, 'max' => 10)));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            return $this->markTestSkipped('mb_strlen does not exist');
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new SizeLength(array('min' => 6, 'max' => 10));
        $this->validator->validate($value, $constraint);
    }

    public function getValidValues()
    {
        return array(
            array(123456),
            array(1234567890),
            array('123456'),
            array('1234567890'),
            array('üüüüüü', true),
            array('üüüüüüüüüü', true),
            array('éééééé', true),
            array('éééééééééé', true),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $mbOnly = false)
    {
        if ($mbOnly && !function_exists('mb_strlen')) {
            return $this->markTestSkipped('mb_strlen does not exist');
        }

        $this->context->expects($this->once())
            ->method('addViolation');

        $constraint = new SizeLength(array('min' => 6, 'max' => 10));
        $this->validator->validate($value, $constraint);
    }

    public function getInvalidValues()
    {
        return array(
            array(12345),
            array(12345678901),
            array('12345'),
            array('12345678901'),
            array('üüüüü', true),
            array('üüüüüüüüüüü', true),
            array('ééééé', true),
            array('ééééééééééé', true),
        );
    }

    public function testMinMessageIsSet()
    {
        $constraint = new SizeLength(array(
            'min' => 5,
            'max' => 10,
            'minMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => '1234',
                '{{ limit }}' => 5,
            ), null, 5);

        $this->validator->validate('1234', $constraint);
    }

    public function testMaxMessageIsSet()
    {
        $constraint = new SizeLength(array(
            'min' => 5,
            'max' => 10,
            'maxMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => '12345678901',
                '{{ limit }}' => 10,
            ), null, 10);

        $this->validator->validate('12345678901', $constraint);
    }

    public function testExactMessageIsSet()
    {
        $constraint = new SizeLength(array(
            'min' => 5,
            'max' => 5,
            'exactMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => '1234',
                '{{ limit }}' => 5,
            ), null, 5);

        $this->validator->validate('1234', $constraint);
    }
}
