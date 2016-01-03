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
use Symfony\Component\Form\Test\TypeTestCase as TestCase;
use Symfony\Component\Intl\Util\IntlTestHelper;

class DateTypeTest extends TestCase
{
    private $defaultTimezone;

    protected function setUp()
    {
        parent::setUp();
        $this->defaultTimezone = date_default_timezone_get();
    }

    protected function tearDown()
    {
        date_default_timezone_set($this->defaultTimezone);
        \Locale::setDefault('en');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testInvalidWidgetOption()
    {
        $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'widget' => 'fake_widget',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testInvalidInputOption()
    {
        $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'input' => 'fake_input',
        ));
    }

    public function testSubmitFromSingleTextDateTimeWithDefaultFormat()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'datetime',
        ));

        $form->submit('2010-06-02');

        $this->assertDateTimeEquals(new \DateTime('2010-06-02 UTC'), $form->getData());
        $this->assertEquals('2010-06-02', $form->getViewData());
    }

    public function testSubmitFromSingleTextDateTime()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
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

    public function testSubmitFromSingleTextString()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
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

    public function testSubmitFromSingleTextTimestamp()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
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

    public function testSubmitFromSingleTextRaw()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
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

    public function testSubmitFromText()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
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

    public function testSubmitFromChoice()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
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

    public function testSubmitFromChoiceEmpty()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
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

    public function testSubmitFromInputDateTimeDifferentPattern()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
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

    public function testSubmitFromInputStringDifferentPattern()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
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

    public function testSubmitFromInputTimestampDifferentPattern()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
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

    public function testSubmitFromInputRawDifferentPattern()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
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
    public function testDatePatternWithFormatOption($format, $pattern)
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'format' => $format,
        ));

        $view = $form->createView();

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
    public function testThrowExceptionIfFormatIsNoPattern()
    {
        $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'format' => '0',
            'widget' => 'single_text',
            'input' => 'string',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testThrowExceptionIfFormatDoesNotContainYearMonthAndDay()
    {
        $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'months' => array(6, 7),
            'format' => 'yy',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testThrowExceptionIfFormatIsNoConstant()
    {
        $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'format' => 105,
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testThrowExceptionIfFormatIsInvalid()
    {
        $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'format' => array(),
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testThrowExceptionIfYearsIsInvalid()
    {
        $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'years' => 'bad value',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testThrowExceptionIfMonthsIsInvalid()
    {
        $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'months' => 'bad value',
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testThrowExceptionIfDaysIsInvalid()
    {
        $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'days' => 'bad value',
        ));
    }

    public function testSetDataWithNegativeTimezoneOffsetStringInput()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
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

    public function testSetDataWithNegativeTimezoneOffsetDateTimeInput()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
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

    public function testYearsOption()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'years' => array(2010, 2011),
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView('2010', '2010', '2010'),
            new ChoiceView('2011', '2011', '2011'),
        ), $view['year']->vars['choices']);
    }

    public function testMonthsOption()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'months' => array(6, 7),
            'format' => \IntlDateFormatter::SHORT,
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView(6, '6', '06'),
            new ChoiceView(7, '7', '07'),
        ), $view['month']->vars['choices']);
    }

    public function testMonthsOptionShortFormat()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'months' => array(1, 4),
            'format' => 'dd.MMM.yy',
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView(1, '1', 'Jän'),
            new ChoiceView(4, '4', 'Apr.'),
        ), $view['month']->vars['choices']);
    }

    public function testMonthsOptionLongFormat()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'months' => array(1, 4),
            'format' => 'dd.MMMM.yy',
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView(1, '1', 'Jänner'),
            new ChoiceView(4, '4', 'April'),
        ), $view['month']->vars['choices']);
    }

    public function testMonthsOptionLongFormatWithDifferentTimezone()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'months' => array(1, 4),
            'format' => 'dd.MMMM.yy',
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView(1, '1', 'Jänner'),
            new ChoiceView(4, '4', 'April'),
        ), $view['month']->vars['choices']);
    }

    public function testIsDayWithinRangeReturnsTrueIfWithin()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'days' => array(6, 7),
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView(6, '6', '06'),
            new ChoiceView(7, '7', '07'),
        ), $view['day']->vars['choices']);
    }

    public function testIsPartiallyFilledReturnsFalseIfSingleText()
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'single_text',
        ));

        $form->submit('7.6.2010');

        $this->assertFalse($form->isPartiallyFilled());
    }

    public function testIsPartiallyFilledReturnsFalseIfChoiceAndCompletelyEmpty()
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'choice',
        ));

        $form->submit(array(
            'day' => '',
            'month' => '',
            'year' => '',
        ));

        $this->assertFalse($form->isPartiallyFilled());
    }

    public function testIsPartiallyFilledReturnsFalseIfChoiceAndCompletelyFilled()
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'choice',
        ));

        $form->submit(array(
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        ));

        $this->assertFalse($form->isPartiallyFilled());
    }

    public function testIsPartiallyFilledReturnsTrueIfChoiceAndDayEmpty()
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
            'widget' => 'choice',
        ));

        $form->submit(array(
            'day' => '',
            'month' => '6',
            'year' => '2010',
        ));

        $this->assertTrue($form->isPartiallyFilled());
    }

    public function testPassDatePatternToView()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType');
        $view = $form->createView();

        $this->assertSame('{{ day }}{{ month }}{{ year }}', $view->vars['date_pattern']);
    }

    public function testPassDatePatternToViewDifferentFormat()
    {
        // we test against "de_AT", so we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'format' => \IntlDateFormatter::LONG,
        ));

        $view = $form->createView();

        $this->assertSame('{{ day }}{{ month }}{{ year }}', $view->vars['date_pattern']);
    }

    public function testPassDatePatternToViewDifferentPattern()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'format' => 'MMyyyydd',
        ));

        $view = $form->createView();

        $this->assertSame('{{ month }}{{ year }}{{ day }}', $view->vars['date_pattern']);
    }

    public function testPassDatePatternToViewDifferentPatternWithSeparators()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'format' => 'MM*yyyy*dd',
        ));

        $view = $form->createView();

        $this->assertSame('{{ month }}*{{ year }}*{{ day }}', $view->vars['date_pattern']);
    }

    public function testDontPassDatePatternIfText()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'widget' => 'single_text',
        ));
        $view = $form->createView();

        $this->assertFalse(isset($view->vars['date_pattern']));
    }

    public function testDatePatternFormatWithQuotedStrings()
    {
        // we test against "es_ES", so we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('es_ES');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            // EEEE, d 'de' MMMM 'de' y
            'format' => \IntlDateFormatter::FULL,
        ));

        $view = $form->createView();

        $this->assertEquals('{{ day }}{{ month }}{{ year }}', $view->vars['date_pattern']);
    }

    public function testPassWidgetToView()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'widget' => 'single_text',
        ));
        $view = $form->createView();

        $this->assertSame('single_text', $view->vars['widget']);
    }

    public function testInitializeWithDateTime()
    {
        // Throws an exception if "data_class" option is not explicitly set
        // to null in the type
        $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', new \DateTime());
    }

    public function testSingleTextWidgetShouldUseTheRightInputType()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'widget' => 'single_text',
        ));

        $view = $form->createView();
        $this->assertEquals('date', $view->vars['type']);
    }

    public function testPassDefaultPlaceholderToViewIfNotRequired()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'required' => false,
        ));

        $view = $form->createView();
        $this->assertSame('', $view['year']->vars['placeholder']);
        $this->assertSame('', $view['month']->vars['placeholder']);
        $this->assertSame('', $view['day']->vars['placeholder']);
    }

    public function testPassNoPlaceholderToViewIfRequired()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'required' => true,
        ));

        $view = $form->createView();
        $this->assertNull($view['year']->vars['placeholder']);
        $this->assertNull($view['month']->vars['placeholder']);
        $this->assertNull($view['day']->vars['placeholder']);
    }

    public function testPassPlaceholderAsString()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'placeholder' => 'Empty',
        ));

        $view = $form->createView();
        $this->assertSame('Empty', $view['year']->vars['placeholder']);
        $this->assertSame('Empty', $view['month']->vars['placeholder']);
        $this->assertSame('Empty', $view['day']->vars['placeholder']);
    }

    public function testPassPlaceholderAsArray()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'placeholder' => array(
                'year' => 'Empty year',
                'month' => 'Empty month',
                'day' => 'Empty day',
            ),
        ));

        $view = $form->createView();
        $this->assertSame('Empty year', $view['year']->vars['placeholder']);
        $this->assertSame('Empty month', $view['month']->vars['placeholder']);
        $this->assertSame('Empty day', $view['day']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddEmptyIfNotRequired()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'required' => false,
            'placeholder' => array(
                'year' => 'Empty year',
                'day' => 'Empty day',
            ),
        ));

        $view = $form->createView();
        $this->assertSame('Empty year', $view['year']->vars['placeholder']);
        $this->assertSame('', $view['month']->vars['placeholder']);
        $this->assertSame('Empty day', $view['day']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddNullIfRequired()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'required' => true,
            'placeholder' => array(
                'year' => 'Empty year',
                'day' => 'Empty day',
            ),
        ));

        $view = $form->createView();
        $this->assertSame('Empty year', $view['year']->vars['placeholder']);
        $this->assertNull($view['month']->vars['placeholder']);
        $this->assertSame('Empty day', $view['day']->vars['placeholder']);
    }

    public function testPassHtml5TypeIfSingleTextAndHtml5Format()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'widget' => 'single_text',
        ));

        $view = $form->createView();
        $this->assertSame('date', $view->vars['type']);
    }

    public function testDontPassHtml5TypeIfHtml5NotAllowed()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'widget' => 'single_text',
            'html5' => false,
        ));

        $view = $form->createView();
        $this->assertFalse(isset($view->vars['type']));
    }

    public function testDontPassHtml5TypeIfNotHtml5Format()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'widget' => 'single_text',
            'format' => \IntlDateFormatter::MEDIUM,
        ));

        $view = $form->createView();
        $this->assertFalse(isset($view->vars['type']));
    }

    public function testDontPassHtml5TypeIfNotSingleText()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'widget' => 'text',
        ));

        $view = $form->createView();
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
    public function testYearErrorsBubbleUp($widget)
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'widget' => $widget,
        ));
        $form['year']->addError($error);

        $this->assertSame(array(), iterator_to_array($form['year']->getErrors()));
        $this->assertSame(array($error), iterator_to_array($form->getErrors()));
    }

    /**
     * @dataProvider provideCompoundWidgets
     */
    public function testMonthErrorsBubbleUp($widget)
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'widget' => $widget,
        ));
        $form['month']->addError($error);

        $this->assertSame(array(), iterator_to_array($form['month']->getErrors()));
        $this->assertSame(array($error), iterator_to_array($form->getErrors()));
    }

    /**
     * @dataProvider provideCompoundWidgets
     */
    public function testDayErrorsBubbleUp($widget)
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'widget' => $widget,
        ));
        $form['day']->addError($error);

        $this->assertSame(array(), iterator_to_array($form['day']->getErrors()));
        $this->assertSame(array($error), iterator_to_array($form->getErrors()));
    }

    public function testYearsFor32BitsMachines()
    {
        if (4 !== PHP_INT_SIZE) {
            $this->markTestSkipped('PHP 32 bit is required.');
        }

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'years' => range(1900, 2040),
        ));

        $view = $form->createView();

        $listChoices = array();
        foreach (range(1902, 2037) as $y) {
            $listChoices[] = new ChoiceView($y, $y, $y);
        }

        $this->assertEquals($listChoices, $view['year']->vars['choices']);
    }

    public function testPassDefaultChoiceTranslationDomain()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType');

        $view = $form->createView();
        $this->assertFalse($view['year']->vars['choice_translation_domain']);
        $this->assertFalse($view['month']->vars['choice_translation_domain']);
        $this->assertFalse($view['day']->vars['choice_translation_domain']);
    }

    public function testPassChoiceTranslationDomainAsString()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'choice_translation_domain' => 'messages',
        ));

        $view = $form->createView();
        $this->assertSame('messages', $view['year']->vars['choice_translation_domain']);
        $this->assertSame('messages', $view['month']->vars['choice_translation_domain']);
        $this->assertSame('messages', $view['day']->vars['choice_translation_domain']);
    }

    public function testPassChoiceTranslationDomainAsArray()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
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
}
