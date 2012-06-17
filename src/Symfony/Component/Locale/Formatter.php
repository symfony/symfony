<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale;

/**
 * @author Eriksen Costa <eriksencosta@gmail.com>
 */
class Formatter
{
    /**
     * Available date/time styles.
     *
     * @var array
     */
    static private $styles = array(
        'short'  => \IntlDateFormatter::SHORT,
        'medium' => \IntlDateFormatter::MEDIUM,
        'long'   => \IntlDateFormatter::LONG,
        'full'   => \IntlDateFormatter::FULL,
    );

    /**
     * Available calendars.
     *
     * @var array
     */
    static private $calendars = array(
        'gregorian'   => \IntlDateFormatter::GREGORIAN,
        'traditional' => \IntlDateFormatter::TRADITIONAL,
    );

    /**
     * @var string
     */
    private $currency;

    /**
     * @var integer
     */
    private $dateStyle;

    /**
     * @var integer
     */
    private $timeStyle;

    /**
     * @var string
     */
    private $timeZone;

    /**
     * @var integer
     */
    private $calendar;

    /**
     * @var string
     */
    private $pattern;

    /**
     * Constructor.
     *
     * @param string  $currency   Default currency to use when calling formatCurrency without the $currency argument.
     *                            Defaults to null.
     * @param string  $dateStyle  The default date style for the date format methods. One of 'short', 'medium', 'long'
     *                            or 'full' Defaults to 'medium'.
     * @param string  $timeStyle  The default time style for the time format methods. One of 'short', 'medium', 'long'
     *                            or 'full' Defaults to 'medium'.
     * @param string  $timeZone   Default time zone to use. Defaults to the system time zone.
     * @param string  $calendar   Default calendar to use. One of 'gregorian' or 'traditional'. Defaults to 'gregorian'.
     * @param string  $pattern    Default pattern to use for the date and time format methods. When set, the date/time
     *                            format methods ignores the styles set. Defaults to null.
     *
     * @see LocaleExtension::formatCurrency()
     * @see LocaleExtension::formatDate()
     * @see LocaleExtension::formatTime()
     * @see LocaleExtension::formatDateTime()
     */
    public function __construct($currency = null, $dateStyle = 'medium', $timeStyle = 'short', $timeZone = null, $calendar = 'gregorian', $pattern = null)
    {
        $this->currency  = $currency;
        $this->dateStyle = $this->getStyleValue('date', $dateStyle);
        $this->timeStyle = $this->getStyleValue('time', $timeStyle);
        $this->timeZone  = $timeZone;
        $this->calendar  = $this->getCalendarValue($calendar);
        $this->pattern   = $pattern;
    }

    /**
     * Formats a value with the desired currency.
     *
     * @param float   $value     The value for format.
     * @param string  $currency  The currency symbol. If not provided, will try to use the default $currency property.
     *                           Defaults to null.
     * @param string  $locale    The desired locale. If not provided, will use the current locale.
     *                           Defaults to null.
     *
     * @return string  The formatted string.
     *
     * @throws \RuntimeException  When the $currency argument and the $currency property are not defined.
     */
    public function formatCurrency($value, $currency = null, $locale = null)
    {
        $locale = $this->getLocale($locale);
        $currency = $this->getCurrency($currency);

        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($value, $currency);
    }

    /**
     * Formats a date.
     *
     * @param mixed   $date       The date to format.
     * @param int     $dateStyle  The date style to use. One of 'short', 'medium', 'long' or 'full'. If not provided,
     *                            will use the $dateStyle provided in the constructor.
     * @param string  $timeZone   The time zone to use. Defaults to system time zone.
     * @param int     $calendar   The calendar to use. One of 'gregorian' or 'traditional'. If not provided, will use
     *                            the $calendar provided in the constructor.
     * @param string  $pattern    The string pattern to use. If not provided, will use the $pattern provided in the
     *                            constructor. If set, ignores completely the provided date/time styles.
     * @param string  $locale     The desired locale. If not provided, will use the current locale.
     *                            Defaults to null.
     *
     * @return string  The formatted string.
     *
     * @see http://userguide.icu-project.org/formatparse/datetime Formatting Dates and Times
     *
     * @throws \InvalidArgumentException When $dateStyle is not one of 'short', 'medium', 'long' or 'full'.
     * @throws \InvalidArgumentException When $calendar is not one of 'gregorian' or 'traditional'.
     */
    public function formatDate($date, $dateStyle = null, $timeZone = null, $calendar = null, $pattern = null, $locale = null)
    {
        $dateStyle = $this->getStyle('date', $dateStyle);

        return $this->doFormatDateTime($date, $dateStyle, \IntlDateFormatter::NONE, $timeZone, $calendar, $pattern, $locale);
    }

    /**
     * Formats a time.
     *
     * @param mixed   $time       The time to format.
     * @param int     $timeStyle  The time style to use. One of 'short', 'medium', 'long' or 'full'. If not provided,
     *                            will use the $timeStyle provided in the constructor.
     * @param string  $timeZone   The time zone to use. Defaults to system time zone.
     * @param int     $calendar   The calendar to use. One of 'gregorian' or 'traditional'. If not provided, will use
     *                            the $calendar provided in the constructor.
     * @param string  $pattern    The string pattern to use. If not provided, will use the $pattern provided in the
     *                            constructor. If set, ignores completely the provided date/time styles.
     * @param string  $locale     The desired locale. If not provided, will use the current locale.
     *                            Defaults to null.
     *
     * @return string  The formatted string.
     *
     * @see http://userguide.icu-project.org/formatparse/datetime Formatting Dates and Times
     *
     * @throws \InvalidArgumentException When $timeStyle is not one of 'short', 'medium', 'long' or 'full'.
     * @throws \InvalidArgumentException When $calendar is not one of 'gregorian' or 'traditional'.
     */
    public function formatTime($time, $timeStyle = null, $timeZone = null, $calendar = null, $pattern = null, $locale = null)
    {
        $timeStyle = $this->getStyle('time', $timeStyle);

        return $this->doFormatDateTime($time, \IntlDateFormatter::NONE, $timeStyle, $timeZone, $calendar, $pattern, $locale);
    }

