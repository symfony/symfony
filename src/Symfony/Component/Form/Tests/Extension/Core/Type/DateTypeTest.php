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

use Symfony\Component\Form\Extension\Core\View\ChoiceView;
use Symfony\Component\Form\FormError;

class DateTypeTest extends LocalizedTestCase
{
    protected function setUp()
    {
        parent::setUp();

        \Locale::setDefault('de_AT');
    }

    /**
     * @expectedException Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testInvalidWidgetOption()
    {
        $form = $this->factory->create('date', null, array(
            'widget' => 'fake_widget',
        ));
    }

    /**
     * @expectedException Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testInvalidInputOption()
    {
        $form = $this->factory->create('date', null, array(
            'input' => 'fake_input',
        ));
    }

    public function testSubmitFromSingleTextDateTime()
    {
        $form = $this->factory->create('date', null, array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'datetime',
        ));

        $form->bind('2.6.2010');

        $this->assertDateTimeEquals(new \DateTime('2010-06-02 UTC'), $form->getData());
        $this->assertEquals('02.06.2010', $form->getViewData());
    }

    public function testSubmitFromSingleTextString()
    {
        $form = $this->factory->create('date', null, array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'string',
        ));

        $form->bind('2.6.2010');

        $this->assertEquals('2010-06-02', $form->getData());
        $this->assertEquals('02.06.2010', $form->getViewData());
    }

    public function testSubmitFromSingleTextTimestamp()
    {
        $form = $this->factory->create('date', null, array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'timestamp',
        ));

        $form->bind('2.6.2010');

        $dateTime = new \DateTime('2010-06-02 UTC');

        $this->assertEquals($dateTime->format('U'), $form->getData());
        $this->assertEquals('02.06.2010', $form->getViewData());
    }

    public function testSubmitFromSingleTextRaw()
    {
        $form = $this->factory->create('date', null, array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'single_text',
            'input' => 'array',
        ));

        $form->bind('2.6.2010');

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
        $form = $this->factory->create('date', null, array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'text',
        ));

        $text = array(
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        );

        $form->bind($text);

        $dateTime = new \DateTime('2010-06-02 UTC');

        $this->assertDateTimeEquals($dateTime, $form->getData());
        $this->assertEquals($text, $form->getViewData());
    }

    public function testSubmitFromChoice()
    {
        $form = $this->factory->create('date', null, array(
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
        $this->assertEquals($text, $form->getViewData());
    }

    public function testSubmitFromChoiceEmpty()
    {
        $form = $this->factory->create('date', null, array(
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

        $this->assertNull($form->getData());
        $this->assertEquals($text, $form->getViewData());
    }

    public function testSubmitFromInputDateTimeDifferentPattern()
    {
        $form = $this->factory->create('date', null, array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'format' => 'MM*yyyy*dd',
            'widget' => 'single_text',
            'input' => 'datetime',
        ));

        $form->bind('06*2010*02');

        $this->assertDateTimeEquals(new \DateTime('2010-06-02 UTC'), $form->getData());
        $this->assertEquals('06*2010*02', $form->getViewData());
    }

    public function testSubmitFromInputStringDifferentPattern()
    {
        $form = $this->factory->create('date', null, array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'format' => 'MM*yyyy*dd',
            'widget' => 'single_text',
            'input' => 'string',
        ));

        $form->bind('06*2010*02');

        $this->assertEquals('2010-06-02', $form->getData());
        $this->assertEquals('06*2010*02', $form->getViewData());
    }

    public function testSubmitFromInputTimestampDifferentPattern()
    {
        $form = $this->factory->create('date', null, array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'format' => 'MM*yyyy*dd',
            'widget' => 'single_text',
            'input' => 'timestamp',
        ));

        $form->bind('06*2010*02');

        $dateTime = new \DateTime('2010-06-02 UTC');

        $this->assertEquals($dateTime->format('U'), $form->getData());
        $this->assertEquals('06*2010*02', $form->getViewData());
    }

    public function testSubmitFromInputRawDifferentPattern()
    {
        $form = $this->factory->create('date', null, array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'format' => 'MM*yyyy*dd',
            'widget' => 'single_text',
            'input' => 'array',
        ));

        $form->bind('06*2010*02');

        $output = array(
            'day' => '2',
            'month' => '6',
            'year' => '2010',
        );

        $this->assertEquals($output, $form->getData());
        $this->assertEquals('06*2010*02', $form->getViewData());
    }

    /**
     * This test is to check that the strings '0', '1', '2', '3' are no accepted
     * as valid IntlDateFormatter constants for FULL, LONG, MEDIUM or SHORT respectively.
     */
    public function testFormatOptionCustomPatternCollapsingIntlDateFormatterConstant()
    {
        $form = $this->factory->create('date', null, array(
            'format' => '0',
            'widget' => 'single_text',
            'input' => 'string',
        ));

        $form->setData('2010-06-02');

        // This would be what would be outputed if '0' was mistaken for \IntlDateFormatter::FULL
        $this->assertNotEquals('Mittwoch, 02. Juni 2010', $form->getViewData());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\CreationException
     */
    public function testValidateFormatOptionGivenWrongConstants()
    {
        $form = $this->factory->create('date', null, array(
            'format' => 105,
        ));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\CreationException
     */
    public function testValidateFormatOptionGivenArrayValue()
    {
        $form = $this->factory->create('date', null, array(
            'format' => array(),
        ));
    }

    public function testSetData_differentTimezones()
    {
        $form = $this->factory->create('date', null, array(
            'data_timezone' => 'America/New_York',
            'user_timezone' => 'Pacific/Tahiti',
            'input' => 'string',
            'widget' => 'single_text',
        ));

        $form->setData('2010-06-02');

        $this->assertEquals('01.06.2010', $form->getViewData());
    }

    public function testSetData_differentTimezonesDateTime()
    {
        $form = $this->factory->create('date', null, array(
            'data_timezone' => 'America/New_York',
            'user_timezone' => 'Pacific/Tahiti',
            'input' => 'datetime',
            'widget' => 'single_text',
        ));

        $dateTime = new \DateTime('2010-06-02 America/New_York');

        $form->setData($dateTime);

        $this->assertDateTimeEquals($dateTime, $form->getData());
        $this->assertEquals('01.06.2010', $form->getViewData());
    }

    public function testYearsOption()
    {
        $form = $this->factory->create('date', null, array(
            'years' => array(2010, 2011),
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView('2010', '2010'),
            new ChoiceView('2011', '2011'),
        ), $view->get('year')->getVar('choices'));
    }

    public function testMonthsOption()
    {
        $form = $this->factory->create('date', null, array(
            'months' => array(6, 7),
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView('6', '06'),
            new ChoiceView('7', '07'),
        ), $view->get('month')->getVar('choices'));
    }

    public function testMonthsOptionNumericIfFormatContainsNoMonth()
    {
        $form = $this->factory->create('date', null, array(
            'months' => array(6, 7),
            'format' => 'yy',
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView('6', '06'),
            new ChoiceView('7', '07'),
        ), $view->get('month')->getVar('choices'));
    }

    public function testMonthsOptionShortFormat()
    {
        $form = $this->factory->create('date', null, array(
            'months' => array(1, 4),
            'format' => 'dd.MMM.yy',
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView('1', 'Jän'),
            new ChoiceView('4', 'Apr')
        ), $view->get('month')->getVar('choices'));
    }

    public function testMonthsOptionLongFormat()
    {
        $form = $this->factory->create('date', null, array(
            'months' => array(1, 4),
            'format' => 'dd.MMMM.yy',
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView('1', 'Jänner'),
            new ChoiceView('4', 'April'),
        ), $view->get('month')->getVar('choices'));
    }

    public function testMonthsOptionLongFormatWithDifferentTimezone()
    {
        $form = $this->factory->create('date', null, array(
            'months' => array(1, 4),
            'format' => 'dd.MMMM.yy',
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView('1', 'Jänner'),
            new ChoiceView('4', 'April'),
        ), $view->get('month')->getVar('choices'));
    }

    public function testIsDayWithinRangeReturnsTrueIfWithin()
    {
        $form = $this->factory->create('date', null, array(
            'days' => array(6, 7),
        ));

        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView('6', '06'),
            new ChoiceView('7', '07'),
        ), $view->get('day')->getVar('choices'));
    }

