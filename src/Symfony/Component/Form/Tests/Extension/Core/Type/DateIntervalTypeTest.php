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

use Symfony\Component\Form\Extension\Core\Type\DateIntervalType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

class DateIntervalTypeTest extends BaseTypeTest
{
    public const TESTED_TYPE = DateIntervalType::class;

    public function testSubmitDateInterval()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['input' => 'dateinterval']);

        $form->submit([
            'years' => '7',
            'months' => '6',
            'days' => '5',
        ]);

        self::assertDateIntervalEquals(new \DateInterval('P7Y6M5D'), $form->getData());
    }

    public function testSubmitString()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['input' => 'string']);

        $form->submit([
            'years' => '7',
            'months' => '6',
            'days' => '5',
        ]);

        self::assertSame('P7Y6M5D', $form->getData());
    }

    public function testSubmitArray()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['input' => 'array']);

        $input = [
            'years' => '7',
            'months' => '6',
            'days' => '5',
        ];

        $form->submit($input);

        self::assertSame($input, $form->getData());
    }

    public function testSubmitWithoutMonths()
    {
        $interval = new \DateInterval('P7Y5D');

        $form = $this->factory->create(static::TESTED_TYPE, $interval, [
            'input' => 'dateinterval',
            'with_months' => false,
        ]);

        $form->submit([
            'years' => '7',
            'months' => '6',
            'days' => '5',
        ]);

        self::assertDateIntervalEquals($interval, $form->getData());
        self::assertTrue($form->isSynchronized());
    }

    public function testSubmitWithTime()
    {
        $interval = new \DateInterval('P7Y6M5DT4H3M2S');
        $form = $this->factory->create(static::TESTED_TYPE, $interval, [
            'input' => 'dateinterval',
            'with_hours' => true,
            'with_minutes' => true,
            'with_seconds' => true,
        ]);

        $form->submit([
            'years' => '7',
            'months' => '6',
            'days' => '5',
            'hours' => '4',
            'minutes' => '3',
            'seconds' => '2',
        ]);

        self::assertDateIntervalEquals($interval, $form->getData());
        self::assertTrue($form->isSynchronized());
    }

    public function testSubmitWithWeeks()
    {
        $form = $this->factory->create(static::TESTED_TYPE, new \DateInterval('P0Y'), [
            'input' => 'dateinterval',
            'with_years' => false,
            'with_months' => false,
            'with_weeks' => true,
            'with_days' => false,
        ]);

        $form->submit([
            'weeks' => '30',
        ]);

        self::assertDateIntervalEquals(new \DateInterval('P30W'), $form->getData());
    }

    public function testSubmitWithInvert()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'input' => 'dateinterval',
            'with_invert' => true,
        ]);

        $form->submit([
            'years' => '7',
            'months' => '6',
            'days' => '5',
            'invert' => true,
        ]);

        $interval = new \DateInterval('P7Y6M5D');
        $interval->invert = 1;

        self::assertDateIntervalEquals($interval, $form->getData());
    }

    public function testSubmitStringSingleText()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'input' => 'string',
            'widget' => 'single_text',
        ]);

        $interval = 'P7Y6M5D';

        $form->submit($interval);

        self::assertSame($interval, $form->getData());
        self::assertSame($interval, $form->getViewData());
    }

    public function testSubmitStringSingleTextWithSeconds()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'input' => 'string',
            'widget' => 'single_text',
            'with_hours' => true,
            'with_minutes' => true,
            'with_seconds' => true,
        ]);

        $interval = 'P7Y6M5DT4H3M2S';

        $form->submit($interval);

        self::assertSame($interval, $form->getData());
        self::assertSame($interval, $form->getViewData());
    }

    public function testSubmitArrayInteger()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'integer',
            'with_invert' => true,
        ]);

        $years = '7';

        $form->submit([
            'years' => '7',
            'months' => '6',
            'days' => '5',
            'invert' => true,
        ]);

        self::assertSame($years, $form['years']->getData());
        self::assertSame($years, $form['years']->getViewData());
    }

    public function testInitializeWithDateInterval()
    {
        // Throws an exception if "data_class" option is not explicitly set
        // to null in the type
        self::assertInstanceOf(FormInterface::class, $this->factory->create(static::TESTED_TYPE, new \DateInterval('P0Y')));
    }

    public function testPassDefaultPlaceholderToViewIfNotRequired()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => false,
            'with_seconds' => true,
        ])
            ->createView();

        self::assertSame('', $view['years']->vars['placeholder']);
        self::assertSame('', $view['months']->vars['placeholder']);
        self::assertSame('', $view['days']->vars['placeholder']);
        self::assertSame('', $view['seconds']->vars['placeholder']);
    }

    public function testPassNoPlaceholderToViewIfRequired()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => true,
            'with_seconds' => true,
        ])
            ->createView();

        self::assertNull($view['years']->vars['placeholder']);
        self::assertNull($view['months']->vars['placeholder']);
        self::assertNull($view['days']->vars['placeholder']);
        self::assertNull($view['seconds']->vars['placeholder']);
    }

    public function testPassPlaceholderAsString()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'placeholder' => 'Empty',
            'with_seconds' => true,
        ])
            ->createView();

        self::assertSame('Empty', $view['years']->vars['placeholder']);
        self::assertSame('Empty', $view['months']->vars['placeholder']);
        self::assertSame('Empty', $view['days']->vars['placeholder']);
        self::assertSame('Empty', $view['seconds']->vars['placeholder']);
    }

    public function testPassPlaceholderAsArray()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'placeholder' => [
                'years' => 'Empty years',
                'months' => 'Empty months',
                'days' => 'Empty days',
                'hours' => 'Empty hours',
                'minutes' => 'Empty minutes',
                'seconds' => 'Empty seconds',
            ],
            'with_hours' => true,
            'with_minutes' => true,
            'with_seconds' => true,
        ])
            ->createView();

        self::assertSame('Empty years', $view['years']->vars['placeholder']);
        self::assertSame('Empty months', $view['months']->vars['placeholder']);
        self::assertSame('Empty days', $view['days']->vars['placeholder']);
        self::assertSame('Empty hours', $view['hours']->vars['placeholder']);
        self::assertSame('Empty minutes', $view['minutes']->vars['placeholder']);
        self::assertSame('Empty seconds', $view['seconds']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddEmptyIfNotRequired()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => false,
            'placeholder' => [
                'years' => 'Empty years',
                'days' => 'Empty days',
                'hours' => 'Empty hours',
                'seconds' => 'Empty seconds',
            ],
            'with_hours' => true,
            'with_minutes' => true,
            'with_seconds' => true,
        ])
            ->createView();

        self::assertSame('Empty years', $view['years']->vars['placeholder']);
        self::assertSame('', $view['months']->vars['placeholder']);
        self::assertSame('Empty days', $view['days']->vars['placeholder']);
        self::assertSame('Empty hours', $view['hours']->vars['placeholder']);
        self::assertSame('', $view['minutes']->vars['placeholder']);
        self::assertSame('Empty seconds', $view['seconds']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddNullIfRequired()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => true,
            'placeholder' => [
                'years' => 'Empty years',
                'days' => 'Empty days',
                'hours' => 'Empty hours',
                'seconds' => 'Empty seconds',
            ],
            'with_hours' => true,
            'with_minutes' => true,
            'with_seconds' => true,
        ])
            ->createView();

        self::assertSame('Empty years', $view['years']->vars['placeholder']);
        self::assertNull($view['months']->vars['placeholder']);
        self::assertSame('Empty days', $view['days']->vars['placeholder']);
        self::assertSame('Empty hours', $view['hours']->vars['placeholder']);
        self::assertNull($view['minutes']->vars['placeholder']);
        self::assertSame('Empty seconds', $view['seconds']->vars['placeholder']);
    }

    public function testDateTypeChoiceErrorsBubbleUp()
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null);

        $form['years']->addError($error);

        self::assertSame([], iterator_to_array($form['years']->getErrors()));
        self::assertSame([$error], iterator_to_array($form->getErrors()));
    }

    public function testTranslationsAreDisabledForChoiceWidget()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'choice',
            'with_hours' => true,
            'with_minutes' => true,
            'with_seconds' => true,
        ]);

        self::assertFalse($form->get('years')->getConfig()->getOption('choice_translation_domain'));
        self::assertFalse($form->get('months')->getConfig()->getOption('choice_translation_domain'));
        self::assertFalse($form->get('days')->getConfig()->getOption('choice_translation_domain'));
        self::assertFalse($form->get('hours')->getConfig()->getOption('choice_translation_domain'));
        self::assertFalse($form->get('minutes')->getConfig()->getOption('choice_translation_domain'));
        self::assertFalse($form->get('seconds')->getConfig()->getOption('choice_translation_domain'));
    }

    public function testInvertDoesNotInheritRequiredOption()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'input' => 'dateinterval',
            'with_invert' => true,
            'required' => true,
        ]);

        self::assertFalse($form->get('invert')->getConfig()->getOption('required'));
    }

    public function testCanChangeTimeFieldsLabels()
    {
        $form = $this->factory->create(
            static::TESTED_TYPE,
            null,
            [
                'required' => true,
                'with_invert' => true,
                'with_hours' => true,
                'with_minutes' => true,
                'with_seconds' => true,
                'labels' => [
                    'invert' => 'form.trans.invert',
                    'years' => 'form.trans.years',
                    'months' => 'form.trans.months',
                    'days' => 'form.trans.days',
                    'hours' => 'form.trans.hours',
                    'minutes' => 'form.trans.minutes',
                    'seconds' => 'form.trans.seconds',
                ],
            ]
        );

        $view = $form->createView();
        self::assertSame('form.trans.invert', $view['invert']->vars['label']);
        self::assertSame('form.trans.years', $view['years']->vars['label']);
        self::assertSame('form.trans.months', $view['months']->vars['label']);
        self::assertSame('form.trans.days', $view['days']->vars['label']);
        self::assertSame('form.trans.hours', $view['hours']->vars['label']);
        self::assertSame('form.trans.minutes', $view['minutes']->vars['label']);
        self::assertSame('form.trans.seconds', $view['seconds']->vars['label']);
    }

    public function testInvertDefaultLabel()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['with_invert' => true]);

        $view = $form->createView();
        self::assertSame('Negative interval', $view['invert']->vars['label']);

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'with_invert' => true,
            'labels' => ['invert' => null],
        ]);

        $view = $form->createView();
        self::assertSame('Negative interval', $view['invert']->vars['label']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, [
            'years' => '',
            'months' => '',
            'days' => '',
        ]);
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = [], $expectedData = null)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        // view transformer writes back empty strings in the view data
        self::assertSame(['years' => '', 'months' => '', 'days' => ''], $form->getViewData());
        self::assertSame($expectedData, $form->getNormData());
        self::assertSame($expectedData, $form->getData());
    }

    /**
     * @dataProvider provideEmptyData
     */
    public function testSubmitNullUsesDateEmptyData($widget, $emptyData, $expectedData)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => $widget,
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        self::assertSame($emptyData, $form->getViewData());
        self::assertEquals($expectedData, $form->getNormData());
        self::assertEquals($expectedData, $form->getData());
    }

    public function provideEmptyData()
    {
        $expectedData = new \DateInterval('P6Y4M');

        return [
            'Simple field' => ['single_text', 'P6Y4M0D', $expectedData],
            'Compound text field' => ['text', ['years' => '06', 'months' => '04', 'days' => '00'], $expectedData],
            'Compound integer field' => ['integer', ['years' => '6', 'months' => '4', 'days' => '0'], $expectedData],
            'Compound choice field' => ['choice', ['years' => '6', 'months' => '4', 'days' => '0'], $expectedData],
        ];
    }
}
