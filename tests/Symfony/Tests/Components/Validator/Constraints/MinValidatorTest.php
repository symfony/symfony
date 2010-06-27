<?php

namespace Symfony\Tests\Components\Validator;

use Symfony\Components\Validator\Constraints\Min;
use Symfony\Components\Validator\Constraints\MinValidator;

class MinValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    public function setUp()
    {
        $this->validator = new MinValidator();
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Min(array('limit' => 10))));
    }

    public function testExpectsNumericType()
    {
        $this->setExpectedException('Symfony\Components\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new Min(array('limit' => 10)));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $constraint = new Min(array('limit' => 10));
        $this->assertTrue($this->validator->isValid($value, $constraint));
    }

    public function getValidValues()
    {
        return array(
            array(10.00001),
            array('10.00001'),
            array(10),
            array(10.0),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value)
    {
        $constraint = new Min(array('limit' => 10));
        $this->assertFalse($this->validator->isValid($value, $constraint));
    }

    public function getInvalidValues()
    {
        return array(
            array(9.999999),
            array('9.999999'),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Min(array(
            'limit' => 10,
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid(9, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            'value' => 9,
            'limit' => 10,
        ));
    }
}