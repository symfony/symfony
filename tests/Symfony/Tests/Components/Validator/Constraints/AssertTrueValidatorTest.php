<?php

namespace Symfony\Tests\Components\Validator;

use Symfony\Components\Validator\Constraints\AssertTrue;
use Symfony\Components\Validator\Constraints\AssertTrueValidator;

class AssertTrueValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    public function setUp()
    {
        $this->validator = new AssertTrueValidator();
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new AssertTrue()));
    }

    public function testTrueIsValid()
    {
        $this->assertTrue($this->validator->isValid(true, new AssertTrue()));
    }

    public function testFalseIsInvalid()
    {
        $constraint = new AssertTrue(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid(false, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array());
    }
}