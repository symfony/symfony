<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\FormError;

class DateTimeTypeTest extends LocalizedTestCase
{
    public function testSubmit_dateTime()
    {
        $form = $this->factory->create('datetime', null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'date_widget' => 'choice',
            'time_widget' => 'choice',
            'input' => 'datetime',
        ));

        $form->bind(array(
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

        $this->assertDateTimeEquals($dateTime, $form->getData());
    }

    public function testSubmit_string()
    {
        $form = $this->factory->create('datetime', null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'string',
            'date_widget' => 'choice',
            'time_widget' => 'choice',
        ));

        $form->bind(array(
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

        $this->assertEquals('2010-06-02 03:04:00', $form->getData());
    }

    public function testSubmit_timestamp()
    {
        $form = $this->factory->create('datetime', null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'timestamp',
            'date_widget' => 'choice',
            'time_widget' => 'choice',
        ));

        $form->bind(array(
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

        $this->assertEquals($dateTime->format('U'), $form->getData());
    }

    public function testSubmit_withSeconds()
    {
        $form = $this->factory->create('datetime', null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'date_widget' => 'choice',
            'time_widget' => 'choice',
            'input' => 'datetime',
            'with_seconds' => true,
        ));

        $form->setData(new \DateTime('2010-06-02 03:04:05 UTC'));

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

        $form->bind($input);

        $this->assertDateTimeEquals(new \DateTime('2010-06-02 03:04:05 UTC'), $form->getData());
    }

    public function testSubmit_differentTimezones()
    {
        $form = $this->factory->create('datetime', null, array(
            'model_timezone' => 'America/New_York',
            'view_timezone' => 'Pacific/Tahiti',
            'date_widget' => 'choice',
            'time_widget' => 'choice',
            'input' => 'string',
            'with_seconds' => true,
        ));

        $dateTime = new \DateTime('2010-06-02 03:04:05 Pacific/Tahiti');

        $form->bind(array(
            'date' => array(
                'day' => (int) $dateTime->format('d'),
                'month' => (int) $dateTime->format('m'),
                'year' => (int) $dateTime->format('Y'),
            ),
            'time' => array(
                'hour' => (int) $dateTime->format('H'),
                'minute' => (int) $dateTime->format('i'),
                'second' => (int) $dateTime->format('s'),
            ),
        ));

        $dateTime->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertEquals($dateTime->format('Y-m-d H:i:s'), $form->getData());
    }

    public function testSubmit_differentTimezonesDateTime()
    {
        $form = $this->factory->create('datetime', null, array(
            'model_timezone' => 'America/New_York',
            'view_timezone' => 'Pacific/Tahiti',
            'widget' => 'single_text',
            'input' => 'datetime',
        ));

        $outputTime = new \DateTime('2010-06-02 03:04:00 Pacific/Tahiti');

        $form->bind('2010-06-02T03:04:00-10:00');

        $outputTime->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertDateTimeEquals($outputTime, $form->getData());
        $this->assertEquals('2010-06-02T03:04:00-10:00', $form->getViewData());
    }

    public function testSubmit_stringSingleText()
    {
        $form = $this->factory->create('datetime', null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'string',
            'widget' => 'single_text',
        ));

        $form->bind('2010-06-02T03:04:00Z');

        $this->assertEquals('2010-06-02 03:04:00', $form->getData());
        $this->assertEquals('2010-06-02T03:04:00Z', $form->getViewData());
    }

    public function testSubmit_stringSingleText_withSeconds()
    {
        $form = $this->factory->create('datetime', null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'string',
            'widget' => 'single_text',
            'with_seconds' => true,
        ));

        $form->bind('2010-06-02T03:04:05Z');

        $this->assertEquals('2010-06-02 03:04:05', $form->getData());
        $this->assertEquals('2010-06-02T03:04:05Z', $form->getViewData());
    }

    public function testSubmit_differentPattern()
    {
        $form = $this->factory->create('datetime', null, array(
            'date_format' => 'MM*yyyy*dd',
            'date_widget' => 'single_text',
            'time_widget' => 'single_text',
            'input' => 'datetime',
        ));

        $dateTime = new \DateTime('2010-06-02 03:04');

        $form->bind(array(
            'date' => '06*2010*02',
            'time' => '03:04',
        ));

        $this->assertDateTimeEquals($dateTime, $form->getData());
    }

    // Bug fix
    public function testInitializeWithDateTime()
    {
        // Throws an exception if "data_class" option is not explicitly set
        // to null in the type
        $this->factory->create('datetime', new \DateTime());
    }

    public function testSingleTextWidgetShouldUseTheRightInputType()
    {
        $form = $this->factory->create('datetime', null, array(
            'widget' => 'single_text',
        ));

        $view = $form->createView();
        $this->assertEquals('datetime', $view->vars['type']);
    }

    public function testPassDefaultEmptyValueToViewIfNotRequired()
    {
        $form = $this->factory->create('datetime', null, array(
            'required' => false,
            'with_seconds' => true,
        ));

        $view = $form->createView();
        $this->assertSame('', $view['date']['year']->vars['empty_value']);
        $this->assertSame('', $view['date']['month']->vars['empty_value']);
        $this->assertSame('', $view['date']['day']->vars['empty_value']);
        $this->assertSame('', $view['time']['hour']->vars['empty_value']);
        $this->assertSame('', $view['time']['minute']->vars['empty_value']);
        $this->assertSame('', $view['time']['second']->vars['empty_value']);
    }

