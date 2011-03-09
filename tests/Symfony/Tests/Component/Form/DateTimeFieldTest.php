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

use Symfony\Component\Form\DateTimeField;
use Symfony\Component\Form\DateField;
use Symfony\Component\Form\TimeField;

class DateTimeFieldTest extends DateTimeTestCase
{
    public function testSubmit_dateTime()
    {
        $field = new DateTimeField('name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'date_widget' => DateField::CHOICE,
            'time_widget' => TimeField::CHOICE,
            'type' => DateTimeField::DATETIME,
        ));

        $field->submit(array(
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

    public function testSubmit_string()
    {
        $field = new DateTimeField('name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'type' => DateTimeField::STRING,
            'date_widget' => DateField::CHOICE,
            'time_widget' => TimeField::CHOICE,
        ));

        $field->submit(array(
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

    public function testSubmit_timestamp()
    {
        $field = new DateTimeField('name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'type' => DateTimeField::TIMESTAMP,
            'date_widget' => DateField::CHOICE,
            'time_widget' => TimeField::CHOICE,
        ));

        $field->submit(array(
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

    public function testSubmit_withSeconds()
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

        $field->submit($input);

        $this->assertDateTimeEquals(new \DateTime('2010-06-02 03:04:05 UTC'), $field->getData());
    }

    public function testSubmit_differentTimezones()
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

        $field->submit(array(
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
}