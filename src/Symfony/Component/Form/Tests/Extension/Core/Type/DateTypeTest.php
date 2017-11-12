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
use Symfony\Component\Intl\Util\IntlTestHelper;

class DateTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\DateType';

    private $defaultTimezone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimezone);
        \Locale::setDefault('en');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testInvalidWidgetOption(): void
    {
        $this->factory->create(static::TESTED_TYPE, null, array(
            'widget' => 'fake_widget',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testInvalidInputOption(): void
    {
        $this->factory->create(static::TESTED_TYPE, null, array(
            'input' => 'fake_input',
        ));
    }

    public function testSubmitFromSingleTextDateTimeWithDefaultFormat(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'datetime',
        ));

        $form->submit('2010-06-02');

        $this->assertDateTimeEquals(new \DateTime('2010-06-02 UTC'), $form->getData());
        $this->assertEquals('2010-06-02', $form->getViewData());
    }

    public function testSubmitFromSingleTextDateTimeWithCustomFormat(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'datetime',
            'format' => 'yyyy',
        ));

        $form->submit('2010');

        $this->assertDateTimeEquals(new \DateTime('2010-01-01 UTC'), $form->getData());
        $this->assertEquals('2010', $form->getViewData());
    }

    public function testSubmitFromSingleTextDateTime(): void
    {
        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'format' => \IntlDateFormatter::MEDIUM,
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'datetime',
        ));

        $form->submit('2.6.2010');

        $this->assertDateTimeEquals(new \DateTime('2010-06-02 UTC'), $form->getData());
        $this->assertEquals('02.06.2010', $form->getViewData());
    }

    public function testSubmitFromSingleTextString(): void
    {
        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'format' => \IntlDateFormatter::MEDIUM,
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'string',
        ));

        $form->submit('2.6.2010');

        $this->assertEquals('2010-06-02', $form->getData());
        $this->assertEquals('02.06.2010', $form->getViewData());
    }

    public function testSubmitFromSingleTextTimestamp(): void
    {
        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'format' => \IntlDateFormatter::MEDIUM,
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'timestamp',
        ));

        $form->submit('2.6.2010');

        $dateTime = new \DateTime('2010-06-02 UTC');

        $this->assertEquals($dateTime->format('U'), $form->getData());
        $this->assertEquals('02.06.2010', $form->getViewData());
    }

    public function testSubmitFromSingleTextRaw(): void
    {
        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'format' => \IntlDateFormatter::MEDIUM,
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'array',
        ));

        $form->submit('2.6.2010');

        $output = array(
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        );

        $this->assertEquals($output, $form->getData());
        $this->assertEquals('02.06.2010', $form->getViewData());
    }

    public function testSubmitFromText(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'text',
        ));

        $text = array(
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        );

        $form->submit($text);

        $dateTime = new \DateTime('2010-06-02 UTC');

        $this->assertDateTimeEquals($dateTime, $form->getData());
        $this->assertEquals($text, $form->getViewData());
    }

    public function testSubmitFromChoice(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'choice',
            'years' => array(2010),
        ));

        $text = array(
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        );

        $form->submit($text);

        $dateTime = new \DateTime('2010-06-02 UTC');

        $this->assertDateTimeEquals($dateTime, $form->getData());
        $this->assertEquals($text, $form->getViewData());
    }

    public function testSubmitFromChoiceEmpty(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'choice',
            'required' => false,
        ));

        $text = array(
            'day' => '',
            'month' => '',
            'year' => '',
        );

        $form->submit($text);

        $this->assertNull($form->getData());
        $this->assertEquals($text, $form->getViewData());
    }

    public function testSubmitFromInputDateTimeDifferentPattern(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'format' => 'MM*yyyy*dd',
            'widget' => 'single_text',
            'input' => 'datetime',
        ));

        $form->submit('06*2010*02');

        $this->assertDateTimeEquals(new \DateTime('2010-06-02 UTC'), $form->getData());
        $this->assertEquals('06*2010*02', $form->getViewData());
    }

    public function testSubmitFromInputStringDifferentPattern(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'format' => 'MM*yyyy*dd',
            'widget' => 'single_text',
            'input' => 'string',
        ));

        $form->submit('06*2010*02');

        $this->assertEquals('2010-06-02', $form->getData());
        $this->assertEquals('06*2010*02', $form->getViewData());
    }

    public function testSubmitFromInputTimestampDifferentPattern(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'format' => 'MM*yyyy*dd',
            'widget' => 'single_text',
            'input' => 'timestamp',
        ));

        $form->submit('06*2010*02');

        $dateTime = new \DateTime('2010-06-02 UTC');

        $this->assertEquals($dateTime->format('U'), $form->getData());
        $this->assertEquals('06*2010*02', $form->getViewData());
    }

    public function testSubmitFromInputRawDifferentPattern(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'format' => 'MM*yyyy*dd',
            'widget' => 'single_text',
            'input' => 'array',
        ));

        $form->submit('06*2010*02');

        $output = array(
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        );

        $this->assertEquals($output, $form->getData());
        $this->assertEquals('06*2010*02', $form->getViewData());
    }

    /**
     * @dataProvider provideDateFormats
     */
    public function testDatePatternWithFormatOption($format, $pattern): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'format' => $format,
        ))
            ->createView();

        $this->assertEquals($pattern, $view->vars['date_pattern']);
    }

    public function provideDateFormats()
    {
        return array(
            array('dMy', '{{ day }}{{ month }}{{ year }}'),
            array('d-M-yyyy', '{{ day }}-{{ month }}-{{ year }}'),
            array('M d y', '{{ month }} {{ day }} {{ year }}'),
        );
    }

    /**
     * This test is to check that the strings '0', '1', '2', '3' are not accepted
     * as valid IntlDateFormatter constants for FULL, LONG, MEDIUM or SHORT respectively.
     *
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testThrowExceptionIfFormatIsNoPattern(): void
    {
        $this->factory->create(static::TESTED_TYPE, null, array(
            'format' => '0',
            'widget' => 'single_text',
            'input' => 'string',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The "format" option should contain the letters "y", "M" and "d". Its current value is "yy".
     */
    public function testThrowExceptionIfFormatDoesNotContainYearMonthAndDay(): void
    {
        $this->factory->create(static::TESTED_TYPE, null, array(
            'months' => array(6, 7),
            'format' => 'yy',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The "format" option should contain the letters "y", "M" or "d". Its current value is "wrong".
     */
    public function testThrowExceptionIfFormatMissesYearMonthAndDayWithSingleTextWidget(): void
    {
        $this->factory->create(static::TESTED_TYPE, null, array(
            'widget' => 'single_text',
            'format' => 'wrong',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testThrowExceptionIfFormatIsNoConstant(): void
    {
        $this->factory->create(static::TESTED_TYPE, null, array(
            'format' => 105,
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testThrowExceptionIfFormatIsInvalid(): void
    {
        $this->factory->create(static::TESTED_TYPE, null, array(
            'format' => array(),
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testThrowExceptionIfYearsIsInvalid(): void
    {
        $this->factory->create(static::TESTED_TYPE, null, array(
            'years' => 'bad value',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testThrowExceptionIfMonthsIsInvalid(): void
    {
        $this->factory->create(static::TESTED_TYPE, null, array(
            'months' => 'bad value',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testThrowExceptionIfDaysIsInvalid(): void
    {
        $this->factory->create(static::TESTED_TYPE, null, array(
            'days' => 'bad value',
        ));
    }

    public function testSetDataWithNegativeTimezoneOffsetStringInput(): void
    {
        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'format' => \IntlDateFormatter::MEDIUM,
            'model_timezone' => 'UTC',
            'view_timezone' => 'America/New_York',
            'input' => 'string',
            'widget' => 'single_text',
        ));

        $form->setData('2010-06-02');

        // 2010-06-02 00:00:00 UTC
        // 2010-06-01 20:00:00 UTC-4
        $this->assertEquals('01.06.2010', $form->getViewData());
    }

    public function testSetDataWithNegativeTimezoneOffsetDateTimeInput(): void
    {
        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_DE');

        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'format' => \IntlDateFormatter::MEDIUM,
            'model_timezone' => 'UTC',
            'view_timezone' => 'America/New_York',
            'input' => 'datetime',
            'widget' => 'single_text',
        ));

        $dateTime = new \DateTime('2010-06-02 UTC');

        $form->setData($dateTime);

        // 2010-06-02 00:00:00 UTC
        // 2010-06-01 20:00:00 UTC-4
        $this->assertDateTimeEquals($dateTime, $form->getData());
        $this->assertEquals('01.06.2010', $form->getViewData());
    }

    public function testYearsOption(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'years' => array(2010, 2011),
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView('2010', '2010', '2010'),
            new ChoiceView('2011', '2011', '2011'),
        ), $view['year']->vars['choices']);
    }

    public function testMonthsOption(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'months' => array(6, 7),
            'format' => \IntlDateFormatter::SHORT,
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView(6, '6', '06'),
            new ChoiceView(7, '7', '07'),
        ), $view['month']->vars['choices']);
    }

    public function testMonthsOptionShortFormat(): void
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, '57.1');

        \Locale::setDefault('de_AT');

        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'months' => array(1, 4),
            'format' => 'dd.MMM.yy',
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView(1, '1', 'Jän.'),
            new ChoiceView(4, '4', 'Apr.'),
        ), $view['month']->vars['choices']);
    }

    public function testMonthsOptionLongFormat(): void
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'months' => array(1, 4),
            'format' => 'dd.MMMM.yy',
        ))
            ->createView();

        $this->assertEquals(array(
            new ChoiceView(1, '1', 'Jänner'),
            new ChoiceView(4, '4', 'April'),
        ), $view['month']->vars['choices']);
    }

    public function testMonthsOptionLongFormatWithDifferentTimezone(): void
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'months' => array(1, 4),
            'format' => 'dd.MMMM.yy',
        ))
            ->createView();

        $this->assertEquals(array(
            new ChoiceView(1, '1', 'Jänner'),
            new ChoiceView(4, '4', 'April'),
        ), $view['month']->vars['choices']);
    }

    public function testIsDayWithinRangeReturnsTrueIfWithin(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'days' => array(6, 7),
        ))
            ->createView();

        $this->assertEquals(array(
            new ChoiceView(6, '6', '06'),
            new ChoiceView(7, '7', '07'),
        ), $view['day']->vars['choices']);
    }

    public function testIsSynchronizedReturnsTrueIfChoiceAndCompletelyEmpty(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'choice',
        ));

        $form->submit(array(
            'day' => '',
            'month' => '',
            'year' => '',
        ));

        $this->assertTrue($form->isSynchronized());
    }

    public function testIsSynchronizedReturnsTrueIfChoiceAndCompletelyFilled(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, new \DateTime(), array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'choice',
        ));

        $form->submit(array(
            'day' => '1',
            'month' => '6',
            'year' => '2010',
        ));

        $this->assertTrue($form->isSynchronized());
    }

    public function testIsSynchronizedReturnsFalseIfChoiceAndDayEmpty(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'choice',
        ));

        $form->submit(array(
            'day' => '',
            'month' => '6',
            'year' => '2010',
        ));

        $this->assertFalse($form->isSynchronized());
    }

    public function testPassDatePatternToView(): void
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $view = $this->factory->create(static::TESTED_TYPE)
            ->createView();

        $this->assertSame('{{ day }}{{ month }}{{ year }}', $view->vars['date_pattern']);
    }

    public function testPassDatePatternToViewDifferentFormat(): void
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_AT');

        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'format' => \IntlDateFormatter::LONG,
        ))
            ->createView();

        $this->assertSame('{{ day }}{{ month }}{{ year }}', $view->vars['date_pattern']);
    }

    public function testPassDatePatternToViewDifferentPattern(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'format' => 'MMyyyydd',
        ))
            ->createView();

        $this->assertSame('{{ month }}{{ year }}{{ day }}', $view->vars['date_pattern']);
    }

    public function testPassDatePatternToViewDifferentPatternWithSeparators(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'format' => 'MM*yyyy*dd',
        ))
            ->createView();

        $this->assertSame('{{ month }}*{{ year }}*{{ day }}', $view->vars['date_pattern']);
    }

    public function testDontPassDatePatternIfText(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'widget' => 'single_text',
        ))
            ->createView();

        $this->assertFalse(isset($view->vars['date_pattern']));
    }

    public function testDatePatternFormatWithQuotedStrings(): void
    {
        // we test against "es_ES", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('es_ES');

        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            // EEEE, d 'de' MMMM 'de' y
            'format' => \IntlDateFormatter::FULL,
        ))
            ->createView();

        $this->assertEquals('{{ day }}{{ month }}{{ year }}', $view->vars['date_pattern']);
    }

    public function testPassWidgetToView(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'widget' => 'single_text',
        ))
            ->createView();

        $this->assertSame('single_text', $view->vars['widget']);
    }

    public function testInitializeWithDateTime(): void
    {
        // Throws an exception if "data_class" option is not explicitly set
        // to null in the type
        $this->assertInstanceOf('Symfony\Component\Form\FormInterface', $this->factory->create(static::TESTED_TYPE, new \DateTime()));
    }

    public function testSingleTextWidgetShouldUseTheRightInputType(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'widget' => 'single_text',
        ))
            ->createView();

        $this->assertEquals('date', $view->vars['type']);
    }

    public function testPassDefaultPlaceholderToViewIfNotRequired(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'required' => false,
        ))
            ->createView();

        $this->assertSame('', $view['year']->vars['placeholder']);
        $this->assertSame('', $view['month']->vars['placeholder']);
        $this->assertSame('', $view['day']->vars['placeholder']);
    }

    public function testPassNoPlaceholderToViewIfRequired(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'required' => true,
        ))
            ->createView();

        $this->assertNull($view['year']->vars['placeholder']);
        $this->assertNull($view['month']->vars['placeholder']);
        $this->assertNull($view['day']->vars['placeholder']);
    }

    public function testPassPlaceholderAsString(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'placeholder' => 'Empty',
        ))
            ->createView();

        $this->assertSame('Empty', $view['year']->vars['placeholder']);
        $this->assertSame('Empty', $view['month']->vars['placeholder']);
        $this->assertSame('Empty', $view['day']->vars['placeholder']);
    }

    public function testPassPlaceholderAsArray(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'placeholder' => array(
                'year' => 'Empty year',
                'month' => 'Empty month',
                'day' => 'Empty day',
            ),
        ))
            ->createView();

        $this->assertSame('Empty year', $view['year']->vars['placeholder']);
        $this->assertSame('Empty month', $view['month']->vars['placeholder']);
        $this->assertSame('Empty day', $view['day']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddEmptyIfNotRequired(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'required' => false,
            'placeholder' => array(
                'year' => 'Empty year',
                'day' => 'Empty day',
            ),
        ))
            ->createView();

        $this->assertSame('Empty year', $view['year']->vars['placeholder']);
        $this->assertSame('', $view['month']->vars['placeholder']);
        $this->assertSame('Empty day', $view['day']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddNullIfRequired(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'required' => true,
            'placeholder' => array(
                'year' => 'Empty year',
                'day' => 'Empty day',
            ),
        ))
            ->createView();

        $this->assertSame('Empty year', $view['year']->vars['placeholder']);
        $this->assertNull($view['month']->vars['placeholder']);
        $this->assertSame('Empty day', $view['day']->vars['placeholder']);
    }

    public function testPassHtml5TypeIfSingleTextAndHtml5Format(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'widget' => 'single_text',
        ))
            ->createView();

        $this->assertSame('date', $view->vars['type']);
    }

    public function testDontPassHtml5TypeIfHtml5NotAllowed(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'widget' => 'single_text',
            'html5' => false,
        ))
            ->createView();

        $this->assertFalse(isset($view->vars['type']));
    }

    public function testDontPassHtml5TypeIfNotHtml5Format(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'widget' => 'single_text',
            'format' => \IntlDateFormatter::MEDIUM,
        ))
            ->createView();

        $this->assertFalse(isset($view->vars['type']));
    }

    public function testDontPassHtml5TypeIfNotSingleText(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'widget' => 'text',
        ))
            ->createView();

        $this->assertFalse(isset($view->vars['type']));
    }

    public function provideCompoundWidgets()
    {
        return array(
            array('text'),
            array('choice'),
        );
    }

    /**
     * @dataProvider provideCompoundWidgets
     */
    public function testYearErrorsBubbleUp($widget): void
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'widget' => $widget,
        ));
        $form['year']->addError($error);

        $this->assertSame(array(), iterator_to_array($form['year']->getErrors()));
        $this->assertSame(array($error), iterator_to_array($form->getErrors()));
    }

    /**
     * @dataProvider provideCompoundWidgets
     */
    public function testMonthErrorsBubbleUp($widget): void
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'widget' => $widget,
        ));
        $form['month']->addError($error);

        $this->assertSame(array(), iterator_to_array($form['month']->getErrors()));
        $this->assertSame(array($error), iterator_to_array($form->getErrors()));
    }

    /**
     * @dataProvider provideCompoundWidgets
     */
    public function testDayErrorsBubbleUp($widget): void
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'widget' => $widget,
        ));
        $form['day']->addError($error);

        $this->assertSame(array(), iterator_to_array($form['day']->getErrors()));
        $this->assertSame(array($error), iterator_to_array($form->getErrors()));
    }

    public function testYearsFor32BitsMachines(): void
    {
        if (4 !== PHP_INT_SIZE) {
            $this->markTestSkipped('PHP 32 bit is required.');
        }

        $view = $this->factory->create(static::TESTED_TYPE, null, array(
            'years' => range(1900, 2040),
        ))
            ->createView();

        $listChoices = array();
        foreach (range(1902, 2037) as $y) {
            $listChoices[] = new ChoiceView($y, $y, $y);
        }

        $this->assertEquals($listChoices, $view['year']->vars['choices']);
    }

    public function testPassDefaultChoiceTranslationDomain(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE);

        $view = $form->createView();
        $this->assertFalse($view['year']->vars['choice_translation_domain']);
        $this->assertFalse($view['month']->vars['choice_translation_domain']);
        $this->assertFalse($view['day']->vars['choice_translation_domain']);
    }

    public function testPassChoiceTranslationDomainAsString(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'choice_translation_domain' => 'messages',
        ));

        $view = $form->createView();
        $this->assertSame('messages', $view['year']->vars['choice_translation_domain']);
        $this->assertSame('messages', $view['month']->vars['choice_translation_domain']);
        $this->assertSame('messages', $view['day']->vars['choice_translation_domain']);
    }

    public function testPassChoiceTranslationDomainAsArray(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'choice_translation_domain' => array(
                'year' => 'foo',
                'day' => 'test',
            ),
        ));

        $view = $form->createView();
        $this->assertSame('foo', $view['year']->vars['choice_translation_domain']);
        $this->assertFalse($view['month']->vars['choice_translation_domain']);
        $this->assertSame('test', $view['day']->vars['choice_translation_domain']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null): void
    {
        parent::testSubmitNull($expected, $norm, array('year' => '', 'month' => '', 'day' => ''));
    }

    public function testSubmitNullWithSingleText(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'widget' => 'single_text',
        ));
        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getViewData());
    }
}
