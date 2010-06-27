<?php

namespace Symfony\Tests\Components\Validator;

use Symfony\Components\Validator\Constraints\AssertFalse;
use Symfony\Components\Validator\Constraints\AssertFalseValidator;

class AssertFalseValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    public function setUp()
    {
        $this->validator = new AssertFalseValidator();
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new AssertFalse()));
    }

    public function testFalseIsValid()
    {
        $this->assertTrue($this->validator->isValid(false, new AssertFalse()));
    }

    public function testTrueIsInvalid()
    {
        $constraint = new AssertFalse(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid(true, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array());
    }
}