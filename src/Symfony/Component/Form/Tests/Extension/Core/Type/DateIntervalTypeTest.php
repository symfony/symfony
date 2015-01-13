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
use Symfony\Component\Form\Test\TypeTestCase as TestCase;

class DateIntervalTypeTest extends TestCase
{
    public function testSubmitDateInterval()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'input' => 'dateinterval',
        ));
        $form->submit(array(
            'years' => '7',
            'months' => '6',
            'days' => '5',
        ));
        $dateInterval = new \DateInterval('P7Y6M5D');
        $this->assertDateIntervalEquals($dateInterval, $form->getData());
    }

    public function testSubmitString()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'input' => 'string',
        ));
        $form->submit(array(
            'years' => '7',
            'months' => '6',
            'days' => '5',
        ));
        $this->assertEquals('P7Y6M5D', $form->getData());
    }

    public function testSubmitArray()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'input' => 'array',
        ));
        $form->submit(array(
            'years' => '7',
            'months' => '6',
            'days' => '5',
        ));
        $this->assertEquals(array('years' => '7', 'months' => '6', 'days' => '5'), $form->getData());
    }

    public function testSubmitWithoutMonths()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'input' => 'dateinterval',
            'with_months' => false,
        ));
        $form->setData(new \DateInterval('P7Y5D'));
        $input = array(
            'years' => '7',
            'months' => '6',
            'days' => '5',
        );
        $form->submit($input);
        $this->assertDateIntervalEquals(new \DateInterval('P7Y5D'), $form->getData());
    }

    public function testSubmitWithTime()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'input' => 'dateinterval',
            'with_hours' => true,
            'with_minutes' => true,
            'with_seconds' => true,
        ));
        $form->setData(new \DateInterval('P7Y6M5DT4H3M2S'));
        $input = array(
            'years' => '7',
            'months' => '6',
            'days' => '5',
            'hours' => '4',
            'minutes' => '3',
            'seconds' => '2',
        );
        $form->submit($input);
        $this->assertDateIntervalEquals(new \DateInterval('P7Y6M5DT4H3M2S'), $form->getData());
    }

    public function testSubmitWithWeeks()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'input' => 'dateinterval',
            'with_years' => false,
            'with_months' => false,
            'with_weeks' => true,
            'with_days' => false,
        ));
        $form->setData(new \DateInterval('P0Y'));
        $input = array(
            'weeks' => '30',
        );
        $form->submit($input);
        $this->assertDateIntervalEquals(new \DateInterval('P30W'), $form->getData());
    }

    public function testSubmitWithInvert()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'input' => 'dateinterval',
            'with_invert' => true,
        ));
        $input = array(
            'years' => '7',
            'months' => '6',
            'days' => '5',
            'invert' => true,
        );
        $form->submit($input);
        $interval = new \DateInterval('P7Y6M5D');
        $interval->invert = 1;
        $this->assertDateIntervalEquals($interval, $form->getData());
    }

    public function testSubmitStringSingleText()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'input' => 'string',
            'widget' => 'single_text',
        ));
        $form->submit('P7Y6M5D');
        $this->assertEquals('P7Y6M5D', $form->getData());
        $this->assertEquals('P7Y6M5D', $form->getViewData());
    }

    public function testSubmitStringSingleTextWithSeconds()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'input' => 'string',
            'widget' => 'single_text',
            'with_hours' => true,
            'with_minutes' => true,
            'with_seconds' => true,
        ));
        $form->submit('P7Y6M5DT4H3M2S');
        $this->assertEquals('P7Y6M5DT4H3M2S', $form->getData());
        $this->assertEquals('P7Y6M5DT4H3M2S', $form->getViewData());
    }

    public function testSubmitArrayInteger()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'widget' => 'integer',
            'with_invert' => 'integer',
        ));
        $input = array(
            'years' => '7',
            'months' => '6',
            'days' => '5',
            'invert' => true,
        );
        $form->submit($input);
        $this->assertSame('7', $form['years']->getData());
        $this->assertSame('7', $form['years']->getViewData());
    }

    public function testInitializeWithDateInterval()
    {
        // Throws an exception if "data_class" option is not explicitly set
        // to null in the type
        $this->factory->create('dateinterval', new \DateInterval('P0Y'));
    }

    public function testPassDefaultPlaceholderToViewIfNotRequired()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'required' => false,
            'with_seconds' => true,
        ));
        $view = $form->createView();
        $this->assertSame('', $view['years']->vars['placeholder']);
        $this->assertSame('', $view['months']->vars['placeholder']);
        $this->assertSame('', $view['days']->vars['placeholder']);
        $this->assertSame('', $view['seconds']->vars['placeholder']);
    }

    public function testPassNoPlaceholderToViewIfRequired()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'required' => true,
            'with_seconds' => true,
        ));
        $view = $form->createView();
        $this->assertNull($view['years']->vars['placeholder']);
        $this->assertNull($view['months']->vars['placeholder']);
        $this->assertNull($view['days']->vars['placeholder']);
        $this->assertNull($view['seconds']->vars['placeholder']);
    }

    public function testPassPlaceholderAsString()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'placeholder' => 'Empty',
            'with_seconds' => true,
        ));
        $view = $form->createView();
        $this->assertSame('Empty', $view['years']->vars['placeholder']);
        $this->assertSame('Empty', $view['months']->vars['placeholder']);
        $this->assertSame('Empty', $view['days']->vars['placeholder']);
        $this->assertSame('Empty', $view['seconds']->vars['placeholder']);
    }

    public function testPassEmptyValueBC()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'empty_value' => 'Empty',
            'with_seconds' => true,
        ));
        $view = $form->createView();
        $this->assertSame('Empty', $view['years']->vars['placeholder']);
        $this->assertSame('Empty', $view['months']->vars['placeholder']);
        $this->assertSame('Empty', $view['days']->vars['placeholder']);
        $this->assertSame('Empty', $view['seconds']->vars['placeholder']);
        $this->assertSame('Empty', $view['years']->vars['empty_value']);
        $this->assertSame('Empty', $view['months']->vars['empty_value']);
        $this->assertSame('Empty', $view['days']->vars['empty_value']);
        $this->assertSame('Empty', $view['seconds']->vars['empty_value']);
    }

    public function testPassPlaceholderAsArray()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'placeholder' => array(
                'years' => 'Empty years',
                'months' => 'Empty months',
                'days' => 'Empty days',
                'hours' => 'Empty hours',
                'minutes' => 'Empty minutes',
                'seconds' => 'Empty seconds',
            ),
            'with_hours' => true,
            'with_minutes' => true,
            'with_seconds' => true,
        ));
        $view = $form->createView();
        $this->assertSame('Empty years', $view['years']->vars['placeholder']);
        $this->assertSame('Empty months', $view['months']->vars['placeholder']);
        $this->assertSame('Empty days', $view['days']->vars['placeholder']);
        $this->assertSame('Empty hours', $view['hours']->vars['placeholder']);
        $this->assertSame('Empty minutes', $view['minutes']->vars['placeholder']);
        $this->assertSame('Empty seconds', $view['seconds']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddEmptyIfNotRequired()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'required' => false,
            'placeholder' => array(
                'years' => 'Empty years',
                'days' => 'Empty days',
                'hours' => 'Empty hours',
                'seconds' => 'Empty seconds',
            ),
            'with_hours' => true,
            'with_minutes' => true,
            'with_seconds' => true,
        ));
        $view = $form->createView();
        $this->assertSame('Empty years', $view['years']->vars['placeholder']);
        $this->assertSame('', $view['months']->vars['placeholder']);
        $this->assertSame('Empty days', $view['days']->vars['placeholder']);
        $this->assertSame('Empty hours', $view['hours']->vars['placeholder']);
        $this->assertSame('', $view['minutes']->vars['placeholder']);
        $this->assertSame('Empty seconds', $view['seconds']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddNullIfRequired()
    {
        $form = $this->factory->create('dateinterval', null, array(
            'required' => true,
            'placeholder' => array(
                'years' => 'Empty years',
                'days' => 'Empty days',
                'hours' => 'Empty hours',
                'seconds' => 'Empty seconds',
            ),
            'with_hours' => true,
            'with_minutes' => true,
            'with_seconds' => true,
        ));
        $view = $form->createView();
        $this->assertSame('Empty years', $view['years']->vars['placeholder']);
        $this->assertNull($view['months']->vars['placeholder']);
        $this->assertSame('Empty days', $view['days']->vars['placeholder']);
        $this->assertSame('Empty hours', $view['hours']->vars['placeholder']);
        $this->assertNull($view['minutes']->vars['placeholder']);
        $this->assertSame('Empty seconds', $view['seconds']->vars['placeholder']);
    }

    public function testDateTypeChoiceErrorsBubbleUp()
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create('dateinterval', null);
        $form['years']->addError($error);
        $this->assertSame(array(), iterator_to_array($form['years']->getErrors()));
        $this->assertSame(array($error), iterator_to_array($form->getErrors()));
    }
}
