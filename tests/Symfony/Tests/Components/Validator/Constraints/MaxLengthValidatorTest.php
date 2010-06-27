<?php

namespace Symfony\Tests\Components\Validator;

use Symfony\Components\Validator\Constraints\MaxLength;
use Symfony\Components\Validator\Constraints\MaxLengthValidator;

class MaxLengthValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    public function setUp()
    {
        $this->validator = new MaxLengthValidator();
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new MaxLength(array('limit' => 5))));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Components\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new MaxLength(array('limit' => 5)));
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value, $skip = false)
    {
        if (!$skip) {
            $constraint = new MaxLength(array('limit' => 5));
            $this->assertTrue($this->validator->isValid($value, $constraint));
        }
    }

    public function getValidValues()
    {
        return array(
            array(12345),
            array('12345'),
            array('üüüüü', !function_exists('mb_strlen')),
            array('ééééé', !function_exists('mb_strlen')),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $skip = false)
    {
        if (!$skip) {
            $constraint = new MaxLength(array('limit' => 5));
            $this->assertFalse($this->validator->isValid($value, $constraint));
        }
    }

    public function getInvalidValues()
    {
        return array(
            array(123456),
            array('123456'),
            array('üüüüüü', !function_exists('mb_strlen')),
            array('éééééé', !function_exists('mb_strlen')),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new MaxLength(array(
            'limit' => 5,
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('123456', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            'value' => '123456',
            'limit' => 5,
        ));
    }
}