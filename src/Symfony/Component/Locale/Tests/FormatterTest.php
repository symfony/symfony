<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Tests;

use Symfony\Component\Locale\Formatter;

class FormatterTest extends AbstractFormatterTest
{
    private $formatter;

    protected function setUp()
    {
        parent::setUp();

        $this->formatter = new Formatter('USD');
    }

    protected function tearDown()
    {
        parent::tearDown();

        unset($this->formatter);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The "dateStyle" must be one of "short, medium, long, full". "invalid_date_style" given.
     */
    public function testConstructorShouldThrowExceptionWhenDateStyleIsInvalid()
    {
        new Formatter('USD', 'invalid_date_style');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The "timeStyle" must be one of "short, medium, long, full". "invalid_time_style" given.
     */
    public function testConstructorShouldThrowExceptionWhenTimeStyleIsInvalid()
    {
        new Formatter('USD', 'short', 'invalid_time_style');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The "calendar" must be one of "gregorian, traditional". "invalid_calendar" given.
     */
    public function testConstructorShouldThrowExceptionWhenCalendarIsInvalid()
    {
        new Formatter('USD', 'short', 'short', null, 'invalid_calendar');
    }

    public function testFormatCurrency()
    {
        $currency = $this->formatter->formatCurrency(100);
        $this->assertEquals('$100.00', $currency);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage You need to define the desired currency via constructor or via the "currency" argument.
     */
    public function testFormatCurrencyShouldThrowAnExceptionWhenNotProvidingTheCurrency()
    {
        $locale = new Formatter();
        $locale->formatCurrency(100);
    }

    public function testFormatCurrencyShouldUseTheDefinedCurrencyWhenProvided()
    {
        $currency = $this->formatter->formatCurrency(100, 'EUR');
        $this->assertEquals('€100.00', $currency);

        $currency = $this->formatter->formatCurrency(100, 'BRL');
        $this->assertEquals('R$100.00', $currency);
    }

    public function testFormatCurrencyShouldUseTheDefaultLocale()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        \Locale::setDefault('pt_BR');

        $currency = $this->formatter->formatCurrency(100, 'USD');
        $this->assertEquals('US$100,00', $currency);

        $currency = $this->formatter->formatCurrency(100, 'BRL');
        $this->assertEquals('R$100,00', $currency);
    }

    public function testFormatCurrencyShouldUseTheDefinedLocaleWhenProvided()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        \Locale::setDefault('fr_FR');

        $currency = $this->formatter->formatCurrency(100, 'USD', 'en');
        $this->assertEquals('$100.00', $currency);

        $currency = $this->formatter->formatCurrency(100, 'BRL', 'en');
        $this->assertEquals('R$100.00', $currency);
    }

    public function testFormatDate()
    {
        $date = $this->formatter->formatDate($this->dateTime);
        $this->assertEquals('Jul 10, 2012', $date);
    }

    public function testFormatDateShouldUseTheDefinedDateStyleWhenProvided()
    {
        $date = $this->formatter->formatDate($this->dateTime, 'medium');
        $this->assertEquals('Jul 10, 2012', $date);
    }

    public function testFormatDateShouldUseTheDefinedTimezoneWhenProvided()
    {
        $date = $this->formatter->formatDate($this->dateTime, null, 'Europe/London');
        $this->assertEquals('Jul 11, 2012', $date);
    }

    public function testFormatDateShouldUseTheDefinedCalendarWhenProvided()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        $date = $this->formatter->formatDate($this->dateTime, null, null, 'traditional');
        $this->assertEquals('Jul 10, 2012', $date);
    }

    public function testFormatDateShouldUseTheDefinedPatternWhenProvided()
    {
        $date = $this->formatter->formatDate($this->dateTime, null, null, null, 'yyyy/MM/dd');
        $this->assertEquals('2012/07/10', $date);
    }

    public function testFormatDateShouldUseTheDefaultLocale()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        \Locale::setDefault('pt_BR');

        $date = $this->formatter->formatDate($this->dateTime);
        $this->assertEquals('10/07/2012', $date);
    }

    public function testFormatDateShouldUseTheDefinedLocaleWhenProvided()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        \Locale::setDefault('fr_FR');

