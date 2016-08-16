<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\DateFormatter;

use Symfony\Component\Intl\Globals\IntlGlobals;
use Symfony\Component\Intl\DateFormatter\DateFormat\FullTransformer;
use Symfony\Component\Intl\Exception\MethodNotImplementedException;
use Symfony\Component\Intl\Exception\MethodArgumentNotImplementedException;
use Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException;
use Symfony\Component\Intl\Locale\Locale;

/**
 * Replacement for PHP's native {@link \IntlDateFormatter} class.
 *
 * The only methods currently supported in this class are:
 *
 *  - {@link __construct}
 *  - {@link create}
 *  - {@link format}
 *  - {@link getCalendar}
 *  - {@link getDateType}
 *  - {@link getErrorCode}
 *  - {@link getErrorMessage}
 *  - {@link getLocale}
 *  - {@link getPattern}
 *  - {@link getTimeType}
 *  - {@link getTimeZoneId}
 *  - {@link isLenient}
 *  - {@link parse}
 *  - {@link setLenient}
 *  - {@link setPattern}
 *  - {@link setTimeZoneId}
 *  - {@link setTimeZone}
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class IntlDateFormatter
{
    /**
     * The error code from the last operation.
     *
     * @var int
     */
    protected $errorCode = IntlGlobals::U_ZERO_ERROR;

    /**
     * The error message from the last operation.
     *
     * @var string
     */
    protected $errorMessage = 'U_ZERO_ERROR';

    /* date/time format types */
    const NONE = -1;
    const FULL = 0;
    const LONG = 1;
    const MEDIUM = 2;
    const SHORT = 3;

    /* calendar formats */
    const TRADITIONAL = 0;
    const GREGORIAN = 1;

    /**
     * Patterns used to format the date when no pattern is provided.
     *
     * @var array
     */
    private $defaultDateFormats = array(
        self::NONE => '',
        self::FULL => 'EEEE, LLLL d, y',
        self::LONG => 'LLLL d, y',
        self::MEDIUM => 'LLL d, y',
        self::SHORT => 'M/d/yy',
    );

    /**
     * Patterns used to format the time when no pattern is provided.
     *
     * @var array
     */
    private $defaultTimeFormats = array(
        self::FULL => 'h:mm:ss a zzzz',
        self::LONG => 'h:mm:ss a z',
        self::MEDIUM => 'h:mm:ss a',
        self::SHORT => 'h:mm a',
    );

    /**
     * @var int
     */
    private $datetype;

    /**
     * @var int
     */
    private $timetype;

    /**
     * @var string
     */
    private $pattern;

    /**
     * @var \DateTimeZone
     */
    private $dateTimeZone;

    /**
     * @var bool
     */
    private $uninitializedTimeZoneId = false;

    /**
     * @var string
     */
    private $timeZoneId;

    /**
     * Constructor.
     *
     * @param string $locale   The locale code. The only currently supported locale is "en" (or null using the default locale, i.e. "en")
     * @param int    $datetype Type of date formatting, one of the format type constants
     * @param int    $timetype Type of time formatting, one of the format type constants
     * @param mixed  $timezone Timezone identifier
     * @param int    $calendar Calendar to use for formatting or parsing. The only currently
     *                         supported value is IntlDateFormatter::GREGORIAN.
     * @param string $pattern  Optional pattern to use when formatting
     *
     * @see http://www.php.net/manual/en/intldateformatter.create.php
     * @see http://userguide.icu-project.org/formatparse/datetime
     *
     * @throws MethodArgumentValueNotImplementedException When $locale different than "en" or null is passed
     * @throws MethodArgumentValueNotImplementedException When $calendar different than GREGORIAN is passed
     */
    public function __construct($locale, $datetype, $timetype, $timezone = null, $calendar = self::GREGORIAN, $pattern = null)
    {
        if ('en' !== $locale && null !== $locale) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'locale', $locale, 'Only the locale "en" is supported');
        }

        if (self::GREGORIAN !== $calendar) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'calendar', $calendar, 'Only the GREGORIAN calendar is supported');
        }

        $this->datetype = $datetype;
        $this->timetype = $timetype;

        $this->setPattern($pattern);
        $this->setTimeZone($timezone);
    }

    /**
     * Static constructor.
     *
     * @param string $locale   The locale code. The only currently supported locale is "en" (or null using the default locale, i.e. "en")
     * @param int    $datetype Type of date formatting, one of the format type constants
     * @param int    $timetype Type of time formatting, one of the format type constants
     * @param string $timezone Timezone identifier
     * @param int    $calendar Calendar to use for formatting or parsing; default is Gregorian
     *                         One of the calendar constants.
     * @param string $pattern  Optional pattern to use when formatting
     *
     * @return IntlDateFormatter
     *
     * @see http://www.php.net/manual/en/intldateformatter.create.php
     * @see http://userguide.icu-project.org/formatparse/datetime
     *
     * @throws MethodArgumentValueNotImplementedException When $locale different than "en" or null is passed
     * @throws MethodArgumentValueNotImplementedException When $calendar different than GREGORIAN is passed
     */
    public static function create($locale, $datetype, $timetype, $timezone = null, $calendar = self::GREGORIAN, $pattern = null)
    {
        return new self($locale, $datetype, $timetype, $timezone, $calendar, $pattern);
    }

    /**
     * Format the date/time value (timestamp) as a string.
     *
     * @param int|\DateTime $timestamp The timestamp to format. \DateTime objects
     *                                 are supported as of PHP 5.3.4.
     *
     * @return string|bool The formatted value or false if formatting failed
     *
     * @see http://www.php.net/manual/en/intldateformatter.format.php
     *
     * @throws MethodArgumentValueNotImplementedException If one of the formatting characters is not implemented
     */
    public function format($timestamp)
    {
        // intl allows timestamps to be passed as arrays - we don't
        if (is_array($timestamp)) {
            $message = 'Only integer Unix timestamps and DateTime objects are supported';

            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'timestamp', $timestamp, $message);
        }

        // behave like the intl extension
        $argumentError = null;
        if (!is_int($timestamp) && !$timestamp instanceof \DateTime) {
            $argumentError = 'datefmt_format: takes either an array or an integer timestamp value or a DateTime object';
            if (PHP_VERSION_ID >= 50500 || (extension_loaded('intl') && method_exists('IntlDateFormatter', 'setTimeZone'))) {
                $argumentError = sprintf('datefmt_format: string \'%s\' is not numeric, which would be required for it to be a valid date', $timestamp);
            }
        }

        if (null !== $argumentError) {
            IntlGlobals::setError(IntlGlobals::U_ILLEGAL_ARGUMENT_ERROR, $argumentError);
            $this->errorCode = IntlGlobals::getErrorCode();
            $this->errorMessage = IntlGlobals::getErrorMessage();

            return false;
        }

        if ($timestamp instanceof \DateTime) {
            $timestamp = $timestamp->getTimestamp();
        }

        $transformer = new FullTransformer($this->getPattern(), $this->getTimeZoneId());
        $formatted = $transformer->format($this->createDateTime($timestamp));

        // behave like the intl extension
        IntlGlobals::setError(IntlGlobals::U_ZERO_ERROR);
        $this->errorCode = IntlGlobals::getErrorCode();
        $this->errorMessage = IntlGlobals::getErrorMessage();

        return $formatted;
    }

    /**
     * Not supported. Formats an object.
     *
     * @param object $object
     * @param mixed  $format
     * @param string $locale
     *
     * @return string The formatted value
     *
     * @see http://www.php.net/manual/en/intldateformatter.formatobject.php
     *
     * @throws MethodNotImplementedException
     */
    public function formatObject($object, $format = null, $locale = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the formatter's calendar.
     *
     * @return int The calendar being used by the formatter. Currently always returns
     *             IntlDateFormatter::GREGORIAN.
     *
     * @see http://www.php.net/manual/en/intldateformatter.getcalendar.php
     */
    public function getCalendar()
    {
        return self::GREGORIAN;
    }

    /**
     * Not supported. Returns the formatter's calendar object.
     *
     * @return object The calendar's object being used by the formatter
     *
     * @see http://www.php.net/manual/en/intldateformatter.getcalendarobject.php
     *
     * @throws MethodNotImplementedException
     */
    public function getCalendarObject()
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the formatter's datetype.
     *
     * @return int The current value of the formatter
     *
     * @see http://www.php.net/manual/en/intldateformatter.getdatetype.php
     */
    public function getDateType()
    {
        return $this->datetype;
    }

    /**
     * Returns formatter's last error code. Always returns the U_ZERO_ERROR class constant value.
     *
     * @return int The error code from last formatter call
     *
     * @see http://www.php.net/manual/en/intldateformatter.geterrorcode.php
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Returns formatter's last error message. Always returns the U_ZERO_ERROR_MESSAGE class constant value.
     *
     * @return string The error message from last formatter call
     *
     * @see http://www.php.net/manual/en/intldateformatter.geterrormessage.php
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Returns the formatter's locale.
     *
     * @param int $type Not supported. The locale name type to return (Locale::VALID_LOCALE or Locale::ACTUAL_LOCALE)
     *
     * @return string The locale used to create the formatter. Currently always
     *                returns "en".
     *
     * @see http://www.php.net/manual/en/intldateformatter.getlocale.php
     */
    public function getLocale($type = Locale::ACTUAL_LOCALE)
    {
        return 'en';
    }

    /**
     * Returns the formatter's pattern.
     *
     * @return string The pattern string used by the formatter
     *
     * @see http://www.php.net/manual/en/intldateformatter.getpattern.php
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * Returns the formatter's time type.
     *
     * @return string The time type used by the formatter
     *
     * @see http://www.php.net/manual/en/intldateformatter.gettimetype.php
     */
    public function getTimeType()
    {
        return $this->timetype;
    }

    /**
     * Returns the formatter's timezone identifier.
     *
     * @return string The timezone identifier used by the formatter
     *
     * @see http://www.php.net/manual/en/intldateformatter.gettimezoneid.php
     */
    public function getTimeZoneId()
    {
        if (!$this->uninitializedTimeZoneId) {
            return $this->timeZoneId;
        }

        // In PHP 5.5 default timezone depends on `date_default_timezone_get()` method
        if (PHP_VERSION_ID >= 50500 || (extension_loaded('intl') && method_exists('IntlDateFormatter', 'setTimeZone'))) {
            return date_default_timezone_get();
        }
    }

    /**
     * Not supported. Returns the formatter's timezone.
     *
     * @return mixed The timezone used by the formatter
     *
     * @see http://www.php.net/manual/en/intldateformatter.gettimezone.php
     *
     * @throws MethodNotImplementedException
     */
    public function getTimeZone()
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns whether the formatter is lenient.
     *
     * @return bool Currently always returns false
     *
     * @see http://www.php.net/manual/en/intldateformatter.islenient.php
     *
     * @throws MethodNotImplementedException
     */
    public function isLenient()
    {
        return false;
    }

    /**
     * Not supported. Parse string to a field-based time value.
     *
     * @param string $value    String to convert to a time value
     * @param int    $position Position at which to start the parsing in $value (zero-based)
     *                         If no error occurs before $value is consumed, $parse_pos will
     *                         contain -1 otherwise it will contain the position at which parsing
     *                         ended. If $parse_pos > strlen($value), the parse fails immediately.
     *
     * @return string Localtime compatible array of integers: contains 24 hour clock value in tm_hour field
     *
     * @see http://www.php.net/manual/en/intldateformatter.localtime.php
     *
     * @throws MethodNotImplementedException
     */
    public function localtime($value, &$position = 0)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Parse string to a timestamp value.
     *
     * @param string $value    String to convert to a time value
     * @param int    $position Not supported. Position at which to start the parsing in $value (zero-based)
     *                         If no error occurs before $value is consumed, $parse_pos will
     *                         contain -1 otherwise it will contain the position at which parsing
     *                         ended. If $parse_pos > strlen($value), the parse fails immediately.
     *
     * @return string Parsed value as a timestamp
     *
     * @see http://www.php.net/manual/en/intldateformatter.parse.php
     *
     * @throws MethodArgumentNotImplementedException When $position different than null, behavior not implemented
     */
    public function parse($value, &$position = null)
    {
        // We don't calculate the position when parsing the value
        if (null !== $position) {
            throw new MethodArgumentNotImplementedException(__METHOD__, 'position');
        }

        $dateTime = $this->createDateTime(0);
        $transformer = new FullTransformer($this->getPattern(), $this->getTimeZoneId());

        $timestamp = $transformer->parse($dateTime, $value);

        // behave like the intl extension. FullTransformer::parse() set the proper error
        $this->errorCode = IntlGlobals::getErrorCode();
        $this->errorMessage = IntlGlobals::getErrorMessage();

        return $timestamp;
    }

    /**
     * Not supported. Set the formatter's calendar.
     *
     * @param string $calendar The calendar to use. Default is IntlDateFormatter::GREGORIAN
     *
     * @return bool true on success or false on failure
     *
     * @see http://www.php.net/manual/en/intldateformatter.setcalendar.php
     *
     * @throws MethodNotImplementedException
     */
    public function setCalendar($calendar)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Set the leniency of the parser.
     *
     * Define if the parser is strict or lenient in interpreting inputs that do not match the pattern
     * exactly. Enabling lenient parsing allows the parser to accept otherwise flawed date or time
     * patterns, parsing as much as possible to obtain a value. Extra space, unrecognized tokens, or
     * invalid values ("February 30th") are not accepted.
     *
     * @param bool $lenient Sets whether the parser is lenient or not. Currently
     *                      only false (strict) is supported.
     *
     * @return bool true on success or false on failure
     *
     * @see http://www.php.net/manual/en/intldateformatter.setlenient.php
     *
     * @throws MethodArgumentValueNotImplementedException When $lenient is true
     */
    public function setLenient($lenient)
    {
        if ($lenient) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'lenient', $lenient, 'Only the strict parser is supported');
        }

        return true;
    }

    /**
     * Set the formatter's pattern.
     *
     * @param string $pattern A pattern string in conformance with the ICU IntlDateFormatter documentation
     *
     * @return bool true on success or false on failure
     *
     * @see http://www.php.net/manual/en/intldateformatter.setpattern.php
     * @see http://userguide.icu-project.org/formatparse/datetime
     */
    public function setPattern($pattern)
    {
        if (null === $pattern) {
            $pattern = $this->getDefaultPattern();
        }

        $this->pattern = $pattern;

        return true;
    }

    /**
     * Set the formatter's timezone identifier.
     *
     * @param string $timeZoneId The time zone ID string of the time zone to use
     *                           If NULL or the empty string, the default time zone for the
     *                           runtime is used.
     *
     * @return bool true on success or false on failure
     *
     * @see http://www.php.net/manual/en/intldateformatter.settimezoneid.php
     */
    public function setTimeZoneId($timeZoneId)
    {
        if (null === $timeZoneId) {
            // In PHP 5.5 if $timeZoneId is null it fallbacks to `date_default_timezone_get()` method
            if (PHP_VERSION_ID >= 50500 || (extension_loaded('intl') && method_exists('IntlDateFormatter', 'setTimeZone'))) {
                $timeZoneId = date_default_timezone_get();
            } else {
                // TODO: changes were made to ext/intl in PHP 5.4.4 release that need to be investigated since it will
                // use ini's date.timezone when the time zone is not provided. As a not well tested workaround, uses UTC.
                // See the first two items of the commit message for more information:
                // https://github.com/php/php-src/commit/eb346ef0f419b90739aadfb6cc7b7436c5b521d9
                $timeZoneId = getenv('TZ') ?: 'UTC';
            }

            $this->uninitializedTimeZoneId = true;
        }

        // Backup original passed time zone
        $timeZone = $timeZoneId;

        // Get an Etc/GMT time zone that is accepted for \DateTimeZone
        if ('GMT' !== $timeZoneId && 0 === strpos($timeZoneId, 'GMT')) {
            try {
                $timeZoneId = DateFormat\TimeZoneTransformer::getEtcTimeZoneId($timeZoneId);
            } catch (\InvalidArgumentException $e) {
                // Does nothing, will fallback to UTC
            }
        }

        try {
            $this->dateTimeZone = new \DateTimeZone($timeZoneId);
            if ('GMT' !== $timeZoneId && $this->dateTimeZone->getName() !== $timeZoneId) {
                $timeZoneId = $timeZone = $this->getTimeZoneId();
            }
        } catch (\Exception $e) {
            if (PHP_VERSION_ID >= 50500 || (extension_loaded('intl') && method_exists('IntlDateFormatter', 'setTimeZone'))) {
                $timeZoneId = $timeZone = $this->getTimeZoneId();
            } else {
                $timeZoneId = 'UTC';
            }
            $this->dateTimeZone = new \DateTimeZone($timeZoneId);
        }

        $this->timeZoneId = $timeZone;

        return true;
    }

    /**
     * This method was added in PHP 5.5 as replacement for `setTimeZoneId()`.
     *
     * @param mixed $timeZone
     *
     * @return bool true on success or false on failure
     *
     * @see http://www.php.net/manual/en/intldateformatter.settimezone.php
     */
    public function setTimeZone($timeZone)
    {
        if ($timeZone instanceof \IntlTimeZone) {
            $timeZone = $timeZone->getID();
        }

        if ($timeZone instanceof \DateTimeZone) {
            $timeZone = $timeZone->getName();

            // DateTimeZone returns the GMT offset timezones without the leading GMT, while our parsing requires it.
            if (!empty($timeZone) && ('+' === $timeZone[0] || '-' === $timeZone[0])) {
                $timeZone = 'GMT'.$timeZone;
            }
        }

        return $this->setTimeZoneId($timeZone);
    }

    /**
     * Create and returns a DateTime object with the specified timestamp and with the
     * current time zone.
     *
     * @param int $timestamp
     *
     * @return \DateTime
     */
    protected function createDateTime($timestamp)
    {
        $dateTime = new \DateTime();
        $dateTime->setTimestamp($timestamp);
        $dateTime->setTimezone($this->dateTimeZone);

        return $dateTime;
    }

    /**
     * Returns a pattern string based in the datetype and timetype values.
     *
     * @return string
     */
    protected function getDefaultPattern()
    {
        $patternParts = array();
        if (self::NONE !== $this->datetype) {
            $patternParts[] = $this->defaultDateFormats[$this->datetype];
        }
        if (self::NONE !== $this->timetype) {
            $patternParts[] = $this->defaultTimeFormats[$this->timetype];
        }

        return implode(', ', $patternParts);
    }
}
