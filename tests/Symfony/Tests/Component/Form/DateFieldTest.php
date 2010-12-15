<?php

namespace Symfony\Tests\Component\Form;

require_once __DIR__ . '/DateTimeTestCase.php';

use Symfony\Component\Form\DateField;
use Symfony\Component\Form\FormConfiguration;

class DateFieldTest extends DateTimeTestCase
{
    protected function setUp()
    {
        FormConfiguration::setDefaultLocale('de_AT');
    }

    public function testBind_fromInput_dateTime()
    {
        $field = new DateField('name', array('widget' => 'input', 'type' => DateField::DATETIME));

        $field->bind('2.6.2010');

        $this->assertDateTimeEquals(new \DateTime('2010-06-02 UTC'), $field->getData());
        $this->assertEquals('02.06.2010', $field->getDisplayedData());
    }

    public function testBind_fromInput_string()
    {
        $field = new DateField('name', array('widget' => 'input', 'type' => DateField::STRING));

        $field->bind('2.6.2010');

        $this->assertEquals('2010-06-02', $field->getData());
        $this->assertEquals('02.06.2010', $field->getDisplayedData());
    }

    public function testBind_fromInput_timestamp()
    {
        $field = new DateField('name', array('widget' => 'input', 'type' => DateField::TIMESTAMP));

        $field->bind('2.6.2010');

        $dateTime = new \DateTime('2010-06-02 UTC');

        $this->assertEquals($dateTime->format('U'), $field->getData());
        $this->assertEquals('02.06.2010', $field->getDisplayedData());
    }

    public function testBind_fromInput_raw()
    {
        $field = new DateField('name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'input',
            'type' => DateField::RAW,
        ));

        $field->bind('2.6.2010');

        $output = array(
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        );

        $this->assertEquals($output, $field->getData());
        $this->assertEquals('02.06.2010', $field->getDisplayedData());
    }

    public function testBind_fromChoice()
    {
        $field = new DateField('name', array('widget' => DateField::CHOICE));

        $input = array(
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        );

        $field->bind($input);

        $dateTime = new \DateTime('2010-06-02 UTC');

        $this->assertDateTimeEquals($dateTime, $field->getData());
        $this->assertEquals($input, $field->getDisplayedData());
    }

    public function testBind_fromChoice_empty()
    {
        $field = new DateField('name', array('widget' => DateField::CHOICE, 'required' => false));

        $input = array(
            'day' => '',
            'month' => '',
            'year' => '',
        );

        $field->bind($input);

        $this->assertSame(null, $field->getData());
        $this->assertEquals($input, $field->getDisplayedData());
    }

    public function testSetData_differentTimezones()
    {
        $field = new DateField('name', array(
            'data_timezone' => 'America/New_York',
            'user_timezone' => 'Pacific/Tahiti',
            // don't do this test with DateTime, because it leads to wrong results!
            'type' => DateField::STRING,
            'widget' => 'input',
        ));

        $field->setData('2010-06-02');

        $this->assertEquals('01.06.2010', $field->getDisplayedData());
    }

    public function testIsYearWithinRange_returnsTrueIfWithin()
    {
        $field = new DateField('name', array(
            'widget' => 'input',
            'years' => array(2010, 2011),
        ));

        $field->bind('2.6.2010');

        $this->assertTrue($field->isYearWithinRange());
    }

    public function testIsYearWithinRange_returnsTrueIfEmpty()
    {
        $field = new DateField('name', array(
            'widget' => 'input',
            'years' => array(2010, 2011),
        ));

        $field->bind('');

        $this->assertTrue($field->isYearWithinRange());
    }

    public function testIsYearWithinRange_returnsFalseIfNotContained()
    {
        $field = new DateField('name', array(
            'widget' => 'input',
            'years' => array(2010, 2012),
        ));

        $field->bind('2.6.2011');

        $this->assertFalse($field->isYearWithinRange());
    }

    public function testIsMonthWithinRange_returnsTrueIfWithin()
    {
        $field = new DateField('name', array(
            'widget' => 'input',
            'months' => array(6, 7),
        ));

        $field->bind('2.6.2010');

        $this->assertTrue($field->isMonthWithinRange());
    }

    public function testIsMonthWithinRange_returnsTrueIfEmpty()
    {
        $field = new DateField('name', array(
            'widget' => 'input',
            'months' => array(6, 7),
        ));

        $field->bind('');

        $this->assertTrue($field->isMonthWithinRange());
    }

    public function testIsMonthWithinRange_returnsFalseIfNotContained()
    {
        $field = new DateField('name', array(
            'widget' => 'input',
            'months' => array(6, 8),
        ));

        $field->bind('2.7.2010');

        $this->assertFalse($field->isMonthWithinRange());
    }

    public function testIsDayWithinRange_returnsTrueIfWithin()
    {
        $field = new DateField('name', array(
            'widget' => 'input',
            'days' => array(6, 7),
        ));

        $field->bind('6.6.2010');

        $this->assertTrue($field->isDayWithinRange());
    }

    public function testIsDayWithinRange_returnsTrueIfEmpty()
    {
        $field = new DateField('name', array(
            'widget' => 'input',
            'days' => array(6, 7),
        ));

        $field->bind('');

        $this->assertTrue($field->isDayWithinRange());
    }

    public function testIsDayWithinRange_returnsFalseIfNotContained()
    {
        $field = new DateField('name', array(
            'widget' => 'input',
            'days' => array(6, 8),
        ));

        $field->bind('7.6.2010');

        $this->assertFalse($field->isDayWithinRange());
    }
}