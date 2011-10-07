<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Time;
use Symfony\Component\Validator\Constraints\TimeValidator;

class TimeValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new TimeValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Time()));
    }

    public function testEmptyStringIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new Time()));
    }

    public function testDateTimeClassIsValid()
    {
        $this->assertTrue($this->validator->isValid(new \DateTime(), new Time()));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\UnexpectedTypeException');

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
            array('foobar 12:34:56'),
            array('12:34:56 foobar'),
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
            '{{ value }}' => 'foobar',
        ));
    }
}
