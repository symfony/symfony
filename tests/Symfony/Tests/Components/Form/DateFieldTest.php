<?php

namespace Symfony\Tests\Components\Form;

require_once __DIR__ . '/DateTimeTestCase.php';

use Symfony\Components\Form\DateField;

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

    public function testRenderAsInput()
    {
        $field = new DateField('name', array('widget' => 'input'));

        $field->setLocale('de_AT');
        $field->setData(new \DateTime('2010-06-02 UTC'));

        $html = '<input id="name" name="name" value="02.06.2010" type="text" class="foobar" />';

        $this->assertEquals($html, $field->render(array(
            'class' => 'foobar',
        )));
    }

    public function testRenderAsInputWithFormat()
    {
        $field = new DateField('name', array('widget' => 'input', 'format' => 'short'));

        $field->setLocale('de_AT');
        $field->setData(new \DateTime('2010-06-02 UTC'));

        $html = '<input id="name" name="name" value="02.06.10" type="text" />';

        $this->assertEquals($html, $field->render());
    }

    public function testRenderAsChoice()
    {
        $field = new DateField('name', array(
            'years' => array(2010, 2011),
            'months' => array(6, 7),
            'days' => array(1, 2),
            'widget' => DateField::CHOICE,
        ));

        $field->setLocale('de_AT');
        $field->setData(new \DateTime('2010-06-02 UTC'));

        $html = <<<EOF
<select id="name_day" name="name[day]" class="foobar">
<option value="1">01</option>
<option value="2" selected="selected">02</option>
</select>.<select id="name_month" name="name[month]" class="foobar">
<option value="6" selected="selected">06</option>
<option value="7">07</option>
</select>.<select id="name_year" name="name[year]" class="foobar">
<option value="2010" selected="selected">2010</option>
<option value="2011">2011</option>
</select>
EOF;

        $this->assertEquals($html, $field->render(array(
            'class' => 'foobar',
        )));
    }

    public function testRenderAsChoiceNonRequired()
    {
        $field = new DateField('name', array(
            'years' => array(2010, 2011),
            'months' => array(6, 7),
            'days' => array(1, 2),
            'widget' => DateField::CHOICE,
        ));

        $field->setLocale('de_AT');
        $field->setRequired(false);

        $html = <<<EOF
<select id="name_day" name="name[day]">
<option value="" selected="selected"></option>
<option value="1">01</option>
<option value="2">02</option>
</select>.<select id="name_month" name="name[month]">
<option value="" selected="selected"></option>
<option value="6">06</option>
<option value="7">07</option>
</select>.<select id="name_year" name="name[year]">
<option value="" selected="selected"></option>
<option value="2010">2010</option>
<option value="2011">2011</option>
</select>
EOF;

        $this->assertEquals($html, $field->render());
    }

    public function testRenderAsChoiceWithPattern()
    {
        $field = new DateField('name', array(
            'years' => array(2010, 2011),
            'months' => array(6, 7),
            'days' => array(1, 2),
            'widget' => DateField::CHOICE,
            'pattern' => '%day%---%month%---%year%',
        ));

        $html = <<<EOF
<select id="name_day" name="name[day]">
<option value="1">01</option>
<option value="2">02</option>
</select>---<select id="name_month" name="name[month]">
<option value="6">06</option>
<option value="7">07</option>
</select>---<select id="name_year" name="name[year]">
<option value="2010">2010</option>
<option value="2011">2011</option>
</select>
EOF;

        $this->assertEquals($html, $field->render());
    }
}