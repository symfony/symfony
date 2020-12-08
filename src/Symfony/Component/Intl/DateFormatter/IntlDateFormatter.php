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

use Symfony\Component\Intl\DateFormatter\DateFormat\FullTransformer;
use Symfony\Component\Intl\Exception\MethodArgumentNotImplementedException;
use Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException;
use Symfony\Component\Intl\Exception\MethodNotImplementedException;
use Symfony\Component\Intl\Globals\IntlGlobals;
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
abstract class IntlDateFormatter
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
    public const NONE = -1;
    public const FULL = 0;
    public const LONG = 1;
    public const MEDIUM = 2;
    public const SHORT = 3;

    /* calendar formats */
    public const TRADITIONAL = 0;
    public const GREGORIAN = 1;

    /**
     * Patterns used to format the date when no pattern is provided.
     */
    private $defaultDateFormats = [
        self::NONE => '',
        self::FULL => 'EEEE, MMMM d, y',
        self::LONG => 'MMMM d, y',
        self::MEDIUM => 'MMM d, y',
        self::SHORT => 'M/d/yy',
    ];

    /**
     * Patterns used to format the time when no pattern is provided.
     */
    private $defaultTimeFormats = [
        self::FULL => 'h:mm:ss a zzzz',
        self::LONG => 'h:mm:ss a z',
        self::MEDIUM => 'h:mm:ss a',
        self::SHORT => 'h:mm a',
    ];

    private $datetype;
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
     * @param string|null                             $locale   The locale code. The only currently supported locale is "en" (or null using the default locale, i.e. "en")
     * @param int|null                                $datetype Type of date formatting, one of the format type constants
     * @param int|null                                $timetype Type of time formatting, one of the format type constants
     * @param \IntlTimeZone|\DateTimeZone|string|null $timezone Timezone identifier
     * @param int                                     $calendar Calendar to use for formatting or parsing. The only currently
     *                                                          supported value is IntlDateFormatter::GREGORIAN (or null using the default calendar, i.e. "GREGORIAN")
     * @param string|null                             $pattern  Optional pattern to use when formatting
     *
     * @see https://php.net/intldateformatter.create
     * @see http://userguide.icu-project.org/formatparse/datetime
     *
     * @throws MethodArgumentValueNotImplementedException When $locale different than "en" or null is passed
     * @throws MethodArgumentValueNotImplementedException When $calendar different than GREGORIAN is passed
     */
    public function __construct(?string $locale, ?int $datetype, ?int $timetype, $timezone = null, ?int $calendar = self::GREGORIAN, string $pattern = null)
    {
        if ('en' !== $locale && null !== $locale) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'locale', $locale, 'Only the locale "en" is supported');
        }

        if (self::GREGORIAN !== $calendar && null !== $calendar) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'calendar', $calendar, 'Only the GREGORIAN calendar is supported');
        }

        $this->datetype = null !== $datetype ? $datetype : self::FULL;
        $this->timetype = null !== $timetype ? $timetype : self::FULL;

        $this->setPattern($pattern);
        $this->setTimeZone($timezone);
    }

    /**
     * Static constructor.
     *
     * @param string|null                             $locale   The locale code. The only currently supported locale is "en" (or null using the default locale, i.e. "en")
     * @param int|null                                $datetype Type of date formatting, one of the format type constants
     * @param int|null                                $timetype Type of time formatting, one of the format type constants
     * @param \IntlTimeZone|\DateTimeZone|string|null $timezone Timezone identifier
     * @param int                                     $calendar Calendar to use for formatting or parsing; default is Gregorian
     *                                                          One of the calendar constants
     * @param string|null                             $pattern  Optional pattern to use when formatting
     *
     * @return static
     *
     * @see https://php.net/intldateformatter.create
     * @see http://userguide.icu-project.org/formatparse/datetime
     *
     * @throws MethodArgumentValueNotImplementedException When $locale different than "en" or null is passed
     * @throws MethodArgumentValueNotImplementedException When $calendar different than GREGORIAN is passed
     */
    public static function create(?string $locale, ?int $datetype, ?int $timetype, $timezone = null, int $calendar = self::GREGORIAN, ?string $pattern = null)
    {
        return new static($locale, $datetype, $timetype, $timezone, $calendar, $pattern);
    }

    /**
     * Format the date/time value (timestamp) as a string.
     *
     * @param int|\DateTimeInterface $timestamp The timestamp to format
     *
     * @return string|bool The formatted value or false if formatting failed
     *
     * @see https://php.net/intldateformatter.format
     *
     * @throws MethodArgumentValueNotImplementedException If one of the formatting characters is not implemented
     */
    public function format($timestamp)
    {
        // intl allows timestamps to be passed as arrays - we don't
        if (\is_array($timestamp)) {
            $message = 'Only integer Unix timestamps and DateTime objects are supported';

            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'timestamp', $timestamp, $message);
        }

        // behave like the intl extension
        $argumentError = null;
        if (!\is_int($timestamp) && !$timestamp instanceof \DateTimeInterface) {
            $argumentError = sprintf('datefmt_format: string \'%s\' is not numeric, which would be required for it to be a valid date', $timestamp);
        }

        if (null !== $argumentError) {
            IntlGlobals::setError(IntlGlobals::U_ILLEGAL_ARGUMENT_ERROR, $argumentError);
            $this->errorCode = IntlGlobals::getErrorCode();
            $this->errorMessage = IntlGlobals::getErrorMessage();

            return false;
        }

        if ($timestamp instanceof \DateTimeInterface) {
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
     * @param mixed  $format
     * @param string $locale
     *
     * @return string The formatted value
     *
     * @see https://php.net/intldateformatter.formatobject
     *
     * @throws MethodNotImplementedException
     */
    public function formatObject(object $object, $format = null, string $locale = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the formatter's calendar.
     *
     * @return int The calendar being used by the formatter. Currently always returns
     *             IntlDateFormatter::GREGORIAN.
     *
     * @see https://php.net/intldateformatter.getcalendar
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
     * @see https://php.net/intldateformatter.getcalendarobject
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
     * @see https://php.net/intldateformatter.getdatetype
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
     * @see https://php.net/intldateformatter.geterrorcode
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
     * @see https://php.net/intldateformatter.geterrormessage
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
     * @see https://php.net/intldateformatter.getlocale
     */
    public function getLocale(int $type = Locale::ACTUAL_LOCALE)
    {
        return 'en';
    }

    /**
     * Returns the formatter's pattern.
     *
     * @return string The pattern string used by the formatter
     *
     * @see https://php.net/intldateformatter.getpattern
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * Returns the formatter's time type.
     *
     * @return int The time type used by the formatter
     *
     * @see https://php.net/intldateformatter.gettimetype
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
     * @see https://php.net/intldateformatter.gettimezoneid
     */
    public function getTimeZoneId()
    {
        if (!$this->uninitializedTimeZoneId) {
            return $this->timeZoneId;
        }

        return date_default_timezone_get();
    }

    /**
     * Not supported. Returns the formatter's timezone.
     *
     * @return mixed The timezone used by the formatter
     *
     * @see https://php.net/intldateformatter.gettimezone
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
     * @see https://php.net/intldateformatter.islenient
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
     * @see https://php.net/intldateformatter.localtime
     *
     * @throws MethodNotImplementedException
     */
    public function localtime(string $value, int &$position = 0)
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
     * @return int|false Parsed value as a timestamp
     *
     * @see https://php.net/intldateformatter.parse
     *
     * @throws MethodArgumentNotImplementedException When $position different than null, behavior not implemented
     */
    public function parse(string $value, int &$position = null)
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
     * @see https://php.net/intldateformatter.setcalendar
     *
     * @throws MethodNotImplementedException
     */
    public function setCalendar(string $calendar)
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
     * @see https://php.net/intldateformatter.setlenient
     *
     * @throws MethodArgumentValueNotImplementedException When $lenient is true
     */
    public function setLenient(bool $lenient)
    {
        if ($lenient) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'lenient', $lenient, 'Only the strict parser is supported');
        }

        return true;
    }

    /**
     * Set the formatter's pattern.
     *
     * @param string|null $pattern A pattern string in conformance with the ICU IntlDateFormatter documentation
     *
     * @return bool true on success or false on failure
     *
     * @see https://php.net/intldateformatter.setpattern
     * @see http://userguide.icu-project.org/formatparse/datetime
     */
    public function setPattern(?string $pattern)
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
     * @param string|null $timeZoneId The time zone ID string of the time zone to use.
     *                                If NULL or the empty string, the default time zone for the
     *                                runtime is used.
     *
     * @return bool true on success or false on failure
     *
     * @see https://php.net/intldateformatter.settimezoneid
     */
    public function setTimeZoneId(?string $timeZoneId)
    {
        if (null === $timeZoneId) {
            $timeZoneId = date_default_timezone_get();

            $this->uninitializedTimeZoneId = true;
        }

        // Backup original passed time zone
        $timeZone = $timeZoneId;

        // Get an Etc/GMT time zone that is accepted for \DateTimeZone
        if ('GMT' !== $timeZoneId && 0 === strpos($timeZoneId, 'GMT')) {
            try {
                $timeZoneId = DateFormat\TimezoneTransformer::getEtcTimeZoneId($timeZoneId);
            } catch (\InvalidArgumentException $e) {
                // Does nothing, will fallback to UTC
            }
        }

        try {
            $this->dateTimeZone = new \DateTimeZone($timeZoneId);
            if ('GMT' !== $timeZoneId && $this->dateTimeZone->getName() !== $timeZoneId) {
                $timeZone = $this->getTimeZoneId();
            }
        } catch (\Exception $e) {
            $timeZoneId = $timeZone = $this->getTimeZoneId();
            $this->dateTimeZone = new \DateTimeZone($timeZoneId);
        }

        $this->timeZoneId = $timeZone;

        return true;
    }

    /**
     * This method was added in PHP 5.5 as replacement for `setTimeZoneId()`.
     *
     * @param \IntlTimeZone|\DateTimeZone|string|null $timeZone
     *
     * @return bool true on success or false on failure
     *
     * @see https://php.net/intldateformatter.settimezone
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
     * @return \DateTime
     */
    protected function createDateTime(int $timestamp)
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
        $pattern = '';
        if (self::NONE !== $this->datetype) {
            $pattern = $this->defaultDateFormats[$this->datetype];
        }
        if (self::NONE !== $this->timetype) {
            if (self::FULL === $this->datetype || self::LONG === $this->datetype) {
                $pattern .= ' \'at\' ';
            } elseif (self::NONE !== $this->datetype) {
                $pattern .= ', ';
            }
            $pattern .= $this->defaultTimeFormats[$this->timetype];
        }

        return $pattern;
    }
}
