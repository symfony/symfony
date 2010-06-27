<?php

namespace Symfony\Tests\Components\Form;

require_once __DIR__ . '/DateTimeTestCase.php';

use Symfony\Components\Form\DateTimeField;
use Symfony\Components\Form\DateField;
use Symfony\Components\Form\TimeField;

class DateTimeFieldTest extends DateTimeTestCase
{
    public function testBind_dateTime()
    {
        $field = new DateTimeField('name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'date_widget' => DateField::CHOICE,
            'time_widget' => TimeField::CHOICE,
            'type' => DateTimeField::DATETIME,
        ));

        $field->bind(array(
            'date' => array(
                'day' => '2',
                'month' => '6',
                'year' => '2010',
            ),
            'time' => array(
                'hour' => '3',
                'minute' => '4',
            ),
        ));

        $dateTime = new \DateTime('2010-06-02 03:04:00 UTC');

        $this->assertDateTimeEquals($dateTime, $field->getData());
    }

    public function testBind_string()
    {
        $field = new DateTimeField('name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'type' => DateTimeField::STRING,
            'date_widget' => DateField::CHOICE,
            'time_widget' => TimeField::CHOICE,
        ));

        $field->bind(array(
            'date' => array(
                'day' => '2',
                'month' => '6',
                'year' => '2010',
            ),
            'time' => array(
                'hour' => '3',
                'minute' => '4',
            ),
        ));

        $this->assertEquals('2010-06-02 03:04:00', $field->getData());
    }

    public function testBind_timestamp()
    {
        $field = new DateTimeField('name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'type' => DateTimeField::TIMESTAMP,
            'date_widget' => DateField::CHOICE,
            'time_widget' => TimeField::CHOICE,
        ));

        $field->bind(array(
            'date' => array(
                'day' => '2',
                'month' => '6',
                'year' => '2010',
            ),
            'time' => array(
                'hour' => '3',
                'minute' => '4',
            ),
        ));

        $dateTime = new \DateTime('2010-06-02 03:04:00 UTC');

        $this->assertEquals($dateTime->format('U'), $field->getData());
    }

    public function testBind_withSeconds()
    {
        $field = new DateTimeField('name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'date_widget' => DateField::CHOICE,
            'time_widget' => TimeField::CHOICE,
            'type' => DateTimeField::DATETIME,
            'with_seconds' => true,
        ));

        $field->setData(new \DateTime('2010-06-02 03:04:05 UTC'));

        $input = array(
            'date' => array(
                'day' => '2',
                'month' => '6',
                'year' => '2010',
            ),
            'time' => array(
                'hour' => '3',
                'minute' => '4',
                'second' => '5',
            ),
        );

        $field->bind($input);

        $this->assertDateTimeEquals(new \DateTime('2010-06-02 03:04:05 UTC'), $field->getData());
    }

    public function testBind_differentTimezones()
    {
        $field = new DateTimeField('name', array(
            'data_timezone' => 'America/New_York',
            'user_timezone' => 'Pacific/Tahiti',
            'date_widget' => DateField::CHOICE,
            'time_widget' => TimeField::CHOICE,
            // don't do this test with DateTime, because it leads to wrong results!
            'type' => DateTimeField::STRING,
            'with_seconds' => true,
        ));

        $dateTime = new \DateTime('2010-06-02 03:04:05 Pacific/Tahiti');

        $field->bind(array(
            'date' => array(
                'day' => (int)$dateTime->format('d'),
                'month' => (int)$dateTime->format('m'),
                'year' => (int)$dateTime->format('Y'),
            ),
            'time' => array(
                'hour' => (int)$dateTime->format('H'),
                'minute' => (int)$dateTime->format('i'),
                'second' => (int)$dateTime->format('s'),
            ),
        ));

        $dateTime->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertEquals($dateTime->format('Y-m-d H:i:s'), $field->getData());
    }

    public function testRender()
    {
        $field = new DateTimeField('name', array(
            'years' => array(2010, 2011),
            'months' => array(6, 7),
            'days' => array(1, 2),
            'hours' => array(3, 4),
            'minutes' => array(5, 6),
            'seconds' => array(7, 8),
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'date_widget' => DateField::CHOICE,
            'time_widget' => TimeField::CHOICE,
            'type' => DateTimeField::DATETIME,
            'with_seconds' => true,
        ));

        $field->setData(new \DateTime('2010-06-02 03:04:05 UTC'));

        $html = <<<EOF
<select id="name_date_day" name="name[date][day]" class="foobar">
<option value="1">01</option>
<option value="2" selected="selected">02</option>
</select>.<select id="name_date_month" name="name[date][month]" class="foobar">
<option value="6" selected="selected">06</option>
<option value="7">07</option>
</select>.<select id="name_date_year" name="name[date][year]" class="foobar">
<option value="2010" selected="selected">2010</option>
<option value="2011">2011</option>
</select>
<select id="name_time_hour" name="name[time][hour]" class="foobar">
<option value="3" selected="selected">03</option>
<option value="4">04</option>
</select>:<select id="name_time_minute" name="name[time][minute]" class="foobar">
<option value="5">05</option>
<option value="6">06</option>
</select>:<select id="name_time_second" name="name[time][second]" class="foobar">
<option value="7">07</option>
<option value="8">08</option>
</select>
EOF;

	$this->assertEquals($html, $field->render(array('class' => 'foobar')));
    }
}