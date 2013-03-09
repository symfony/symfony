<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\DateTimeValidator;

class DateTimeValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new DateTimeValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->context = null;
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new DateTime());
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new DateTime());
    }

    public function testDateTimeClassIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(new \DateTime(), new DateTime());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new DateTime());
    }

    /**
     * @dataProvider getValidDateTimes
     */
    public function testValidDateTimes($dateTime)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($dateTime, new DateTime());
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
    public function testInvalidDateTimes($dateTime)
    {
        $constraint = new DateTime(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $dateTime,
            ));

        $this->validator->validate($dateTime, $constraint);
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
}
