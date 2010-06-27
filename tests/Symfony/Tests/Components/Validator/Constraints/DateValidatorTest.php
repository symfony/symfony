<?php

namespace Symfony\Tests\Components\Validator;

use Symfony\Components\Validator\Constraints\Date;
use Symfony\Components\Validator\Constraints\DateValidator;

class DateValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    public function setUp()
    {
        $this->validator = new DateValidator();
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Date()));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Components\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new Date());
    }

    /**
     * @dataProvider getValidDates
     */
    public function testValidDates($date)
    {
        $this->assertTrue($this->validator->isValid($date, new Date()));
    }

    public function getValidDates()
    {
        return array(
            array('2010-01-01'),
            array('1955-12-12'),
            array('2030-05-31'),
        );
    }

    /**
     * @dataProvider getInvalidDates
     */
    public function testInvalidDates($date)
    {
        $this->assertFalse($this->validator->isValid($date, new Date()));
    }

    public function getInvalidDates()
    {
        return array(
            array('foobar'),
            array('2010-13-01'),
            array('2010-04-32'),
            array('2010-02-29'),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Date(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            'value' => 'foobar',
        ));
    }
}