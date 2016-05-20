<?php


namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\DateRange;
use Symfony\Component\Validator\Constraints\DateRangeValidator;
use Symfony\Component\Validator\Validation;

class DateRangeValidatorTest extends AbstractConstraintValidatorTest
{

    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_5;
    }

    protected function createValidator()
    {
        return new DateRangeValidator();
    }

    public function testBothBlank()
    {
        $constraint = new DateRange(array(
            'start' => 'startDate',
            'end' => 'endDate',
        ));
        $this->validator->validate(new TestEvent(null, null), $constraint);
        $this->assertNoViolation();
    }

    public function testStartBlank()
    {
        $constraint = new DateRange(array(
            'start' => 'startDate',
            'end' => 'endDate',
        ));
        $this->validator->validate(new TestEvent(null, new \DateTime()), $constraint);
        $this->assertNoViolation();
    }

    public function testEndBlank()
    {
        $constraint = new DateRange(array(
            'start' => 'startDate',
            'end' => 'endDate',
        ));
        $this->validator->validate(new TestEvent(new \DateTime(), null), $constraint);
        $this->assertNoViolation();
    }

    public function testEqual()
    {
        $constraint = new DateRange(array(
            'start' => 'startDate',
            'end' => 'endDate',
        ));
        $this->validator->validate(new TestEvent(new \DateTime(), new \DateTime()), $constraint);
        $this->assertNoViolation();
    }

    /**
     * @dataProvider dataTestValid
     */
    public function testValid($value)
    {
        $constraint = new DateRange(array(
            'start' => 'startDate',
            'end' => 'endDate',
        ));
        $this->validator->validate($value, $constraint);
        $this->assertNoViolation();
    }

    public function dataTestValid()
    {
        return array(
            array(
                new TestEvent(new \DateTime('2014-01-01'), new \DateTime('2014-01-02')),
            ),
            array(
                new TestEvent(new \DateTime('2014-01-01 00:00:00'), new \DateTime('2014-01-01 00:30:00')),
            )
        );
    }

    /**
     * @param TestEvent $value
     * @dataProvider dataTestInvalid
     */
    public function testInvalid($value)
    {
        $constraint = new DateRange(array(
            'start' => 'startDate',
            'end' => 'endDate',
        ));
        $this->validator->validate($value, $constraint);
        $this->assertViolation(
             'Invalid date range',
             array(
                 '{{ start }}' => $value->getStartDate()->format('Y-m-d'),
                 '{{ end }}' => $value->getEndDate()->format('Y-m-d'),
             ),
             'property.path',
             $value
        );
    }

    public function dataTestInvalid()
    {
        return array(
            array(
                new TestEvent(new \DateTime('2014-01-01'), new \DateTime('2013-12-31')),
            ),
            array(
                new TestEvent(new \DateTime('2014-01-01 00:30:00'), new \DateTime('2014-01-01 00:00:00')),
            )
        );
    }

    public function testErrorPathOnStart()
    {
        $constraint = new DateRange(array(
            'start' => 'startDate',
            'end' => 'endDate',
            'errorPath' => 'startDate',
        ));
        $value = new TestEvent(new \DateTime(), new \DateTime('-1 day'));
        $this->validator->validate($value, $constraint);
        $this->assertViolation(
             'Start date should be earlier than or equal to {{ limit }}',
             array(
                 '{{ limit }}' => $value->getEndDate()->format('Y-m-d'),
             ),
             'property.path.startDate',
             $value
        );
    }

    public function testErrorPathOnEnd()
    {
        $constraint = new DateRange(array(
            'start' => 'startDate',
            'end' => 'endDate',
            'errorPath' => 'endDate',
        ));
        $value = new TestEvent(new \DateTime(), new \DateTime('-1 day'));
        $this->validator->validate($value, $constraint);
        $this->assertViolation(
             'End date should be later than or equal to {{ limit }}',
             array(
                 '{{ limit }}' => $value->getStartDate()->format('Y-m-d'),
             ),
             'property.path.endDate',
             $value
        );
    }

    /**
     * @param $value
     * @param $interval
     *
     * @dataProvider dateMinIntervalViolations
     */
    public function testMinIntervalViolations($value, $interval)
    {
        $constraint = new DateRange(array(
            'start' => 'startDate',
            'end' => 'endDate',
            'min' => $interval
        ));
        $this->validator->validate($value, $constraint);
        $this->assertViolation(
             'Dates must be {{ interval }} apart',
             array(
                 '{{ interval }}' => $interval,
             ),
             'property.path',
             $value
        );
    }

    public function dateMinIntervalViolations()
    {
        return array(
            array(
                new TestEvent(new \DateTime(), new \DateTime('+1 hour')),
                '1 day',
            ),
            array(
                new TestEvent(new \DateTime(), new \DateTime('+1 week')),
                '1 month',
            ),
            array(
                new TestEvent(new \DateTime('2014-02-01'), new \DateTime('2014-02-27')),
                '1 month',
            ),
            array(
                new TestEvent(new \DateTime('-30 seconds'), new \DateTime()),
                '1 minute',
            ),
            array(
                new TestEvent(new \DateTime(), new \DateTime('+1 month 2 days')),
                '1 month + 10 days',
            ),
        );
    }

    /**
     * @param $value
     * @param $interval
     *
     * @dataProvider dateMinIntervalValid
     */
    public function testMinIntervalsValid($value, $interval)
    {
        $constraint = new DateRange(array(
            'start' => 'startDate',
            'end' => 'endDate',
            'min' => $interval
        ));
        $this->validator->validate($value, $constraint);
        $this->assertNoViolation();
    }

    public function dateMinIntervalValid()
    {
        return array(
            array(
                new TestEvent(new \DateTime(), new \DateTime('+1 day')),
                '1 day',
            ),
            array(
                new TestEvent(new \DateTime(), new \DateTime('+1 week')),
                '5 days',
            ),
            array(
                new TestEvent(new \DateTime('2014-02-01'), new \DateTime('2014-03-01')),
                '1 month',
            ),
        );
    }

    /**
     * @param $value
     * @param $interval
     *
     * @dataProvider dateMaxIntervalViolations
     */
    public function testMaxIntervalViolations($value, $interval)
    {
        $constraint = new DateRange(array(
            'start' => 'startDate',
            'end' => 'endDate',
            'max' => $interval
        ));
        $this->validator->validate($value, $constraint);
        $this->assertViolation(
             'Dates must be {{ interval }} apart',
                 array(
                     '{{ interval }}' => $interval,
                 ),
                 'property.path',
                 $value
        );
    }

    public function dateMaxIntervalViolations()
    {
        return array(
            array(
                new TestEvent(new \DateTime(), new \DateTime('+1 day 1 seconds')),
                '1 day',
            ),
            array(
                new TestEvent(new \DateTime(), new \DateTime('+1 year')),
                '1 month',
            ),
            array(
                new TestEvent(new \DateTime('2014-12-01'), new \DateTime('2015-01-02')),
                '1 month',
            ),
        );
    }

    /**
     * @param $value
     * @param $interval
     *
     * @dataProvider dateMaxIntervalValid
     */
    public function testMaxIntervalsValid($value, $interval)
    {
        $constraint = new DateRange(array(
            'start' => 'startDate',
            'end' => 'endDate',
            'max' => $interval
        ));
        $this->validator->validate($value, $constraint);
        $this->assertNoViolation();
    }

    public function dateMaxIntervalValid()
    {
        return array(
            array(
                new TestEvent(new \DateTime(), new \DateTime('+1 day')),
                '1 day',
            ),
            array(
                new TestEvent(new \DateTime(), new \DateTime('+3 days')),
                '5 days',
            ),
            array(
                new TestEvent(new \DateTime('2014-02-01'), new \DateTime('2014-03-01')),
                '1 month',
            ),
        );
    }

}

class TestEvent
{
    protected $startDate;

    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function getStartDate()
    {
        return $this->startDate;
    }

    public function getEndDate()
    {
        return $this->endDate;
    }
}
