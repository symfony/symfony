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

use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\DateValidator;

class DateValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new DateValidator();
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

        $this->validator->validate(null, new Date());
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new Date());
    }

    public function testDateTimeClassIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(new \DateTime(), new Date());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Date());
    }

    /**
     * @dataProvider getValidDates
     */
    public function testValidDates($date)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($date, new Date());
    }

    public function getValidDates()
    {
        return array(
            array(''),
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
        $constraint = new Date(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $date,
            ));

        $this->validator->validate($date, $constraint);
    }

    public function getInvalidDates()
    {
        return array(
            array('foobar'),
            array('foobar 2010-13-01'),
            array('2010-13-01 foobar'),
            array('2010-13-01'),
            array('2010-04-32'),
            array('2010-02-29'),
        );
    }

    /**
     * @dataProvider getValidBeforeDates
     */
    public function testValidBeforeDates($date)
    {
        $constraint = new Date(array(
            'before' => '2015-01-01'
        ));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($date, $constraint);
    }

    public function getValidBeforeDates()
    {
        return array(
            array('2014-12-31'),
            array('2000-01-01'),
            array('1980-01-01'),
        );
    }

    /**
     * @dataProvider getInvalidBeforeDates
     */
    public function testInvalidBeforeDates($date)
    {
        $constraint = new Date(array(
            'messageBeforeDate' => 'myMessage',
            'before' => '2015-01-01'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}'  => $date,
                '{{ before }}' => '2015-01-01',
            ));

        $this->validator->validate($date, $constraint);
    }

    public function getInvalidBeforeDates()
    {
        return array(
            array('2015-01-01'),
            array('2018-12-20'),
            array('2016-02-29'),
        );
    }

    /**
     * @dataProvider getValidAfterDates
     */
    public function testValidAfterDates($date)
    {
        $constraint = new Date(array(
            'after' => '2015-01-01'
        ));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($date, $constraint);
    }

    public function getValidAfterDates()
    {
        return array(
            array('2015-01-02'),
            array('2016-02-29'),
            array('2100-12-31'),
        );
    }

    /**
     * @dataProvider getInvalidAfterDates
     */
    public function testInvalidAfterDates($date)
    {
        $constraint = new Date(array(
            'messageAfterDate' => 'myMessage',
            'after' => '2015-01-01'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $date,
                '{{ after }}' => '2015-01-01',
            ));

        $this->validator->validate($date, $constraint);
    }

    public function getInvalidAfterDates()
    {
        return array(
            array('2015-01-01'),
            array('2014-12-31'),
            array('1980-01-01'),
        );
    }


    /**
     * @dataProvider getValidInBetweenDates
     */
    public function testValidInBetweenDates($date)
    {
        $constraint = new Date(array(
            'after'  => '2014-12-31',
            'before' => '2017-01-01'
        ));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($date, $constraint);
    }

    public function getValidInBetweenDates()
    {
        return array(
            array('2015-01-01'),
            array('2015-12-31'),
            array('2016-12-31'),
        );
    }

    /**
     * @dataProvider getTooLateInBetweenDates
     */
    public function testTooLateInBetweenDates($date)
    {
        $constraint = new Date(array(
            'messageAfterDate'  => 'myMessage',
            'messageBeforeDate' => 'myMessage',
            'after'  => '2014-12-31',
            'before' => '2017-01-01'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $date,
                '{{ before }}' => '2017-01-01',
            ));

        $this->validator->validate($date, $constraint);
    }

    public function getTooLateInBetweenDates()
    {
        return array(
            array('2017-01-01'),
            array('2020-06-06'),
        );
    }

    /**
     * @dataProvider getTooEarlyInBetweenDates
     */
    public function testTooEarlyInBetweenDates($date)
    {
        $constraint = new Date(array(
            'messageAfterDate'  => 'myMessage',
            'messageBeforeDate' => 'myMessage',
            'after'  => '2014-12-31',
            'before' => '2017-01-01'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $date,
                '{{ after }}' => '2014-12-31',
            ));

        $this->validator->validate($date, $constraint);
    }

    public function getTooEarlyInBetweenDates()
    {
        return array(
            array('2014-12-31'),
            array('2000-06-06'),
        );
    }

    /**
     * @dataProvider getDateFormatMessages
     */
    public function testDateFormatMessagesFormatter($format, $expected)
    {
        $constraint = new Date(array(
            'messageAfterDate'   => 'myMessage',
            'after'              => '2014-12-31',
            'dateFormatMessages' => $format,
        ));

        $date = '2000-01-01';

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $date,
                '{{ after }}' => $expected,
            ));

        $this->validator->validate($date, $constraint);
    }

    public function getDateFormatMessages()
    {
        return array(
            array('dd/MM/yy',   '31/12/14'),
            array('yyyy-MM-dd', '2014-12-31'),
            array('yyyyMMdd HH:mm:ss', '20141231 00:00:00'),
        );
    }
}
