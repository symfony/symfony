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

    public function getTenToTwenty()
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

    public function getLessThanTen()
    {
        return array(
            array(9.99999),
            array('9.99999'),
            array(5),
            array(1.0),
        );
    }

    public function getMoreThanTwenty()
    {
        return array(
            array(20.000001),
            array('20.000001'),
            array(21),
            array(30.0),
        );
    }

    /**
     * @dataProvider getTenToTwenty
     */
    public function testValidValuesMin($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Range(array('min' => 10));
        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getTenToTwenty
     */
    public function testValidValuesMax($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Range(array('max' => 20));
        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getTenToTwenty
     */
    public function testValidValuesMinMax($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Range(array('min' => 10, 'max' => 20));
        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getLessThanTen
     */
    public function testInvalidValuesMin($value)
    {
        $constraint = new Range(array(
            'min' => 10,
            'minMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', $this->identicalTo(array(
                '{{ value }}' => $value,
                '{{ limit }}' => 10,
        )));

        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getMoreThanTwenty
     */
    public function testInvalidValuesMax($value)
    {
        $constraint = new Range(array(
            'max' => 20,
            'maxMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', $this->identicalTo(array(
                '{{ value }}' => $value,
                '{{ limit }}' => 20,
            )));

        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getMoreThanTwenty
     */
    public function testInvalidValuesCombinedMax($value)
    {
        $constraint = new Range(array(
            'min' => 10,
            'max' => 20,
            'minMessage' => 'myMinMessage',
            'maxMessage' => 'myMaxMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMaxMessage', $this->identicalTo(array(
                '{{ value }}' => $value,
                '{{ limit }}' => 20,
            )));

        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getLessThanTen
     */
    public function testInvalidValuesCombinedMin($value)
    {
        $constraint = new Range(array(
            'min' => 10,
            'max' => 20,
            'minMessage' => 'myMinMessage',
            'maxMessage' => 'myMaxMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMinMessage', $this->identicalTo(array(
            '{{ value }}' => $value,
            '{{ limit }}' => 10,
        )));

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
