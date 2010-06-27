<?php

namespace Symfony\Tests\Components\Validator;

use Symfony\Components\Validator\Constraints\DateTime;
use Symfony\Components\Validator\Constraints\DateTimeValidator;

class DateTimeValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    public function setUp()
    {
        $this->validator = new DateTimeValidator();
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new DateTime()));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Components\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new DateTime());
    }

    /**
     * @dataProvider getValidDateTimes
     */
    public function testValidDateTimes($date)
    {
        $this->assertTrue($this->validator->isValid($date, new DateTime()));
    }

    public function getValidDateTimes()
    {
        return array(
            array('2010-01-01 01:02:03'),
            array('1955-12-12 00:00:00'),
            array('2030-05-31 23:59:59'),
        );
    }

    /**
     * @dataProvider getInvalidDateTimes
     */
    public function testInvalidDateTimes($date)
    {
        $this->assertFalse($this->validator->isValid($date, new DateTime()));
    }

    public function getInvalidDateTimes()
    {
        return array(
            array('foobar'),
            array('2010-01-01'),
            array('00:00:00'),
            array('2010-01-01 00:00'),
            array('2010-13-01 00:00:00'),
            array('2010-04-32 00:00:00'),
            array('2010-02-29 00:00:00'),
            array('2010-01-01 24:00:00'),
            array('2010-01-01 00:60:00'),
            array('2010-01-01 00:00:60'),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new DateTime(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            'value' => 'foobar',
        ));
    }
}