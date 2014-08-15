<?php


namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\DateRange;
use Symfony\Component\Validator\Constraints\DateRangeValidator;
use Symfony\Component\Validator\Validation;

class DateRangeValidatorTest extends AbstractConstraintValidatorTest
{

    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_4;
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
             )
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
             'Start date should be less than or equal to {{ limit }}',
             array(
                 '{{ limit }}' => $value->getEndDate()->format('Y-m-d'),
             ),
             'property.path.startDate',
             null
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
             'End date should be greater than or equal to {{ limit }}',
             array(
                 '{{ limit }}' => $value->getStartDate()->format('Y-m-d'),
             ),
             'property.path.endDate',
             null
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
