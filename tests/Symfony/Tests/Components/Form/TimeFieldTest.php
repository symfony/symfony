<?php

namespace Symfony\Tests\Components\Form;

require_once __DIR__ . '/DateTimeTestCase.php';

use Symfony\Components\Form\TimeField;

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

    public function testRenderAsInputs()
    {
        $field = new TimeField('name', array(
            'widget' => TimeField::INPUT,
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
        ));

        $field->setData(new \DateTime('04:05 UTC'));

        $html = <<<EOF
<input id="name_hour" name="name[hour]" value="04" type="text" maxlength="2" size="1" class="foobar" />
:
<input id="name_minute" name="name[minute]" value="05" type="text" maxlength="2" size="1" class="foobar" />
EOF;

        $this->assertEquals(str_replace("\n", '', $html), $field->render(array('class' => 'foobar')));
    }

    public function testRenderAsInputs_withSeconds()
    {
        $field = new TimeField('name', array(
            'widget' => TimeField::INPUT,
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'with_seconds' => true,
        ));

        $field->setData(new \DateTime('04:05:06 UTC'));

        $html = <<<EOF
<input id="name_hour" name="name[hour]" value="04" type="text" maxlength="2" size="1" class="foobar" />
:
<input id="name_minute" name="name[minute]" value="05" type="text" maxlength="2" size="1" class="foobar" />
:
<input id="name_second" name="name[second]" value="06" type="text" maxlength="2" size="1" class="foobar" />
EOF;

        $this->assertEquals(str_replace("\n", '', $html), $field->render(array('class' => 'foobar')));
    }

    public function testRenderAsChoices()
    {
        $field = new TimeField('name', array(
            'hours' => array(3, 4),
            'minutes' => array(5, 6),
            'widget' => TimeField::CHOICE,
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
        ));

        $field->setData(new \DateTime('04:05 UTC'));

        $html = <<<EOF
<select id="name_hour" name="name[hour]" class="foobar">
<option value="3">03</option>
<option value="4" selected="selected">04</option>
</select>:<select id="name_minute" name="name[minute]" class="foobar">
<option value="5" selected="selected">05</option>
<option value="6">06</option>
</select>
EOF;

        $this->assertEquals($html, $field->render(array('class' => 'foobar')));
    }

    public function testRenderAsChoices_withSeconds()
    {
        $field = new TimeField('name', array(
            'hours' => array(3, 4),
            'minutes' => array(5, 6),
            'seconds' => array(7, 8),
            'widget' => TimeField::CHOICE,
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'with_seconds' => true,
        ));

        $field->setData(new \DateTime('04:05:07 UTC'));

        $html = <<<EOF
<select id="name_hour" name="name[hour]" class="foobar">
<option value="3">03</option>
<option value="4" selected="selected">04</option>
</select>:<select id="name_minute" name="name[minute]" class="foobar">
<option value="5" selected="selected">05</option>
<option value="6">06</option>
</select>:<select id="name_second" name="name[second]" class="foobar">
<option value="7" selected="selected">07</option>
<option value="8">08</option>
</select>
EOF;

        $this->assertEquals($html, $field->render(array('class' => 'foobar')));
    }

    public function testRenderAsChoices_nonRequired()
    {
        $field = new TimeField('name', array(
            'hours' => array(3, 4),
            'minutes' => array(5, 6),
            'widget' => TimeField::CHOICE,
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
        ));

        $field->setRequired(false);
        $field->setData(new \DateTime('04:05 UTC'));

        $html = <<<EOF
<select id="name_hour" name="name[hour]" class="foobar">
<option value=""></option>
<option value="3">03</option>
<option value="4" selected="selected">04</option>
</select>:<select id="name_minute" name="name[minute]" class="foobar">
<option value=""></option>
<option value="5" selected="selected">05</option>
<option value="6">06</option>
</select>
EOF;

        $this->assertEquals($html, $field->render(array('class' => 'foobar')));
    }
}