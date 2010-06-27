<?php

namespace Symfony\Tests\Components\Validator;

use Symfony\Components\Validator\Constraints\NotNull;
use Symfony\Components\Validator\Constraints\NotNullValidator;

class NotNullValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    public function setUp()
    {
        $this->validator = new NotNullValidator();
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $this->assertTrue($this->validator->isValid($value, new NotNull()));
    }

    public function getValidValues()
    {
        return array(
            array(0),
            array(false),
            array(true),
            array(''),
        );
    }

    public function testNullIsInvalid()
    {
        $constraint = new NotNull(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid(null, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array());
    }
}