        $date = $this->formatter->formatDate($this->dateTime, null, null, null, null, 'en');
        $this->assertEquals('Jul 10, 2012', $date);
    }

    public function testFormatTime()
    {
        $date = $this->formatter->formatTime($this->dateTime);
        $this->assertEquals('11:00 PM', $date);
    }

    public function testFormatTimeShouldUseTheDefinedTimeStyleWhenProvided()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        $date = $this->formatter->formatTime($this->dateTime, 'medium');
        $this->assertEquals('11:00:00 PM', $date);

        $date = $this->formatter->formatTime($this->dateTime, 'long');
        $this->assertEquals('11:00:00 PM GMT-03:00', $date);

        $date = $this->formatter->formatTime($this->dateTime, 'full');
        $this->assertEquals('11:00:00 PM Brasilia Time', $date);
    }

    public function testFormatTimeShouldUseTheDefinedTimezoneWhenProvided()
    {
        $date = $this->formatter->formatTime($this->dateTime, null, 'Europe/London');
        $this->assertEquals('3:00 AM', $date);
    }

    public function testFormatTimeShouldUseTheDefinedCalendarWhenProvided()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        $date = $this->formatter->formatTime($this->dateTime, null, null, 'traditional');
        $this->assertEquals('11:00 PM', $date);
    }

    public function testFormatTimeShouldUseTheDefinedPatternWhenProvided()
    {
        $date = $this->formatter->formatTime($this->dateTime, null, null, null, 'HH:mm:ss a');
        $this->assertEquals('23:00:00 PM', $date);
    }

    public function testFormatTimeShouldUseTheDefaultLocale()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        \Locale::setDefault('pt_BR');

        $date = $this->formatter->formatTime($this->dateTime);
        $this->assertEquals('23:00', $date);
    }

    public function testFormatTimeShouldUseTheDefinedLocaleWhenProvided()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        \Locale::setDefault('fr_FR');

        $date = $this->formatter->formatTime($this->dateTime, null, null, null, null, 'en');
        $this->assertEquals('11:00 PM', $date);
    }

    public function testFormatDateTime()
    {
        $date = $this->formatter->formatDateTime($this->dateTime);
        $this->assertEquals('Jul 10, 2012 11:00 PM', $date);
    }

    public function testFormatDateTimeShouldUseTheDefinedDateStyleWhenProvided()
    {
        $date = $this->formatter->formatDateTime($this->dateTime, 'medium');
        $this->assertEquals('Jul 10, 2012 11:00 PM', $date);

        $date = $this->formatter->formatDateTime($this->dateTime, 'long');
        $this->assertEquals('July 10, 2012 11:00 PM', $date);

        $date = $this->formatter->formatDateTime($this->dateTime, 'full');
        $this->assertEquals('Tuesday, July 10, 2012 11:00 PM', $date);
    }

    public function testFormatDateTimeShouldUseTheDefinedTimeStyleWhenProvided()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        $date = $this->formatter->formatDateTime($this->dateTime, null, 'medium');
        $this->assertEquals('Jul 10, 2012 11:00:00 PM', $date);

        $date = $this->formatter->formatDateTime($this->dateTime, null, 'long');
        $this->assertEquals('Jul 10, 2012 11:00:00 PM GMT-03:00', $date);

        $date = $this->formatter->formatDateTime($this->dateTime, null, 'full');
        $this->assertEquals('Jul 10, 2012 11:00:00 PM Brasilia Time', $date);
    }

    public function testFormatDateTimeShouldUseTheDefinedTimezoneWhenProvided()
    {
        $date = $this->formatter->formatDateTime($this->dateTime, null, null, 'Europe/London');
        $this->assertEquals('Jul 11, 2012 3:00 AM', $date);
    }

    public function testFormatDateTimeShouldUseTheDefinedCalendarWhenProvided()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        $date = $this->formatter->formatDateTime($this->dateTime, null, null, null, 'traditional');
        $this->assertEquals('Jul 10, 2012 11:00 PM', $date);
    }

    public function testFormatDateTimeShouldUseTheDefinedPatternWhenProvided()
    {
        $date = $this->formatter->formatDateTime($this->dateTime, null, null, null, null, 'yyyy/MM/dd HH:mm:ss a');
        $this->assertEquals('2012/07/10 23:00:00 PM', $date);
    }

    public function testFormatDateTimeShouldUseTheDefaultLocale()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        \Locale::setDefault('pt_BR');

        $date = $this->formatter->formatDateTime($this->dateTime);
        $this->assertEquals('10/07/2012 23:00', $date);
    }

    public function testFormatDateTimeShouldUseTheDefinedLocaleWhenProvided()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        \Locale::setDefault('fr_FR');

        $date = $this->formatter->formatDateTime($this->dateTime, null, null, null, null, null, 'en');
        $this->assertEquals('Jul 10, 2012 11:00 PM', $date);
    }
}