    /**
     * Formats a date/time.
     *
     * @param mixed   $dateTime   The date/time to format.
     * @param int     $dateStyle  The date style to use. One of 'short', 'medium', 'long' or 'full'. If not provided,
     *                            will use the $dateStyle provided in the constructor.
     * @param int     $timeStyle  The time style to use. One of 'short', 'medium', 'long' or 'full'. If not provided,
     *                            will use the $timeStyle provided in the constructor.
     * @param string  $timeZone   The time zone to use. Defaults to system time zone.
     * @param int     $calendar   The calendar to use. One of 'gregorian' or 'traditional'. If not provided, will use
     *                            the $calendar provided in the constructor.
     * @param string  $pattern    The string pattern to use. If not provided, will use the $pattern provided in the
     *                            constructor. If set, ignores completely the provided date/time styles.
     * @param string  $locale     The desired locale. If not provided, will use the current locale.
     *                            Defaults to null.
     *
     * @return string  The formatted string.
     *
     * @see http://userguide.icu-project.org/formatparse/datetime Formatting Dates and Times
     *
     * @throws \InvalidArgumentException When $dateStyle is not one of 'short', 'medium', 'long' or 'full'.
     * @throws \InvalidArgumentException When $timeStyle is not one of 'short', 'medium', 'long' or 'full'.
     * @throws \InvalidArgumentException When $calendar is not one of 'gregorian' or 'traditional'.
     */
    public function formatDateTime($dateTime, $dateStyle = null, $timeStyle = null, $timeZone = null, $calendar = null, $pattern = null, $locale = null)
    {
        $dateStyle = $this->getStyle('date', $dateStyle);
        $timeStyle = $this->getStyle('time', $timeStyle);

        return $this->doFormatDateTime($dateTime, $dateStyle, $timeStyle, $timeZone, $calendar, $pattern, $locale);
    }

    /**
     * Formats a date/time.
     *
     * @param mixed   $dateTime
     * @param int     $dateStyle
     * @param int     $timeStyle
     * @param string  $timeZone
     * @param int     $calendar
     * @param string  $pattern
     * @param string  $locale
     *
     * @return string
     *
     * @see LocaleExtension::formatDate()
     * @see LocaleExtension::formatTime()
     * @see LocaleExtension::formatDateTime()
     *
     * @throws \InvalidArgumentException When $calendar is not one of 'gregorian' or 'traditional'.
     */
    private function doFormatDateTime($dateTime, $dateStyle, $timeStyle, $timeZone = null, $calendar = null, $pattern = null, $locale = null)
    {
        $timeZone = $this->getTimeZone($timeZone);
        $calendar = $this->getCalendar($calendar);
        $pattern  = $this->getPattern($pattern);
        $locale   = $this->getLocale($locale);

        $formatter = new \IntlDateFormatter($locale, $dateStyle, $timeStyle, $timeZone, $calendar, $pattern);

        // IntlDateFormatter supports DateTime as of PHP 5.3.4.
        // TODO: remove this when the PR #3840 got merged.
        if ($dateTime instanceOf \DateTime) {
            $dateTime = $dateTime->getTimestamp();
        }

        return $formatter->format($dateTime);
    }

    private function getLocale($locale = null)
    {
        return null !== $locale ? $locale : \Locale::getDefault();
    }

    private function getCurrency($currency = null)
    {
        if (null !== $currency) {
            return $currency;
        }

        if (null === $this->currency) {
            throw new \RuntimeException('You need to define the desired currency via constructor or via the "currency" argument.');
        }

        return $this->currency;
    }

    private function getStyle($type, $style)
    {
        if (null === $style) {
            return $this->{$type.'Style'};
        }

        return $this->getStyleValue($type, $style);
    }

    private function getTimeZone($timeZone = null)
    {
        return null !== $timeZone ? $timeZone : $this->timeZone;
    }

    private function getCalendar($calendar = null)
    {
        if (null === $calendar) {
            return $this->calendar;
        }

        return $this->getCalendarValue($calendar);
    }

    private function getPattern($pattern = null)
    {
        return null !== $pattern ? $pattern : $this->pattern;
    }

    private function getStyleValue($type, $style)
    {
        $style = strtolower($style);

        if (!isset(self::$styles[$style])) {
            throw new \InvalidArgumentException(sprintf('The "%sStyle" must be one of "%s". "%s" given.', $type, implode(', ', array_keys(self::$styles)), $style));
        }

        return self::$styles[$style];
    }

    private function getCalendarValue($calendar)
    {
        $calendar = strtolower($calendar);

        if (!isset(self::$calendars[$calendar])) {
            throw new \InvalidArgumentException(sprintf('The "calendar" must be one of "%s". "%s" given.', implode(', ', array_keys(self::$calendars)), $calendar));
        }

        return self::$calendars[$calendar];
    }
}
