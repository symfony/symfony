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

use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Intl\Util\IntlTestHelper;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

class DateTypeTest extends BaseTypeTest
{
    public const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\DateType';

    private $defaultTimezone;
    private $defaultLocale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultTimezone = date_default_timezone_get();
        $this->defaultLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimezone);
        \Locale::setDefault($this->defaultLocale);
    }

    public function testInvalidWidgetOption()
    {
        self::expectException(InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'fake_widget',
        ]);
    }

    public function testInvalidInputOption()
    {
        self::expectException(InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'input' => 'fake_input',
        ]);
    }

    public function testSubmitFromSingleTextDateTimeWithDefaultFormat()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'datetime',
        ]);

        $form->submit('2010-06-02');

        self::assertEquals(new \DateTime('2010-06-02 UTC'), $form->getData());
        self::assertEquals('2010-06-02', $form->getViewData());
    }

    public function testSubmitFromSingleTextDateTimeWithCustomFormat()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'datetime',
            'format' => 'yyyy',
            'html5' => false,
        ]);

        $form->submit('2010');

        self::assertEquals(new \DateTime('2010-01-01 UTC'), $form->getData());
        self::assertEquals('2010', $form->getViewData());
    }

    public function testSubmitFromSingleTextDateTime()
    {
        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'format' => \IntlDateFormatter::MEDIUM,
            'html5' => false,
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'datetime',
        ]);

        $form->submit('2.6.2010');

        self::assertEquals(new \DateTime('2010-06-02 UTC'), $form->getData());
        self::assertEquals('02.06.2010', $form->getViewData());
    }

    public function testSubmitFromSingleTextDateTimeImmutable()
    {
        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'format' => \IntlDateFormatter::MEDIUM,
            'html5' => false,
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
        ]);

        $form->submit('2.6.2010');

        self::assertInstanceOf(\DateTimeImmutable::class, $form->getData());
        self::assertEquals(new \DateTimeImmutable('2010-06-02 UTC'), $form->getData());
        self::assertEquals('02.06.2010', $form->getViewData());
    }

    public function testSubmitFromSingleTextString()
    {
        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'format' => \IntlDateFormatter::MEDIUM,
            'html5' => false,
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'string',
        ]);

        $form->submit('2.6.2010');

        self::assertEquals('2010-06-02', $form->getData());
        self::assertEquals('02.06.2010', $form->getViewData());
    }

    public function testSubmitFromSingleTextTimestamp()
    {
        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'format' => \IntlDateFormatter::MEDIUM,
            'html5' => false,
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'timestamp',
        ]);

        $form->submit('2.6.2010');

        $dateTime = new \DateTime('2010-06-02 UTC');

        self::assertEquals($dateTime->format('U'), $form->getData());
        self::assertEquals('02.06.2010', $form->getViewData());
    }

    public function testSubmitFromSingleTextRaw()
    {
        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'format' => \IntlDateFormatter::MEDIUM,
            'html5' => false,
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'array',
        ]);

        $form->submit('2.6.2010');

        $output = [
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        ];

        self::assertEquals($output, $form->getData());
        self::assertEquals('02.06.2010', $form->getViewData());
    }

    public function testArrayDateWithReferenceDoesUseReferenceTimeOnZero()
    {
        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $input = [
            'day' => '0',
            'month' => '0',
            'year' => '0',
        ];

        $form = $this->factory->create(static::TESTED_TYPE, $input, [
            'format' => \IntlDateFormatter::MEDIUM,
            'html5' => false,
            'model_timezone' => 'UTC',
            'view_timezone' => 'Europe/Berlin',
            'input' => 'array',
            'widget' => 'single_text',
        ]);

        self::assertSame($input, $form->getData());
        self::assertEquals('01.01.1970', $form->getViewData());
    }

    public function testSubmitFromText()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'text',
        ]);

        $text = [
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        ];

        $form->submit($text);

        $dateTime = new \DateTime('2010-06-02 UTC');

        self::assertEquals($dateTime, $form->getData());
        self::assertEquals($text, $form->getViewData());
    }

    public function testSubmitFromChoice()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'choice',
            'years' => [2010],
        ]);

        $text = [
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        ];

        $form->submit($text);

        $dateTime = new \DateTime('2010-06-02 UTC');

        self::assertEquals($dateTime, $form->getData());
        self::assertEquals($text, $form->getViewData());
    }

    public function testSubmitFromChoiceEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'choice',
            'required' => false,
        ]);

        $text = [
            'day' => '',
            'month' => '',
            'year' => '',
        ];

        $form->submit($text);

        self::assertNull($form->getData());
        self::assertEquals($text, $form->getViewData());
    }

    public function testSubmitFromInputDateTimeDifferentPattern()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'format' => 'MM*yyyy*dd',
            'html5' => false,
            'widget' => 'single_text',
            'input' => 'datetime',
        ]);

        $form->submit('06*2010*02');

        self::assertEquals(new \DateTime('2010-06-02 UTC'), $form->getData());
        self::assertEquals('06*2010*02', $form->getViewData());
    }

    public function testSubmitFromInputStringDifferentPattern()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'format' => 'MM*yyyy*dd',
            'html5' => false,
            'widget' => 'single_text',
            'input' => 'string',
        ]);

        $form->submit('06*2010*02');

        self::assertEquals('2010-06-02', $form->getData());
        self::assertEquals('06*2010*02', $form->getViewData());
    }

    public function testSubmitFromInputTimestampDifferentPattern()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'format' => 'MM*yyyy*dd',
            'html5' => false,
            'widget' => 'single_text',
            'input' => 'timestamp',
        ]);

        $form->submit('06*2010*02');

        $dateTime = new \DateTime('2010-06-02 UTC');

        self::assertEquals($dateTime->format('U'), $form->getData());
        self::assertEquals('06*2010*02', $form->getViewData());
    }

    public function testSubmitFromInputRawDifferentPattern()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'format' => 'MM*yyyy*dd',
            'html5' => false,
            'widget' => 'single_text',
            'input' => 'array',
        ]);

        $form->submit('06*2010*02');

        $output = [
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        ];

        self::assertEquals($output, $form->getData());
        self::assertEquals('06*2010*02', $form->getViewData());
    }

    /**
     * @dataProvider provideDateFormats
     */
    public function testDatePatternWithFormatOption($format, $pattern)
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'format' => $format,
        ])
            ->createView();

        self::assertEquals($pattern, $view->vars['date_pattern']);
    }

    public function provideDateFormats()
    {
        return [
            ['dMy', '{{ day }}{{ month }}{{ year }}'],
            ['d-M-yyyy', '{{ day }}-{{ month }}-{{ year }}'],
            ['M d y', '{{ month }} {{ day }} {{ year }}'],
        ];
    }

    /**
     * This test is to check that the strings '0', '1', '2', '3' are not accepted
     * as valid IntlDateFormatter constants for FULL, LONG, MEDIUM or SHORT respectively.
     */
    public function testThrowExceptionIfFormatIsNoPattern()
    {
        self::expectException(InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'format' => '0',
            'html5' => false,
            'widget' => 'single_text',
            'input' => 'string',
        ]);
    }

    public function testThrowExceptionIfFormatDoesNotContainYearMonthAndDay()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The "format" option should contain the letters "y", "M" and "d". Its current value is "yy".');
        $this->factory->create(static::TESTED_TYPE, null, [
            'months' => [6, 7],
            'format' => 'yy',
        ]);
    }

    public function testThrowExceptionIfFormatMissesYearMonthAndDayWithSingleTextWidget()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The "format" option should contain the letters "y", "M" or "d". Its current value is "wrong".');
        $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
            'format' => 'wrong',
            'html5' => false,
        ]);
    }

    public function testThrowExceptionIfFormatIsNoConstant()
    {
        self::expectException(InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'format' => 105,
        ]);
    }

    public function testThrowExceptionIfFormatIsInvalid()
    {
        self::expectException(InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'format' => [],
        ]);
    }

    public function testThrowExceptionIfYearsIsInvalid()
    {
        self::expectException(InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'years' => 'bad value',
        ]);
    }

    public function testThrowExceptionIfMonthsIsInvalid()
    {
        self::expectException(InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'months' => 'bad value',
        ]);
    }

    public function testThrowExceptionIfDaysIsInvalid()
    {
        self::expectException(InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'days' => 'bad value',
        ]);
    }

    public function testSetDataWithNegativeTimezoneOffsetStringInput()
    {
        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'format' => \IntlDateFormatter::MEDIUM,
            'html5' => false,
            'model_timezone' => 'UTC',
            'view_timezone' => 'America/New_York',
            'input' => 'string',
            'widget' => 'single_text',
        ]);

        $form->setData('2010-06-02');

        // 2010-06-02 00:00:00 UTC
        // 2010-06-01 20:00:00 UTC-4
        self::assertEquals('01.06.2010', $form->getViewData());
    }

    public function testSetDataWithNegativeTimezoneOffsetDateTimeInput()
    {
        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'format' => \IntlDateFormatter::MEDIUM,
            'html5' => false,
            'model_timezone' => 'UTC',
            'view_timezone' => 'America/New_York',
            'input' => 'datetime',
            'widget' => 'single_text',
        ]);

        $dateTime = new \DateTime('2010-06-02 UTC');

        $form->setData($dateTime);

        // 2010-06-02 00:00:00 UTC
        // 2010-06-01 20:00:00 UTC-4
        self::assertEquals($dateTime, $form->getData());
        self::assertEquals('01.06.2010', $form->getViewData());
    }

    public function testYearsOption()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'years' => [2010, 2011],
        ]);

        $view = $form->createView();

        self::assertEquals([
            new ChoiceView('2010', '2010', '2010'),
            new ChoiceView('2011', '2011', '2011'),
        ], $view['year']->vars['choices']);
    }

    public function testMonthsOption()
    {
        \Locale::setDefault('en');
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'months' => [6, 7],
            'format' => \IntlDateFormatter::SHORT,
        ]);

        $view = $form->createView();

        self::assertEquals([
            new ChoiceView(6, '6', '6'),
            new ChoiceView(7, '7', '7'),
        ], $view['month']->vars['choices']);
    }

    public function testMonthsOptionShortFormat()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, '57.1');

        \Locale::setDefault('de_AT');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'months' => [1, 4],
            'format' => 'dd.MMM.yy',
        ]);

        $view = $form->createView();

        self::assertEquals([
            new ChoiceView(1, '1', 'Jän.'),
            new ChoiceView(4, '4', 'Apr.'),
        ], $view['month']->vars['choices']);
    }

    public function testMonthsOptionLongFormat()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'months' => [1, 4],
            'format' => 'dd.MMMM.yy',
        ])
            ->createView();

        self::assertEquals([
            new ChoiceView(1, '1', 'Jänner'),
            new ChoiceView(4, '4', 'April'),
        ], $view['month']->vars['choices']);
    }

    public function testMonthsOptionLongFormatWithDifferentTimezone()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'months' => [1, 4],
            'format' => 'dd.MMMM.yy',
        ])
            ->createView();

        self::assertEquals([
            new ChoiceView(1, '1', 'Jänner'),
            new ChoiceView(4, '4', 'April'),
        ], $view['month']->vars['choices']);
    }

    public function testIsDayWithinRangeReturnsTrueIfWithin()
    {
        \Locale::setDefault('en');
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'days' => [6, 7],
        ])
            ->createView();

        self::assertEquals([
            new ChoiceView(6, '6', '6'),
            new ChoiceView(7, '7', '7'),
        ], $view['day']->vars['choices']);
    }

    public function testIsSynchronizedReturnsTrueIfChoiceAndCompletelyEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'choice',
        ]);

        $form->submit([
            'day' => '',
            'month' => '',
            'year' => '',
        ]);

        self::assertTrue($form->isSynchronized());
    }

    public function testIsSynchronizedReturnsTrueIfChoiceAndCompletelyFilled()
    {
        $form = $this->factory->create(static::TESTED_TYPE, new \DateTime(), [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'choice',
        ]);

        $form->submit([
            'day' => '1',
            'month' => '6',
            'year' => '2010',
        ]);

        self::assertTrue($form->isSynchronized());
    }

    public function testIsSynchronizedReturnsFalseIfChoiceAndDayEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'choice',
        ]);

        $form->submit([
            'day' => '',
            'month' => '6',
            'year' => '2010',
        ]);

        self::assertFalse($form->isSynchronized());
    }

    public function testPassDatePatternToView()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $view = $this->factory->create(static::TESTED_TYPE)
            ->createView();

        self::assertSame('{{ day }}{{ month }}{{ year }}', $view->vars['date_pattern']);
    }

    public function testPassDatePatternToViewDifferentFormat()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'format' => \IntlDateFormatter::LONG,
        ])
            ->createView();

        self::assertSame('{{ day }}{{ month }}{{ year }}', $view->vars['date_pattern']);
    }

    public function testPassDatePatternToViewDifferentPattern()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'format' => 'MMyyyydd',
        ])
            ->createView();

        self::assertSame('{{ month }}{{ year }}{{ day }}', $view->vars['date_pattern']);
    }

    public function testPassDatePatternToViewDifferentPatternWithSeparators()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'format' => 'MM*yyyy*dd',
        ])
            ->createView();

        self::assertSame('{{ month }}*{{ year }}*{{ day }}', $view->vars['date_pattern']);
    }

    public function testDontPassDatePatternIfText()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
        ])
            ->createView();

        self::assertArrayNotHasKey('date_pattern', $view->vars);
    }

    public function testDatePatternFormatWithQuotedStrings()
    {
        // we test against "es_ES", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('es_ES');

        $view = $this->factory->create(static::TESTED_TYPE, null, [
            // EEEE, d 'de' MMMM 'de' y
            'format' => \IntlDateFormatter::FULL,
        ])
            ->createView();

        self::assertEquals('{{ day }}{{ month }}{{ year }}', $view->vars['date_pattern']);
    }

    public function testPassWidgetToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
        ])
            ->createView();

        self::assertSame('single_text', $view->vars['widget']);
    }

    public function testInitializeWithDateTime()
    {
        // Throws an exception if "data_class" option is not explicitly set
        // to null in the type
        self::assertInstanceOf(FormInterface::class, $this->factory->create(static::TESTED_TYPE, new \DateTime()));
    }

    public function testSingleTextWidgetShouldUseTheRightInputType()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
        ])
            ->createView();

        self::assertEquals('date', $view->vars['type']);
    }

    public function testPassDefaultPlaceholderToViewIfNotRequired()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => false,
        ])
            ->createView();

        self::assertSame('', $view['year']->vars['placeholder']);
        self::assertSame('', $view['month']->vars['placeholder']);
        self::assertSame('', $view['day']->vars['placeholder']);
    }

    public function testPassNoPlaceholderToViewIfRequired()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => true,
        ])
            ->createView();

        self::assertNull($view['year']->vars['placeholder']);
        self::assertNull($view['month']->vars['placeholder']);
        self::assertNull($view['day']->vars['placeholder']);
    }

    public function testPassPlaceholderAsString()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'placeholder' => 'Empty',
        ])
            ->createView();

        self::assertSame('Empty', $view['year']->vars['placeholder']);
        self::assertSame('Empty', $view['month']->vars['placeholder']);
        self::assertSame('Empty', $view['day']->vars['placeholder']);
    }

    public function testPassPlaceholderAsArray()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'placeholder' => [
                'year' => 'Empty year',
                'month' => 'Empty month',
                'day' => 'Empty day',
            ],
        ])
            ->createView();

        self::assertSame('Empty year', $view['year']->vars['placeholder']);
        self::assertSame('Empty month', $view['month']->vars['placeholder']);
        self::assertSame('Empty day', $view['day']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddEmptyIfNotRequired()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => false,
            'placeholder' => [
                'year' => 'Empty year',
                'day' => 'Empty day',
            ],
        ])
            ->createView();

        self::assertSame('Empty year', $view['year']->vars['placeholder']);
        self::assertSame('', $view['month']->vars['placeholder']);
        self::assertSame('Empty day', $view['day']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddNullIfRequired()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => true,
            'placeholder' => [
                'year' => 'Empty year',
                'day' => 'Empty day',
            ],
        ])
            ->createView();

        self::assertSame('Empty year', $view['year']->vars['placeholder']);
        self::assertNull($view['month']->vars['placeholder']);
        self::assertSame('Empty day', $view['day']->vars['placeholder']);
    }

    public function testPassHtml5TypeIfSingleTextAndHtml5Format()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
        ])
            ->createView();

        self::assertSame('date', $view->vars['type']);
    }

    public function testDontPassHtml5TypeIfHtml5NotAllowed()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
            'html5' => false,
        ])
            ->createView();

        self::assertArrayNotHasKey('type', $view->vars);
    }

    public function testDontPassHtml5TypeIfNotHtml5Format()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
            'format' => \IntlDateFormatter::MEDIUM,
            'html5' => false,
        ])
            ->createView();

        self::assertArrayNotHasKey('type', $view->vars);
    }

    public function testDontPassHtml5TypeIfNotSingleText()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'text',
        ])
            ->createView();

        self::assertArrayNotHasKey('type', $view->vars);
    }

    public function provideCompoundWidgets()
    {
        return [
            ['text'],
            ['choice'],
        ];
    }

    /**
     * @dataProvider provideCompoundWidgets
     */
    public function testYearErrorsBubbleUp($widget)
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => $widget,
        ]);
        $form['year']->addError($error);

        self::assertSame([], iterator_to_array($form['year']->getErrors()));
        self::assertSame([$error], iterator_to_array($form->getErrors()));
    }

    /**
     * @dataProvider provideCompoundWidgets
     */
    public function testMonthErrorsBubbleUp($widget)
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => $widget,
        ]);
        $form['month']->addError($error);

        self::assertSame([], iterator_to_array($form['month']->getErrors()));
        self::assertSame([$error], iterator_to_array($form->getErrors()));
    }

    /**
     * @dataProvider provideCompoundWidgets
     */
    public function testDayErrorsBubbleUp($widget)
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => $widget,
        ]);
        $form['day']->addError($error);

        self::assertSame([], iterator_to_array($form['day']->getErrors()));
        self::assertSame([$error], iterator_to_array($form->getErrors()));
    }

    public function testYears()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'years' => [1900, 2000, 2040],
        ])
            ->createView();

        $listChoices = [];
        foreach ([1900, 2000, 2040] as $y) {
            $listChoices[] = new ChoiceView($y, $y, $y);
        }

        self::assertEquals($listChoices, $view['year']->vars['choices']);
    }

    public function testPassDefaultChoiceTranslationDomain()
    {
        $form = $this->factory->create(static::TESTED_TYPE);

        $view = $form->createView();
        self::assertFalse($view['year']->vars['choice_translation_domain']);
        self::assertFalse($view['month']->vars['choice_translation_domain']);
        self::assertFalse($view['day']->vars['choice_translation_domain']);
    }

    public function testPassChoiceTranslationDomainAsString()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'choice_translation_domain' => 'messages',
        ]);

        $view = $form->createView();
        self::assertSame('messages', $view['year']->vars['choice_translation_domain']);
        self::assertSame('messages', $view['month']->vars['choice_translation_domain']);
        self::assertSame('messages', $view['day']->vars['choice_translation_domain']);
    }

    public function testPassChoiceTranslationDomainAsArray()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'choice_translation_domain' => [
                'year' => 'foo',
                'day' => 'test',
            ],
        ]);

        $view = $form->createView();
        self::assertSame('foo', $view['year']->vars['choice_translation_domain']);
        self::assertFalse($view['month']->vars['choice_translation_domain']);
        self::assertSame('test', $view['day']->vars['choice_translation_domain']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, ['year' => '', 'month' => '', 'day' => '']);
    }

    public function testSubmitNullWithSingleText()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
        ]);
        $form->submit(null);

        self::assertNull($form->getData());
        self::assertNull($form->getNormData());
        self::assertSame('', $form->getViewData());
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = [], $expectedData = null)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        // view transformer writes back empty strings in the view data
        self::assertSame(['year' => '', 'month' => '', 'day' => ''], $form->getViewData());
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

        if ($emptyData instanceof \Closure) {
            $emptyData = $emptyData($form);
        }
        self::assertSame($emptyData, $form->getViewData());
        self::assertEquals($expectedData, $form->getNormData());
        self::assertEquals($expectedData, $form->getData());
    }

    public function provideEmptyData()
    {
        $expectedData = \DateTime::createFromFormat('Y-m-d H:i:s', '2018-11-11 00:00:00');
        $lazyEmptyData = static function (FormInterface $form) {
            return $form->getConfig()->getCompound() ? ['year' => '2018', 'month' => '11', 'day' => '11'] : '2018-11-11';
        };

        return [
            'Simple field' => ['single_text', '2018-11-11', $expectedData],
            'Compound text fields' => ['text', ['year' => '2018', 'month' => '11', 'day' => '11'], $expectedData],
            'Compound choice fields' => ['choice', ['year' => '2018', 'month' => '11', 'day' => '11'], $expectedData],
            'Simple field lazy' => ['single_text', $lazyEmptyData, $expectedData],
            'Compound text fields lazy' => ['text', $lazyEmptyData, $expectedData],
            'Compound choice fields lazy' => ['choice', $lazyEmptyData, $expectedData],
        ];
    }

    public function testSubmitStringWithCustomInputFormat()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'string',
            'input_format' => 'd/m/Y',
        ]);

        $form->submit('2018-01-14');

        self::assertSame('14/01/2018', $form->getData());
    }
}
