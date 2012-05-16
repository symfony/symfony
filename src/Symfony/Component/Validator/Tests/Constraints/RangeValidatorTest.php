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

use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\RangeValidator;

class RangeValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new RangeValidator();
        $this->validator->initialize($this->context);
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new Range(array('min' => 10, 'max' => 20)));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Range(array('min' => 10, 'max' => 20));
        $this->validator->validate($value, $constraint);
    }

    public function getValidValues()
    {
        return array(
            array(10.00001),
            array(19.99999),
            array('10.00001'),
            array('19.99999'),
            array(10),
            array(20),
            array(10.0),
            array(20.0),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value)
    {
        $this->context->expects($this->once())
            ->method('addViolation');

        $constraint = new Range(array('min' => 10, 'max' => 20));
        $this->validator->validate($value, $constraint);
    }

    public function getInvalidValues()
    {
        return array(
            array(9.999999),
            array(20.000001),
            array('9.999999'),
            array('20.000001'),
            array(new \stdClass()),
        );
    }

    public function testMinMessageIsSet()
    {
        $constraint = new Range(array(
            'min' => 10,
            'max' => 20,
            'minMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => 9,
                '{{ limit }}' => 10,
            ));

        $this->validator->validate(9, $constraint);
    }

    public function testMaxMessageIsSet()
    {
        $constraint = new Range(array(
            'min' => 10,
            'max' => 20,
            'maxMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => 21,
                '{{ limit }}' => 20,
            ));

        $this->validator->validate(21, $constraint);
    }
}
