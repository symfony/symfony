<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

require_once __DIR__ . '/DateTimeTestCase.php';

use Symfony\Component\Form\TimeField;

class TimeFieldTest extends DateTimeTestCase
{
    public function testSubmit_dateTime()
    {
        $field = $this->factory->getInstance('time', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'type' => 'datetime',
        ));

        $input = array(
            'hour' => '3',
            'minute' => '4',
        );

        $field->submit($input);

        $dateTime = new \DateTime('1970-01-01 03:04:00 UTC');

        $this->assertEquals($dateTime, $field->getData());
        $this->assertEquals($input, $field->getDisplayedData());
    }

    public function testSubmit_string()
    {
        $field = $this->factory->getInstance('time', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'type' => 'string',
        ));

        $input = array(
            'hour' => '3',
            'minute' => '4',
        );

        $field->submit($input);

        $this->assertEquals('03:04:00', $field->getData());
        $this->assertEquals($input, $field->getDisplayedData());
    }

    public function testSubmit_timestamp()
    {
        $field = $this->factory->getInstance('time', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'type' => 'timestamp',
        ));

        $input = array(
            'hour' => '3',
            'minute' => '4',
        );

        $field->submit($input);

        $dateTime = new \DateTime('1970-01-01 03:04:00 UTC');

        $this->assertEquals($dateTime->format('U'), $field->getData());
        $this->assertEquals($input, $field->getDisplayedData());
    }

    public function testSubmit_array()
    {
        $field = $this->factory->getInstance('time', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'type' => 'array',
        ));

        $input = array(
            'hour' => '3',
            'minute' => '4',
        );

        $data = array(
            'hour' => '3',
            'minute' => '4',
        );

        $field->submit($input);

        $this->assertEquals($data, $field->getData());
        $this->assertEquals($input, $field->getDisplayedData());
    }

    public function testSetData_withSeconds()
    {
        $field = $this->factory->getInstance('time', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'type' => 'datetime',
            'with_seconds' => true,
        ));

        $field->setData(new \DateTime('03:04:05 UTC'));

        $this->assertEquals(array('hour' => 3, 'minute' => 4, 'second' => 5), $field->getDisplayedData());
    }

    public function testSetData_differentTimezones()
    {
        $field = $this->factory->getInstance('time', 'name', array(
            'data_timezone' => 'America/New_York',
            'user_timezone' => 'Pacific/Tahiti',
            // don't do this test with DateTime, because it leads to wrong results!
            'type' => 'string',
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

    public function testIsHourWithinRange_returnsTrueIfWithin()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'hours' => array(6, 7),
        ));

        $field->submit(array('hour' => '06', 'minute' => '12'));

        $this->assertTrue($field->isHourWithinRange());
    }

    public function testIsHourWithinRange_returnsTrueIfEmpty()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'hours' => array(6, 7),
        ));

        $field->submit(array('hour' => '', 'minute' => '06'));

        $this->assertTrue($field->isHourWithinRange());
    }

    public function testIsHourWithinRange_returnsFalseIfNotContained()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'hours' => array(6, 7),
        ));

        $field->submit(array('hour' => '08', 'minute' => '12'));

        $this->assertFalse($field->isHourWithinRange());
    }

    public function testIsMinuteWithinRange_returnsTrueIfWithin()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'minutes' => array(6, 7),
        ));

        $field->submit(array('hour' => '06', 'minute' => '06'));

        $this->assertTrue($field->isMinuteWithinRange());
    }

    public function testIsMinuteWithinRange_returnsTrueIfEmpty()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'minutes' => array(6, 7),
        ));

        $field->submit(array('hour' => '06', 'minute' => ''));

        $this->assertTrue($field->isMinuteWithinRange());
    }

    public function testIsMinuteWithinRange_returnsFalseIfNotContained()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'minutes' => array(6, 7),
        ));

        $field->submit(array('hour' => '06', 'minute' => '08'));

        $this->assertFalse($field->isMinuteWithinRange());
    }

    public function testIsSecondWithinRange_returnsTrueIfWithin()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'seconds' => array(6, 7),
            'with_seconds' => true,
        ));

        $field->submit(array('hour' => '04', 'minute' => '05', 'second' => '06'));

        $this->assertTrue($field->isSecondWithinRange());
    }

    public function testIsSecondWithinRange_returnsTrueIfEmpty()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'seconds' => array(6, 7),
            'with_seconds' => true,
        ));

        $field->submit(array('hour' => '06', 'minute' => '06', 'second' => ''));

        $this->assertTrue($field->isSecondWithinRange());
    }

    public function testIsSecondWithinRange_returnsTrueIfNotWithSeconds()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'seconds' => array(6, 7),
        ));

        $field->submit(array('hour' => '06', 'minute' => '06'));

        $this->assertTrue($field->isSecondWithinRange());
    }

    public function testIsSecondWithinRange_returnsFalseIfNotContained()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'seconds' => array(6, 7),
            'with_seconds' => true,
        ));

        $field->submit(array('hour' => '04', 'minute' => '05', 'second' => '08'));

        $this->assertFalse($field->isSecondWithinRange());
    }

    public function testIsPartiallyFilled_returnsFalseIfCompletelyEmpty()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'widget' => 'choice',
        ));

        $field->submit(array(
            'hour' => '',
            'minute' => '',
        ));

        $this->assertFalse($field->isPartiallyFilled());
    }

    public function testIsPartiallyFilled_returnsFalseIfCompletelyEmpty_withSeconds()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'widget' => 'choice',
            'with_seconds' => true,
        ));

        $field->submit(array(
            'hour' => '',
            'minute' => '',
            'second' => '',
        ));

        $this->assertFalse($field->isPartiallyFilled());
    }

    public function testIsPartiallyFilled_returnsFalseIfCompletelyFilled()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'widget' => 'choice',
        ));

        $field->submit(array(
            'hour' => '0',
            'minute' => '0',
        ));

        $this->assertFalse($field->isPartiallyFilled());
    }

    public function testIsPartiallyFilled_returnsFalseIfCompletelyFilled_withSeconds()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'widget' => 'choice',
            'with_seconds' => true,
        ));

        $field->submit(array(
            'hour' => '0',
            'minute' => '0',
            'second' => '0',
        ));

        $this->assertFalse($field->isPartiallyFilled());
    }

    public function testIsPartiallyFilled_returnsTrueIfChoiceAndHourEmpty()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'widget' => 'choice',
            'with_seconds' => true,
        ));

        $field->submit(array(
            'hour' => '',
            'minute' => '0',
            'second' => '0',
        ));

        $this->assertTrue($field->isPartiallyFilled());
    }

    public function testIsPartiallyFilled_returnsTrueIfChoiceAndMinuteEmpty()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'widget' => 'choice',
            'with_seconds' => true,
        ));

        $field->submit(array(
            'hour' => '0',
            'minute' => '',
            'second' => '0',
        ));

        $this->assertTrue($field->isPartiallyFilled());
    }

    public function testIsPartiallyFilled_returnsTrueIfChoiceAndSecondsEmpty()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $field = $this->factory->getInstance('time', 'name', array(
            'widget' => 'choice',
            'with_seconds' => true,
        ));

        $field->submit(array(
            'hour' => '0',
            'minute' => '0',
            'second' => '',
        ));

        $this->assertTrue($field->isPartiallyFilled());
    }
}