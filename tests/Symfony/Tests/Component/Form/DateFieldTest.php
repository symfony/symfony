<?php

namespace Symfony\Tests\Component\Form;

require_once __DIR__ . '/DateTimeTestCase.php';

use Symfony\Component\Form\DateField;

class DateFieldTest extends DateTimeTestCase
{
    public function testBind_fromInput_dateTime()
    {
        $field = new DateField('name', array('widget' => 'input', 'type' => DateField::DATETIME));

        $field->setLocale('de_AT');
        $field->bind('2.6.2010');

        $this->assertDateTimeEquals(new \DateTime('2010-06-02 UTC'), $field->getData());
        $this->assertEquals('02.06.2010', $field->getDisplayedData());
    }

    public function testBind_fromInput_string()
    {
        $field = new DateField('name', array('widget' => 'input', 'type' => DateField::STRING));

        $field->setLocale('de_AT');
        $field->bind('2.6.2010');

        $this->assertEquals('2010-06-02', $field->getData());
        $this->assertEquals('02.06.2010', $field->getDisplayedData());
    }

    public function testBind_fromInput_timestamp()
    {
        $field = new DateField('name', array('widget' => 'input', 'type' => DateField::TIMESTAMP));

        $field->setLocale('de_AT');
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

        $field->setLocale('de_AT');
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

        $field->setLocale('de_AT');
        $field->bind($input);

        $dateTime = new \DateTime('2010-06-02 UTC');

        $this->assertDateTimeEquals($dateTime, $field->getData());
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

        $field->setLocale('de_AT');
        $field->setData('2010-06-02');

        $this->assertEquals('01.06.2010', $field->getDisplayedData());
    }
}