    public function testIsPartiallyFilledReturnsFalseIfSingleText()
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', null, array(
            'data_timezone' => 'UTC',
            'user_timezone' => 'UTC',
            'widget' => 'single_text',
        ));

        $form->bind('7.6.2010');

        $this->assertFalse($form->isPartiallyFilled());
    }

    public function testIsPartiallyFilledReturnsFalseIfChoiceAndCompletelyEmpty()
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', null, array(
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

    public function testIsPartiallyFilledReturnsFalseIfChoiceAndCompletelyFilled()
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', null, array(
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

    public function testIsPartiallyFilledReturnsTrueIfChoiceAndDayEmpty()
    {
        $this->markTestIncomplete('Needs to be reimplemented using validators');

        $form = $this->factory->create('date', null, array(
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

    public function testPassDatePatternToView()
    {
        $form = $this->factory->create('date');
        $view = $form->createView();

        $this->assertSame('{{ day }}.{{ month }}.{{ year }}', $view->getVar('date_pattern'));
    }

    public function testPassDatePatternToViewDifferentPattern()
    {
        $form = $this->factory->create('date', null, array(
            'format' => 'MM*yyyy*dd'
        ));

        $view = $form->createView();

        $this->assertSame('{{ month }}*{{ year }}*{{ day }}', $view->getVar('date_pattern'));
    }

    public function testDontPassDatePatternIfText()
    {
        $form = $this->factory->create('date', null, array(
            'widget' => 'single_text',
        ));
        $view = $form->createView();

        $this->assertNull($view->getVar('date_pattern'));
    }

    public function testPassWidgetToView()
    {
        $form = $this->factory->create('date', null, array(
            'widget' => 'single_text',
        ));
        $view = $form->createView();

        $this->assertSame('single_text', $view->getVar('widget'));
    }

    // Bug fix
    public function testInitializeWithDateTime()
    {
        // Throws an exception if "data_class" option is not explicitely set
        // to null in the type
        $this->factory->create('date', new \DateTime());
    }

    public function testSingleTextWidgetShouldUseTheRightInputType()
    {
        $form = $this->factory->create('date', null, array(
            'widget' => 'single_text',
        ));

        $view = $form->createView();
        $this->assertEquals('date', $view->getVar('type'));
    }
}
