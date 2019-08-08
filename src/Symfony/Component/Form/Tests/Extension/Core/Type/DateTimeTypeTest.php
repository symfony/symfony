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

class DateTimeTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\DateTimeType';

    protected function setUp(): void
    {
        \Locale::setDefault('en');

        parent::setUp();
    }

    public function testSubmitDateTime()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'date_widget' => 'choice',
            'years' => [2010],
            'time_widget' => 'choice',
            'input' => 'datetime',
        ]);

        $form->submit([
            'date' => [
                'day' => '2',
                'month' => '6',
                'year' => '2010',
            ],
            'time' => [
                'hour' => '3',
                'minute' => '4',
            ],
        ]);

        $dateTime = new \DateTime('2010-06-02 03:04:00 UTC');

        $this->assertEquals($dateTime, $form->getData());
    }

    public function testSubmitDateTimeImmutable()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'date_widget' => 'choice',
            'years' => [2010],
            'time_widget' => 'choice',
            'input' => 'datetime_immutable',
        ]);

        $form->submit([
            'date' => [
                'day' => '2',
                'month' => '6',
                'year' => '2010',
            ],
            'time' => [
                'hour' => '3',
                'minute' => '4',
            ],
        ]);

        $dateTime = new \DateTimeImmutable('2010-06-02 03:04:00 UTC');

        $this->assertEquals($dateTime, $form->getData());
    }

    public function testSubmitString()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'string',
            'date_widget' => 'choice',
            'years' => [2010],
            'time_widget' => 'choice',
        ]);

        $form->submit([
            'date' => [
                'day' => '2',
                'month' => '6',
                'year' => '2010',
            ],
            'time' => [
                'hour' => '3',
                'minute' => '4',
            ],
        ]);

        $this->assertEquals('2010-06-02 03:04:00', $form->getData());
    }

    public function testSubmitTimestamp()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'timestamp',
            'date_widget' => 'choice',
            'years' => [2010],
            'time_widget' => 'choice',
        ]);

        $form->submit([
            'date' => [
                'day' => '2',
                'month' => '6',
                'year' => '2010',
            ],
            'time' => [
                'hour' => '3',
                'minute' => '4',
            ],
        ]);

        $dateTime = new \DateTime('2010-06-02 03:04:00 UTC');

        $this->assertEquals($dateTime->format('U'), $form->getData());
    }

    public function testSubmitWithoutMinutes()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'date_widget' => 'choice',
            'years' => [2010],
            'time_widget' => 'choice',
            'input' => 'datetime',
            'with_minutes' => false,
        ]);

        $form->setData(new \DateTime());

        $input = [
            'date' => [
                'day' => '2',
                'month' => '6',
                'year' => '2010',
            ],
            'time' => [
                'hour' => '3',
            ],
        ];

        $form->submit($input);

        $this->assertEquals(new \DateTime('2010-06-02 03:00:00 UTC'), $form->getData());
    }

    public function testSubmitWithSeconds()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'date_widget' => 'choice',
            'years' => [2010],
            'time_widget' => 'choice',
            'input' => 'datetime',
            'with_seconds' => true,
        ]);

        $form->setData(new \DateTime());

        $input = [
            'date' => [
                'day' => '2',
                'month' => '6',
                'year' => '2010',
            ],
            'time' => [
                'hour' => '3',
                'minute' => '4',
                'second' => '5',
            ],
        ];

        $form->submit($input);

        $this->assertEquals(new \DateTime('2010-06-02 03:04:05 UTC'), $form->getData());
    }

    public function testSubmitDifferentTimezones()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'America/New_York',
            'view_timezone' => 'Pacific/Tahiti',
            'date_widget' => 'choice',
            'years' => [2010],
            'time_widget' => 'choice',
            'input' => 'string',
            'with_seconds' => true,
        ]);

        $dateTime = new \DateTime('2010-06-02 03:04:05 Pacific/Tahiti');

        $form->submit([
            'date' => [
                'day' => (int) $dateTime->format('d'),
                'month' => (int) $dateTime->format('m'),
                'year' => (int) $dateTime->format('Y'),
            ],
            'time' => [
                'hour' => (int) $dateTime->format('H'),
                'minute' => (int) $dateTime->format('i'),
                'second' => (int) $dateTime->format('s'),
            ],
        ]);

        $dateTime->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertEquals($dateTime->format('Y-m-d H:i:s'), $form->getData());
    }

    public function testSubmitDifferentTimezonesDateTime()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'America/New_York',
            'view_timezone' => 'Pacific/Tahiti',
            'widget' => 'single_text',
            'input' => 'datetime',
        ]);

        $outputTime = new \DateTime('2010-06-02 03:04:00 Pacific/Tahiti');

        $form->submit('2010-06-02T03:04:00');

        $outputTime->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertEquals($outputTime, $form->getData());
        $this->assertEquals('2010-06-02T03:04:00', $form->getViewData());
    }

    public function testSubmitDifferentTimezonesDateTimeImmutable()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'America/New_York',
            'view_timezone' => 'Pacific/Tahiti',
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
        ]);

        $outputTime = new \DateTimeImmutable('2010-06-02 03:04:00 Pacific/Tahiti');

        $form->submit('2010-06-02T03:04:00');

        $outputTime = $outputTime->setTimezone(new \DateTimeZone('America/New_York'));

        $this->assertInstanceOf(\DateTimeImmutable::class, $form->getData());
        $this->assertEquals($outputTime, $form->getData());
        $this->assertEquals('2010-06-02T03:04:00', $form->getViewData());
    }

    public function testSubmitStringSingleText()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'string',
            'widget' => 'single_text',
        ]);

        $form->submit('2010-06-02T03:04:00');

        $this->assertEquals('2010-06-02 03:04:00', $form->getData());
        $this->assertEquals('2010-06-02T03:04:00', $form->getViewData());
    }

    public function testSubmitStringSingleTextWithSeconds()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'string',
            'widget' => 'single_text',
            'with_seconds' => true,
        ]);

        $form->submit('2010-06-02T03:04:05');

        $this->assertEquals('2010-06-02 03:04:05', $form->getData());
        $this->assertEquals('2010-06-02T03:04:05', $form->getViewData());
    }

    public function testSubmitDifferentPattern()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'html5' => false,
            'date_format' => 'MM*yyyy*dd',
            'date_widget' => 'single_text',
            'time_widget' => 'single_text',
            'input' => 'datetime',
        ]);

        $dateTime = new \DateTime('2010-06-02 03:04');

        $form->submit([
            'date' => '06*2010*02',
            'time' => '03:04',
        ]);

        $this->assertEquals($dateTime, $form->getData());
    }

    public function testInitializeWithDateTime()
    {
        // Throws an exception if "data_class" option is not explicitly set
        // to null in the type
        $this->assertInstanceOf('Symfony\Component\Form\FormInterface', $this->factory->create(static::TESTED_TYPE, new \DateTime()));
    }

    public function testSingleTextWidgetShouldUseTheRightInputType()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
        ])
            ->createView();

        $this->assertEquals('datetime-local', $view->vars['type']);
    }

    public function testPassDefaultPlaceholderToViewIfNotRequired()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => false,
            'with_seconds' => true,
        ])
            ->createView();

        $this->assertSame('', $view['date']['year']->vars['placeholder']);
        $this->assertSame('', $view['date']['month']->vars['placeholder']);
        $this->assertSame('', $view['date']['day']->vars['placeholder']);
        $this->assertSame('', $view['time']['hour']->vars['placeholder']);
        $this->assertSame('', $view['time']['minute']->vars['placeholder']);
        $this->assertSame('', $view['time']['second']->vars['placeholder']);
    }

    public function testPassNoPlaceholderToViewIfRequired()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => true,
            'with_seconds' => true,
        ])
            ->createView();

        $this->assertNull($view['date']['year']->vars['placeholder']);
        $this->assertNull($view['date']['month']->vars['placeholder']);
        $this->assertNull($view['date']['day']->vars['placeholder']);
        $this->assertNull($view['time']['hour']->vars['placeholder']);
        $this->assertNull($view['time']['minute']->vars['placeholder']);
        $this->assertNull($view['time']['second']->vars['placeholder']);
    }

    public function testPassPlaceholderAsString()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'placeholder' => 'Empty',
            'with_seconds' => true,
        ])
            ->createView();

        $this->assertSame('Empty', $view['date']['year']->vars['placeholder']);
        $this->assertSame('Empty', $view['date']['month']->vars['placeholder']);
        $this->assertSame('Empty', $view['date']['day']->vars['placeholder']);
        $this->assertSame('Empty', $view['time']['hour']->vars['placeholder']);
        $this->assertSame('Empty', $view['time']['minute']->vars['placeholder']);
        $this->assertSame('Empty', $view['time']['second']->vars['placeholder']);
    }

    public function testPassPlaceholderAsArray()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'placeholder' => [
                'year' => 'Empty year',
                'month' => 'Empty month',
                'day' => 'Empty day',
                'hour' => 'Empty hour',
                'minute' => 'Empty minute',
                'second' => 'Empty second',
            ],
            'with_seconds' => true,
        ])
            ->createView();

        $this->assertSame('Empty year', $view['date']['year']->vars['placeholder']);
        $this->assertSame('Empty month', $view['date']['month']->vars['placeholder']);
        $this->assertSame('Empty day', $view['date']['day']->vars['placeholder']);
        $this->assertSame('Empty hour', $view['time']['hour']->vars['placeholder']);
        $this->assertSame('Empty minute', $view['time']['minute']->vars['placeholder']);
        $this->assertSame('Empty second', $view['time']['second']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddEmptyIfNotRequired()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => false,
            'placeholder' => [
                'year' => 'Empty year',
                'day' => 'Empty day',
                'hour' => 'Empty hour',
                'second' => 'Empty second',
            ],
            'with_seconds' => true,
        ])
            ->createView();

        $this->assertSame('Empty year', $view['date']['year']->vars['placeholder']);
        $this->assertSame('', $view['date']['month']->vars['placeholder']);
        $this->assertSame('Empty day', $view['date']['day']->vars['placeholder']);
        $this->assertSame('Empty hour', $view['time']['hour']->vars['placeholder']);
        $this->assertSame('', $view['time']['minute']->vars['placeholder']);
        $this->assertSame('Empty second', $view['time']['second']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddNullIfRequired()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => true,
            'placeholder' => [
                'year' => 'Empty year',
                'day' => 'Empty day',
                'hour' => 'Empty hour',
                'second' => 'Empty second',
            ],
            'with_seconds' => true,
        ])
            ->createView();

        $this->assertSame('Empty year', $view['date']['year']->vars['placeholder']);
        $this->assertNull($view['date']['month']->vars['placeholder']);
        $this->assertSame('Empty day', $view['date']['day']->vars['placeholder']);
        $this->assertSame('Empty hour', $view['time']['hour']->vars['placeholder']);
        $this->assertNull($view['time']['minute']->vars['placeholder']);
        $this->assertSame('Empty second', $view['time']['second']->vars['placeholder']);
    }

    public function testPassHtml5TypeIfSingleTextAndHtml5Format()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
        ])
            ->createView();

        $this->assertSame('datetime-local', $view->vars['type']);
    }

    public function testDontPassHtml5TypeIfHtml5NotAllowed()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
            'html5' => false,
        ])
            ->createView();

        $this->assertArrayNotHasKey('type', $view->vars);
    }

    public function testDontPassHtml5TypeIfNotSingleText()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'text',
        ])
            ->createView();

        $this->assertArrayNotHasKey('type', $view->vars);
    }

    public function testSingleTextWidgetWithCustomNonHtml5Format()
    {
        $form = $this->factory->create(static::TESTED_TYPE, new \DateTime('2019-02-13 19:12:13'), [
            'html5' => false,
            'widget' => 'single_text',
            'date_format' => \IntlDateFormatter::SHORT,
            'format' => null,
        ]);
        $view = $form->createView();

        $this->assertSame('2/13/19, 7:12:13 PM', $view->vars['value']);
    }

    public function testDateTypeChoiceErrorsBubbleUp()
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null);

        $form['date']->addError($error);

        $this->assertSame([], iterator_to_array($form['date']->getErrors()));
        $this->assertSame([$error], iterator_to_array($form->getErrors()));
    }

    public function testDateTypeSingleTextErrorsBubbleUp()
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'date_widget' => 'single_text',
        ]);

        $form['date']->addError($error);

        $this->assertSame([], iterator_to_array($form['date']->getErrors()));
        $this->assertSame([$error], iterator_to_array($form->getErrors()));
    }

    public function testTimeTypeChoiceErrorsBubbleUp()
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null);

        $form['time']->addError($error);

        $this->assertSame([], iterator_to_array($form['time']->getErrors()));
        $this->assertSame([$error], iterator_to_array($form->getErrors()));
    }

    public function testTimeTypeSingleTextErrorsBubbleUp()
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'time_widget' => 'single_text',
        ]);

        $form['time']->addError($error);

        $this->assertSame([], iterator_to_array($form['time']->getErrors()));
        $this->assertSame([$error], iterator_to_array($form->getErrors()));
    }

    public function testPassDefaultChoiceTranslationDomain()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'with_seconds' => true,
        ]);

        $view = $form->createView();

        $this->assertFalse($view['date']['year']->vars['choice_translation_domain']);
        $this->assertFalse($view['date']['month']->vars['choice_translation_domain']);
        $this->assertFalse($view['date']['day']->vars['choice_translation_domain']);
        $this->assertFalse($view['time']['hour']->vars['choice_translation_domain']);
        $this->assertFalse($view['time']['minute']->vars['choice_translation_domain']);
        $this->assertFalse($view['time']['second']->vars['choice_translation_domain']);
    }

    public function testPassChoiceTranslationDomainAsString()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'choice_translation_domain' => 'messages',
            'with_seconds' => true,
        ]);

        $view = $form->createView();
        $this->assertSame('messages', $view['date']['year']->vars['choice_translation_domain']);
        $this->assertSame('messages', $view['date']['month']->vars['choice_translation_domain']);
        $this->assertSame('messages', $view['date']['day']->vars['choice_translation_domain']);
        $this->assertSame('messages', $view['time']['hour']->vars['choice_translation_domain']);
        $this->assertSame('messages', $view['time']['minute']->vars['choice_translation_domain']);
        $this->assertSame('messages', $view['time']['second']->vars['choice_translation_domain']);
    }

    public function testPassChoiceTranslationDomainAsArray()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'choice_translation_domain' => [
                'year' => 'foo',
                'month' => 'test',
                'hour' => 'foo',
                'second' => 'test',
            ],
            'with_seconds' => true,
        ]);

        $view = $form->createView();
        $this->assertSame('foo', $view['date']['year']->vars['choice_translation_domain']);
        $this->assertSame('test', $view['date']['month']->vars['choice_translation_domain']);
        $this->assertFalse($view['date']['day']->vars['choice_translation_domain']);
        $this->assertSame('foo', $view['time']['hour']->vars['choice_translation_domain']);
        $this->assertFalse($view['time']['minute']->vars['choice_translation_domain']);
        $this->assertSame('test', $view['time']['second']->vars['choice_translation_domain']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, [
            // View data is an array of choice values array
            'date' => ['year' => '', 'month' => '', 'day' => ''],
            'time' => ['hour' => '', 'minute' => ''],
        ]);
    }

    public function testSubmitNullWithText()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'text',
        ]);
        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame([
            // View data is an array of choice values array
            'date' => ['year' => '', 'month' => '', 'day' => ''],
            'time' => ['hour' => '', 'minute' => ''],
        ], $form->getViewData());
    }

    public function testSubmitNullWithSingleText()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
        ]);
        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = [], $expectedData = null)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        // view transformer writes back empty strings in the view data
        $this->assertSame(
            ['date' => ['year' => '', 'month' => '', 'day' => ''], 'time' => ['hour' => '', 'minute' => '']],
            $form->getViewData()
        );
        $this->assertSame($expectedData, $form->getNormData());
        $this->assertSame($expectedData, $form->getData());
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

        $this->assertSame($emptyData, $form->getViewData());
        $this->assertEquals($expectedData, $form->getNormData());
        $this->assertEquals($expectedData, $form->getData());
    }

    public function provideEmptyData()
    {
        $expectedData = \DateTime::createFromFormat('Y-m-d H:i', '2018-11-11 21:23');

        return [
            'Simple field' => ['single_text', '2018-11-11T21:23:00', $expectedData],
            'Compound text field' => ['text', ['date' => ['year' => '2018', 'month' => '11', 'day' => '11'], 'time' => ['hour' => '21', 'minute' => '23']], $expectedData],
            'Compound choice field' => ['choice', ['date' => ['year' => '2018', 'month' => '11', 'day' => '11'], 'time' => ['hour' => '21', 'minute' => '23']], $expectedData],
        ];
    }

    public function testSubmitStringWithCustomInputFormat(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'input' => 'string',
            'widget' => 'single_text',
            'input_format' => 'd/m/Y H:i:s P',
        ]);

        $form->submit('2018-01-14T21:29:00');

        $this->assertSame('14/01/2018 21:29:00 +00:00', $form->getData());
    }
}
