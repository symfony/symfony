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

use Symfony\Component\Validator\Constraints\DateTimeRange;
use Symfony\Component\Validator\Constraints\DateTimeRangeValidator;

class DateTimeRangeValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    /** @var DateTimeRangeValidator */
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new DateTimeRangeValidator();
        $this->validator->initialize($this->context);
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new DateTimeRange(array('min' => new \DateTime(), 'max' => new \DateTime())));
    }

    public function getJanuaryTenToJanuaryTwenty()
    {
        return array(
            array(new \DateTime("2013-01-10T00:00:00Z")),
            array(new \DateTime("2013-01-11T00:00:00Z")),
            array(new \DateTime("2013-01-12T00:00:00Z")),
            array(new \DateTime("2013-01-13T00:00:00Z")),
            array(new \DateTime("2013-01-14T00:00:00Z")),
            array(new \DateTime("2013-01-15T00:00:00Z")),
            array(new \DateTime("2013-01-16T00:00:00Z")),
            array(new \DateTime("2013-01-17T00:00:00Z")),
            array(new \DateTime("2013-01-18T00:00:00Z")),
            array(new \DateTime("2013-01-19T00:00:00Z")),
            array(new \DateTime("2013-01-20T00:00:00Z")),
            array("2013-01-10"),
            array("2013-01-11"),
            array("2013-01-12"),
            array("2013-01-13"),
            array("2013-01-14"),
            array("2013-01-15"),
            array("2013-01-16"),
            array("2013-01-17"),
            array("2013-01-18"),
            array("2013-01-19"),
            array("2013-01-20"),
            array("2013-01-10 00:00:00"),
            array("2013-01-11 00:00:00"),
            array("2013-01-12 00:00:00"),
            array("2013-01-13 00:00:00"),
            array("2013-01-14 00:00:00"),
            array("2013-01-15 00:00:00"),
            array("2013-01-16 00:00:00"),
            array("2013-01-17 00:00:00"),
            array("2013-01-18 00:00:00"),
            array("2013-01-19 00:00:00"),
            array("2013-01-20 00:00:00"),
        );
    }

    public function getBeforeJanuaryTen()
    {
        return array(
            array(new \DateTime("2012-12-30T00:00:00Z")),
            array(new \DateTime("2012-12-31T00:00:00Z")),
            array(new \DateTime("2013-01-01T00:00:00Z")),
            array(new \DateTime("2013-01-02T00:00:00Z")),
            array(new \DateTime("2013-01-03T00:00:00Z")),
            array(new \DateTime("2013-01-04T00:00:00Z")),
            array(new \DateTime("2013-01-05T00:00:00Z")),
            array(new \DateTime("2013-01-06T00:00:00Z")),
            array(new \DateTime("2013-01-07T00:00:00Z")),
            array(new \DateTime("2013-01-08T00:00:00Z")),
            array(new \DateTime("2013-01-09T00:00:00Z")),
            array(new \DateTime("2013-01-09T23:59:59Z")),
            array("2012-12-30"),
            array("2012-12-31"),
            array("2013-01-01"),
            array("2013-01-02"),
            array("2013-01-03"),
            array("2013-01-04"),
            array("2013-01-05"),
            array("2013-01-06"),
            array("2013-01-07"),
            array("2013-01-08"),
            array("2013-01-09"),
            array("2012-12-30"),
            array("2012-12-31"),
            array("2013-01-01 00:00:00"),
            array("2013-01-02 00:00:00"),
            array("2013-01-03 00:00:00"),
            array("2013-01-04 00:00:00"),
            array("2013-01-05 00:00:00"),
            array("2013-01-06 00:00:00"),
            array("2013-01-07 00:00:00"),
            array("2013-01-08 00:00:00"),
            array("2013-01-09 00:00:00"),
            array("2013-01-09 23:59:59"),
        );
    }

    public function getAfterJanuaryTwenty()
    {
        return array(
            array(new \DateTime("2013-01-20T00:00:01Z")),
            array(new \DateTime("2013-01-21T00:00:00Z")),
            array(new \DateTime("2013-01-22T00:00:00Z")),
            array(new \DateTime("2013-01-23T00:00:00Z")),
            array(new \DateTime("2013-01-24T00:00:00Z")),
            array(new \DateTime("2013-01-25T00:00:00Z")),
            array(new \DateTime("2013-01-26T00:00:00Z")),
            array(new \DateTime("2013-01-27T00:00:00Z")),
            array(new \DateTime("2013-01-28T00:00:00Z")),
            array(new \DateTime("2013-01-29T00:00:00Z")),
            array(new \DateTime("2013-01-30T00:00:00Z")),
            array("2013-01-21"),
            array("2013-01-22"),
            array("2013-01-23"),
            array("2013-01-24"),
            array("2013-01-25"),
            array("2013-01-26"),
            array("2013-01-27"),
            array("2013-01-28"),
            array("2013-01-29"),
            array("2013-01-30"),
            array("2013-01-20 00:00:01"),
            array("2013-01-21 00:00:00"),
            array("2013-01-22 00:00:00"),
            array("2013-01-23 00:00:00"),
            array("2013-01-24 00:00:00"),
            array("2013-01-25 00:00:00"),
            array("2013-01-26 00:00:00"),
            array("2013-01-27 00:00:00"),
            array("2013-01-28 00:00:00"),
            array("2013-01-29 00:00:00"),
            array("2013-01-30 00:00:00"),
        );
    }

    public function getInvalidValues()
    {
        return array(
            array('Invalid'),
            array('2013-01-30 24:00:00'),
            array(11234123),
        );
    }

    public function getUnexpectedTypes()
    {
        return array(
            array(new \stdClass()),
            array(array()),
        );
    }

    /**
     * @dataProvider getJanuaryTenToJanuaryTwenty
     */
    public function testValidValuesMinDateTime($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new DateTimeRange(array('min' => new \DateTime("2013-01-10T00:00:00Z")));
        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getJanuaryTenToJanuaryTwenty
     */
    public function testValidValuesMaxDateTime($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new DateTimeRange(array('max' => new \DateTime("2013-01-20T00:00:00Z")));
        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getJanuaryTenToJanuaryTwenty
     */
    public function testValidValuesMinMaxDateTime($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new DateTimeRange(array('min' => new \DateTime("2013-01-10T00:00:00Z"), 'max' => new \DateTime("2013-01-20T00:00:00Z")));
        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getJanuaryTenToJanuaryTwenty
     */
    public function testValidValuesMinString($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new DateTimeRange(array('min' => "2013-01-10T00:00:00Z"));
        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getJanuaryTenToJanuaryTwenty
     */
    public function testValidValuesMaxString($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new DateTimeRange(array('max' => "2013-01-20T00:00:00Z"));
        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getJanuaryTenToJanuaryTwenty
     */
    public function testValidValuesMinMaxString($value)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new DateTimeRange(array('min' => "2013-01-10T00:00:00Z", 'max' => "2013-01-20T00:00:00Z"));
        $this->validator->validate($value, $constraint);
    }


    /**
     * @dataProvider getBeforeJanuaryTen
     */
    public function testInvalidValuesMin($value)
    {
        $constraint = new DateTimeRange(array(
            'min' => new \DateTime("2013-01-10T00:00:00Z"),
            'minMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', $this->equalTo(array(
                    '{{ value }}' => $value,
                    '{{ limit }}' => new \DateTime("2013-01-10T00:00:00Z"),
                )));

        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getAfterJanuaryTwenty
     */
    public function testInvalidValuesMax($value)
    {
        $constraint = new DateTimeRange(array(
            'max' => new \DateTime("2013-01-20T00:00:00Z"),
            'maxMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', $this->equalTo(array(
                        '{{ value }}' => $value,
                        '{{ limit }}' => new \DateTime("2013-01-20T00:00:00Z"),
                    )));

        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getBeforeJanuaryTen
     */
    public function testInvalidValuesCombinedMin($value)
    {
        $constraint = new DateTimeRange(array(
            'min' => new \DateTime("2013-01-10T00:00:00Z"),
            'max' => new \DateTime("2013-01-20T00:00:00Z"),
            'minMessage' => 'myMinMessage',
            'maxMessage' => 'myMaxMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMinMessage', $this->equalTo(array(
                        '{{ value }}' => $value,
                        '{{ limit }}' => new \DateTime("2013-01-10T00:00:00Z"),
                    )));

        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testCompletelyInvalidValues($value)
    {
        $constraint = new DateTimeRange(array(
            'min' => new \DateTime("2013-01-10T00:00:00Z"),
            'max' => new \DateTime("2013-01-20T00:00:00Z"),
            'minMessage' => 'myMinMessage',
            'maxMessage' => 'myMaxMessage',
            'invalidMessage' => 'myInvalidMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myInvalidMessage', $this->equalTo(array(
                        '{{ value }}' => $value,
                    )));

        $this->validator->validate($value, $constraint);
    }

    /**
     * @dataProvider getUnexpectedTypes
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testUnexpectedType($value)
    {
        $constraint = new DateTimeRange(array(
            'min' => new \DateTime("2013-01-10T00:00:00Z"),
            'max' => new \DateTime("2013-01-20T00:00:00Z"),
            'minMessage' => 'myMinMessage',
            'maxMessage' => 'myMaxMessage',
            'invalidMessage' => 'myInvalidMessage'
        ));
        $this->validator->validate($value, $constraint);
    }
}
