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

use Symfony\Component\Validator\Constraints\Size;
use Symfony\Component\Validator\Constraints\SizeValidator;

class SizeValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new SizeValidator();
        $this->validator->initialize($this->context);
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->assertTrue($this->validator->isValid(null, new Size(array('min' => 10, 'max' => 20))));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new Size(array('min' => 10, 'max' => 20));
        $this->assertTrue($this->validator->isValid($value, $constraint));
    }

    public function getValidValues()
    {
        $array = range(1, 15);
        $countableMock = $this->getMock('Countable');
        $countableMock
            ->expects($this->any())
            ->method('count')
            ->will($this->returnValue(15))
        ;

        return array(
            array(10.00001),
            array(19.99999),
            array('10.00001'),
            array('19.99999'),
            array(10),
            array(20),
            array(10.0),
            array(20.0),
            array($array),
            array($countableMock)
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value)
    {
        $this->context->expects($this->once())
            ->method('addViolation');

        $constraint = new Size(array('min' => 10, 'max' => 20));
        $this->assertFalse($this->validator->isValid($value, $constraint));
    }

    public function getInvalidValues()
    {
        $smallerArray = range(1, 9);
        $biggerArray = range(1, 21);
        $smallerCountableMock = $this->getMock('Countable');
        $smallerCountableMock
            ->expects($this->any())
            ->method('count')
            ->will($this->returnValue(9))
        ;
        $biggerCountableMock = $this->getMock('Countable');
        $biggerCountableMock
            ->expects($this->any())
            ->method('count')
            ->will($this->returnValue(21))
        ;

        return array(
            array(9.999999),
            array(20.000001),
            array('9.999999'),
            array('20.000001'),
            array(new \stdClass()),
            array($smallerArray),
            array($biggerArray),
            array($smallerCountableMock),
            array($biggerCountableMock)
        );
    }

    public function testMinMessageIsSet()
    {
        $constraint = new Size(array(
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

        $this->assertFalse($this->validator->isValid(9, $constraint));
    }

    public function testMaxMessageIsSet()
    {
        $constraint = new Size(array(
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

        $this->assertFalse($this->validator->isValid(21, $constraint));
    }
}
