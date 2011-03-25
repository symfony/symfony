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

use Symfony\Component\Form\DateField;
use Symfony\Component\Form\FormContext;

class DateTypeTest extends DateTimeTestCase
{
    protected function setUp()
    {
        parent::setUp();

        \Locale::setDefault('de_AT');
    }

    public function testSubmit_fromInput_dateTime()
    {
        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
            'input' => 'datetime',
        ));

        $form->bind('2.6.2010');

        $this->assertDateTimeEquals(new \DateTime('2010-06-02 UTC'), $form->getData());
        $this->assertEquals('02.06.2010', $form->getClientData());
    }

    public function testSubmit_fromInput_string()
    {
        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
            'input' => 'string',
        ));

        $form->bind('2.6.2010');

        $this->assertEquals('2010-06-02', $form->getData());
        $this->assertEquals('02.06.2010', $form->getClientData());
    }

    public function testSubmit_fromInput_timestamp()
    {
        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
            'input' => 'timestamp',
        ));

        $form->bind('2.6.2010');

        $dateTime = new \DateTime('2010-06-02 UTC');

        $this->assertEquals($dateTime->format('U'), $form->getData());
        $this->assertEquals('02.06.2010', $form->getClientData());
    }

    public function testSubmit_fromInput_raw()
    {
        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
            'input' => 'array',
        ));

        $form->bind('2.6.2010');

        $output = array(
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        );

        $this->assertEquals($output, $form->getData());
        $this->assertEquals('02.06.2010', $form->getClientData());
    }

    public function testSubmit_fromChoice()
    {
        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'choice',
        ));

        $text = array(
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        );

        $form->bind($text);

        $dateTime = new \DateTime('2010-06-02 UTC');

        $this->assertDateTimeEquals($dateTime, $form->getData());
        $this->assertEquals($text, $form->getClientData());
    }

    public function testSubmit_fromChoice_empty()
    {
        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'choice',
            'required' => false,
        ));

        $text = array(
            'day' => '',
            'month' => '',
            'year' => '',
        );

        $form->bind($text);

        $this->assertSame(null, $form->getData());
        $this->assertEquals($text, $form->getClientData());
    }

    public function testSetData_differentTimezones()
    {
        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'America/New_York',
            'user_timezone' => 'Pacific/Tahiti',
            // don't do this test with DateTime, because it leads to wrong results!
            'input' => 'string',
            'widget' => 'text',
        ));

        $form->setData('2010-06-02');

        $this->assertEquals('01.06.2010', $form->getClientData());
    }

    public function testIsYearWithinRange_returnsTrueIfWithin()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
            'years' => array(2010, 2011),
        ));

        $form->bind('2.6.2010');

        $this->assertTrue($form->isYearWithinRange());
    }

    public function testIsYearWithinRange_returnsTrueIfEmpty()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
            'years' => array(2010, 2011),
        ));

        $form->bind('');

        $this->assertTrue($form->isYearWithinRange());
    }

    public function testIsYearWithinRange_returnsTrueIfEmpty_choice()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'choice',
            'years' => array(2010, 2011),
        ));

        $form->bind(array(
            'day' => '1',
            'month' => '2',
            'year' => '',
        ));

        $this->assertTrue($form->isYearWithinRange());
    }

    public function testIsYearWithinRange_returnsFalseIfNotContained()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
            'years' => array(2010, 2012),
        ));

        $form->bind('2.6.2011');

        $this->assertFalse($form->isYearWithinRange());
    }

    public function testIsMonthWithinRange_returnsTrueIfWithin()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
            'months' => array(6, 7),
        ));

        $form->bind('2.6.2010');

        $this->assertTrue($form->isMonthWithinRange());
    }

    public function testIsMonthWithinRange_returnsTrueIfEmpty()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
            'months' => array(6, 7),
        ));

        $form->bind('');

        $this->assertTrue($form->isMonthWithinRange());
    }

    public function testIsMonthWithinRange_returnsTrueIfEmpty_choice()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'choice',
            'months' => array(6, 7),
        ));

        $form->bind(array(
            'day' => '1',
            'month' => '',
            'year' => '2011',
        ));

        $this->assertTrue($form->isMonthWithinRange());
    }

    public function testIsMonthWithinRange_returnsFalseIfNotContained()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
            'months' => array(6, 8),
        ));

        $form->bind('2.7.2010');

        $this->assertFalse($form->isMonthWithinRange());
    }

    public function testIsDayWithinRange_returnsTrueIfWithin()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
            'days' => array(6, 7),
        ));

        $form->bind('6.6.2010');

        $this->assertTrue($form->isDayWithinRange());
    }

    public function testIsDayWithinRange_returnsTrueIfEmpty()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
            'days' => array(6, 7),
        ));

        $form->bind('');

        $this->assertTrue($form->isDayWithinRange());
    }

    public function testIsDayWithinRange_returnsTrueIfEmpty_choice()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'choice',
            'days' => array(6, 7),
        ));

        $form->bind(array(
            'day' => '',
            'month' => '1',
            'year' => '2011',
        ));

        $this->assertTrue($form->isDayWithinRange());
    }

    public function testIsDayWithinRange_returnsFalseIfNotContained()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
            'days' => array(6, 8),
        ));

        $form->bind('7.6.2010');

        $this->assertFalse($form->isDayWithinRange());
    }

    public function testIsPartiallyFilled_returnsFalseIfInput()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
        ));

        $form->bind('7.6.2010');

        $this->assertFalse($form->isPartiallyFilled());
    }

    public function testIsPartiallyFilled_returnsFalseIfChoiceAndCompletelyEmpty()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'choice',
        ));

        $form->bind(array(
            'day' => '',
            'month' => '',
            'year' => '',
        ));

        $this->assertFalse($form->isPartiallyFilled());
    }

    public function testIsPartiallyFilled_returnsFalseIfChoiceAndCompletelyFilled()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'choice',
        ));

        $form->bind(array(
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        ));

        $this->assertFalse($form->isPartiallyFilled());
    }

    public function testIsPartiallyFilled_returnsTrueIfChoiceAndDayEmpty()
    {
        $this->markTestSkipped('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', 'name', array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'choice',
        ));

        $form->bind(array(
            'day' => '',
            'month' => '6',
            'year' => '2010',
        ));

        $this->assertTrue($form->isPartiallyFilled());
    }
}