    public function testPassNoEmptyValueToViewIfRequired()
    {
        $form = $this->factory->create('datetime', null, array(
            'required' => true,
            'with_seconds' => true,
        ));

        $view = $form->createView();
        $this->assertNull($view['date']['year']->vars['empty_value']);
        $this->assertNull($view['date']['month']->vars['empty_value']);
        $this->assertNull($view['date']['day']->vars['empty_value']);
        $this->assertNull($view['time']['hour']->vars['empty_value']);
        $this->assertNull($view['time']['minute']->vars['empty_value']);
        $this->assertNull($view['time']['second']->vars['empty_value']);
    }

    public function testPassEmptyValueAsString()
    {
        $form = $this->factory->create('datetime', null, array(
            'empty_value' => 'Empty',
            'with_seconds' => true,
        ));

        $view = $form->createView();
        $this->assertSame('Empty', $view['date']['year']->vars['empty_value']);
        $this->assertSame('Empty', $view['date']['month']->vars['empty_value']);
        $this->assertSame('Empty', $view['date']['day']->vars['empty_value']);
        $this->assertSame('Empty', $view['time']['hour']->vars['empty_value']);
        $this->assertSame('Empty', $view['time']['minute']->vars['empty_value']);
        $this->assertSame('Empty', $view['time']['second']->vars['empty_value']);
    }

    public function testPassEmptyValueAsArray()
    {
        $form = $this->factory->create('datetime', null, array(
            'empty_value' => array(
                'year' => 'Empty year',
                'month' => 'Empty month',
                'day' => 'Empty day',
                'hour' => 'Empty hour',
                'minute' => 'Empty minute',
                'second' => 'Empty second',
            ),
            'with_seconds' => true,
        ));

        $view = $form->createView();
        $this->assertSame('Empty year', $view['date']['year']->vars['empty_value']);
        $this->assertSame('Empty month', $view['date']['month']->vars['empty_value']);
        $this->assertSame('Empty day', $view['date']['day']->vars['empty_value']);
        $this->assertSame('Empty hour', $view['time']['hour']->vars['empty_value']);
        $this->assertSame('Empty minute', $view['time']['minute']->vars['empty_value']);
        $this->assertSame('Empty second', $view['time']['second']->vars['empty_value']);
    }

    public function testPassEmptyValueAsPartialArray_addEmptyIfNotRequired()
    {
        $form = $this->factory->create('datetime', null, array(
            'required' => false,
            'empty_value' => array(
                'year' => 'Empty year',
                'day' => 'Empty day',
                'hour' => 'Empty hour',
                'second' => 'Empty second',
            ),
            'with_seconds' => true,
        ));

        $view = $form->createView();
        $this->assertSame('Empty year', $view['date']['year']->vars['empty_value']);
        $this->assertSame('', $view['date']['month']->vars['empty_value']);
        $this->assertSame('Empty day', $view['date']['day']->vars['empty_value']);
        $this->assertSame('Empty hour', $view['time']['hour']->vars['empty_value']);
        $this->assertSame('', $view['time']['minute']->vars['empty_value']);
        $this->assertSame('Empty second', $view['time']['second']->vars['empty_value']);
    }

    public function testPassEmptyValueAsPartialArray_addNullIfRequired()
    {
        $form = $this->factory->create('datetime', null, array(
            'required' => true,
            'empty_value' => array(
                'year' => 'Empty year',
                'day' => 'Empty day',
                'hour' => 'Empty hour',
                'second' => 'Empty second',
            ),
            'with_seconds' => true,
        ));

        $view = $form->createView();
        $this->assertSame('Empty year', $view['date']['year']->vars['empty_value']);
        $this->assertNull($view['date']['month']->vars['empty_value']);
        $this->assertSame('Empty day', $view['date']['day']->vars['empty_value']);
        $this->assertSame('Empty hour', $view['time']['hour']->vars['empty_value']);
        $this->assertNull($view['time']['minute']->vars['empty_value']);
        $this->assertSame('Empty second', $view['time']['second']->vars['empty_value']);
    }

    public function testPassHtml5TypeIfSingleTextAndHtml5Format()
    {
        $form = $this->factory->create('datetime', null, array(
            'widget' => 'single_text',
        ));

        $view = $form->createView();
        $this->assertSame('datetime', $view->vars['type']);
    }

    public function testDontPassHtml5TypeIfNotHtml5Format()
    {
        $form = $this->factory->create('datetime', null, array(
            'widget' => 'single_text',
            'format' => 'yyyy-MM-dd HH:mm',
        ));

        $view = $form->createView();
        $this->assertFalse(isset($view->vars['type']));
    }

    public function testDontPassHtml5TypeIfNotSingleText()
    {
        $form = $this->factory->create('datetime', null, array(
            'widget' => 'text',
        ));

        $view = $form->createView();
        $this->assertFalse(isset($view->vars['type']));
    }

    public function testDateTypeChoiceErrorsBubbleUp()
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create('datetime', null);

        $form['date']->addError($error);

        $this->assertSame(array(), $form['date']->getErrors());
        $this->assertSame(array($error), $form->getErrors());
    }

    public function testDateTypeSingleTextErrorsBubbleUp()
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create('datetime', null, array(
            'date_widget' => 'single_text'
        ));

        $form['date']->addError($error);

        $this->assertSame(array(), $form['date']->getErrors());
        $this->assertSame(array($error), $form->getErrors());
    }

    public function testTimeTypeChoiceErrorsBubbleUp()
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create('datetime', null);

        $form['time']->addError($error);

        $this->assertSame(array(), $form['time']->getErrors());
        $this->assertSame(array($error), $form->getErrors());
    }

    public function testTimeTypeSingleTextErrorsBubbleUp()
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create('datetime', null, array(
            'time_widget' => 'single_text'
        ));

        $form['time']->addError($error);

        $this->assertSame(array(), $form['time']->getErrors());
        $this->assertSame(array($error), $form->getErrors());
    }

}
