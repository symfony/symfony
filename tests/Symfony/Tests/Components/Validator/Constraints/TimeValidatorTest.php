<?php

namespace Symfony\Tests\Components\Validator;

use Symfony\Components\Validator\Constraints\Time;
use Symfony\Components\Validator\Constraints\TimeValidator;

class TimeValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    public function setUp()
    {
        $this->validator = new TimeValidator();
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Time()));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Components\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new Time());
    }

    /**
     * @dataProvider getValidTimes
     */
    public function testValidTimes($time)
    {
        $this->assertTrue($this->validator->isValid($time, new Time()));
    }

    public function getValidTimes()
    {
        return array(
            array('01:02:03'),
            array('00:00:00'),
            array('23:59:59'),
        );
    }

    /**
     * @dataProvider getInvalidTimes
     */
    public function testInvalidTimes($time)
    {
        $this->assertFalse($this->validator->isValid($time, new Time()));
    }

    public function getInvalidTimes()
    {
        return array(
            array('foobar'),
            array('00:00'),
            array('24:00:00'),
            array('00:60:00'),
            array('00:00:60'),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Time(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            'value' => 'foobar',
        ));
    }
}