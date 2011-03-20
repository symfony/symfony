<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Type;

require_once __DIR__ . '/DateTimeTestCase.php';

use Symfony\Component\Form\DateTimeField;
use Symfony\Component\Form\DateField;
use Symfony\Component\Form\TimeField;

class DateTimeTypeTest extends DateTimeTestCase
{
    public function testSubmit_dateTime()
    {
        $field = $this->factory->create('datetime', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'date_widget' => 'choice',
            'time_widget' => 'choice',
            'input' => 'datetime',
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

    public function testSubmit_string()
    {
        $field = $this->factory->create('datetime', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'input' => 'string',
            'date_widget' => 'choice',
            'time_widget' => 'choice',
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

    public function testSubmit_timestamp()
    {
        $field = $this->factory->create('datetime', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'input' => 'timestamp',
            'date_widget' => 'choice',
            'time_widget' => 'choice',
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

    public function testSubmit_withSeconds()
    {
        $field = $this->factory->create('datetime', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'date_widget' => 'choice',
            'time_widget' => 'choice',
            'input' => 'datetime',
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

    public function testSubmit_differentTimezones()
    {
        $field = $this->factory->create('datetime', 'name', array(
            'data_timezone' => 'America/New_York',
            'user_timezone' => 'Pacific/Tahiti',
            'date_widget' => 'choice',
            'time_widget' => 'choice',
            // don't do this test with DateTime, because it leads to wrong results!
            'input' => 'string',
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
}