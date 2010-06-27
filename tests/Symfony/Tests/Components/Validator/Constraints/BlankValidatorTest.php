<?php

namespace Symfony\Tests\Components\Validator;

use Symfony\Components\Validator\Constraints\Blank;
use Symfony\Components\Validator\Constraints\BlankValidator;

class BlankValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    public function setUp()
    {
        $this->validator = new BlankValidator();
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Blank()));
    }

    public function testBlankIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new Blank()));
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($date)
    {
        $this->assertFalse($this->validator->isValid($date, new Blank()));
    }

    public function getInvalidValues()
    {
        return array(
            array('foobar'),
            array(0),
            array(false),
            array(1234),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Blank(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            'value' => 'foobar',
        ));
    }
}