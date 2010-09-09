<?php

namespace Symfony\Tests\Component\Form;

require_once __DIR__ . '/DateTimeTestCase.php';

use Symfony\Component\Form\TimeField;

class TimeFieldTest extends DateTimeTestCase
{
    public function testBind_dateTime()
    {
        $field = new TimeField('name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'type' => TimeField::DATETIME,
        ));

        $input = array(
            'hour' => '3',
            'minute' => '4',
        );

        $field->bind($input);

        $dateTime = new \DateTime('1970-01-01 03:04:00 UTC');

        $this->assertEquals($dateTime, $field->getData());
        $this->assertEquals($input, $field->getDisplayedData());
    }

    public function testBind_string()
    {
        $field = new TimeField('name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'type' => TimeField::STRING,
        ));

        $input = array(
            'hour' => '3',
            'minute' => '4',
        );

        $field->bind($input);

        $this->assertEquals('03:04:00', $field->getData());
        $this->assertEquals($input, $field->getDisplayedData());
    }

    public function testBind_timestamp()
    {
        $field = new TimeField('name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'type' => TimeField::TIMESTAMP,
        ));

        $input = array(
            'hour' => '3',
            'minute' => '4',
        );

        $field->bind($input);

        $dateTime = new \DateTime('1970-01-01 03:04:00 UTC');

        $this->assertEquals($dateTime->format('U'), $field->getData());
        $this->assertEquals($input, $field->getDisplayedData());
    }

    public function testBind_raw()
    {
        $field = new TimeField('name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'type' => TimeField::RAW,
        ));

        $input = array(
            'hour' => '3',
            'minute' => '4',
        );

        $data = array(
            'hour' => '3',
            'minute' => '4',
            'second' => '0',
        );

        $field->bind($input);

        $this->assertEquals($data, $field->getData());
        $this->assertEquals($input, $field->getDisplayedData());
    }

    public function testSetData_withSeconds()
    {
        $field = new TimeField('name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'type' => TimeField::DATETIME,
            'with_seconds' => true,
        ));

        $field->setData(new \DateTime('03:04:05 UTC'));

        $this->assertEquals(array('hour' => 3, 'minute' => 4, 'second' => 5), $field->getDisplayedData());
    }

    public function testSetData_differentTimezones()
    {
        $field = new TimeField('name', array(
            'data_timezone' => 'America/New_York',
            'user_timezone' => 'Pacific/Tahiti',
            // don't do this test with DateTime, because it leads to wrong results!
            'type' => TimeField::STRING,
            'with_seconds' => true,
        ));

        $dateTime = new \DateTime('03:04:05 America/New_York');

        $field->setData($dateTime->format('H:i:s'));

        $dateTime = clone $dateTime;
        $dateTime->setTimezone(new \DateTimeZone('Pacific/Tahiti'));

        $displayedData = array(
            'hour' => (int)$dateTime->format('H'),
            'minute' => (int)$dateTime->format('i'),
            'second' => (int)$dateTime->format('s')
        );

        $this->assertEquals($displayedData, $field->getDisplayedData());
    }
}