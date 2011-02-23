<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub;

use Symfony\Component\Locale\Stub\StubLocale;
use Symfony\Component\Locale\Exception\NotImplementedException;
use Symfony\Component\Locale\Exception\MethodNotImplementedException;
use Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException;

/**
 * Provides a stub IntlDateFormatter for the 'en' locale.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class StubIntlDateFormatter
{
    /**
     * Constants defined by the intl extension, not class constants in IntlDateFormatter
     * TODO: remove if the Form component drop the call to the intl_is_failure() function
     *
     * @see StubIntlDateFormatter::getErrorCode()
     * @see StubIntlDateFormatter::getErrorMessage()
     */
    const U_ZERO_ERROR = 0;
    const U_ZERO_ERROR_MESSAGE = 'U_ZERO_ERROR';

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
     * Patterns used to format the date when no pattern
     * is provided
     */
    private $defaultDateFormats = array(
        self::NONE      => '',
        self::FULL      => 'EEEE, LLLL d, y',
        self::LONG      => 'LLLL d, y',
        self::MEDIUM    => 'LLL d, y',
        self::SHORT     => 'M/d/yy',
    );

    /**
     * Patterns used to format the time when no pattern
     * is provided
     */
    private $defaultTimeFormats = array(
        self::FULL      => 'h:mm:ss a zzzz',
        self::LONG      => 'h:mm:ss a z',
        self::MEDIUM    => 'h:mm:ss a',
        self::SHORT     => 'h:mm a',
    );

    private $datetype;
    private $timetype;
    private $pattern;

    /**
     * @var DateTimeZone
     */
    private $dateTimeZone;

    /**
     * Constructor
     *
     * @param  string  $locale   The locale code
     * @param  int     $datetype Type of date formatting, one of the format type constants
     * @param  int     $timetype Type of time formatting, one of the format type constants
     * @param  string  $timezone Timezone identifier
     * @param  int     $calendar Calendar to use for formatting or parsing; default is Gregorian.
     *                           One of the calendar constants.
     * @param  string  $pattern  Optional pattern to use when formatting.
     * @see    http://userguide.icu-project.org/formatparse/datetime
     * @throws MethodArgumentValueNotImplementedException  When $locale different than 'en' is passed
     * @throws MethodArgumentValueNotImplementedException  When $calendar different than GREGORIAN is passed
     */
    public function __construct($locale, $datetype, $timetype, $timezone = null, $calendar = self::GREGORIAN, $pattern = null)
    {
        if ('en' != $locale) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'locale', $locale, 'Only the \'en\' locale is supported');
        }

        if (self::GREGORIAN != $calendar) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'calendar', $calendar, 'Only the GREGORIAN calendar is supported');
        }

        $this->datetype = $datetype;
        $this->timetype = $timetype;

        $this->setPattern($pattern);
        $this->setTimeZoneId($timezone);
    }

    /**
     * Format the date/time value (timestamp) as a string
     *
     * @param  int         $timestamp   Unix timestamp to format
     * @return string                   The formatted value
     * @throws NotImplementedException  If one of the formatting characters is not implemented
     */
    public function format($timestamp)
    {
        $dateTime = $this->createDateTime($timestamp);
        $dateTime->setTimestamp($timestamp);
        $dateTime->setTimezone($this->dateTimeZone);

        $quoteMatch = "'(?:[^']+|'')*'";
        $implementedCharsMatch = $this->buildCharsMatch('MLydGQqhDEaHkKmsz');

        $notImplementedChars = 'YuwWFgecSAZvVW';
        $notImplementedCharsMatch = $this->buildCharsMatch($notImplementedChars);

        $regExp = "/($quoteMatch|$implementedCharsMatch|$notImplementedCharsMatch)/";

        $pattern = $this->getPattern();

        $callback = function($matches) use ($dateTime, $notImplementedChars, $pattern) {
            $dateChars = $matches[0];
            $length = strlen($dateChars);

            if ("'" === $dateChars[0]) {
                if (preg_match("/^'+$/", $dateChars)) {
                    return str_replace("''", "'", $dateChars);
                }
                return str_replace("''", "'", substr($dateChars, 1, -1));
            }

            switch ($dateChars[0]) {
                case 'M':
                case 'L':
                    $matchLengthMap = array(
                        1   => 'n',
                        2   => 'm',
                        3   => 'M',
                        4   => 'F',
                    );

                    if (isset($matchLengthMap[$length])) {
                       return $dateTime->format($matchLengthMap[$length]);
                    } else if (5 == $length) {
                        return substr($dateTime->format('M'), 0, 1);
                    } else {
                        return str_pad($dateTime->format('m'), $length, '0', STR_PAD_LEFT);
                    }
                    break;

                case 'y':
                    if (2 == $length) {
                       return $dateTime->format('y');
                    } else {
                        return str_pad($dateTime->format('Y'), $length, '0', STR_PAD_LEFT);
                    }
                    break;

                case 'd':
                    return str_pad($dateTime->format('j'), $length, '0', STR_PAD_LEFT);
                    break;

                case 'G':
                    $year = (int) $dateTime->format('Y');
                    return $year >= 0 ? 'AD' : 'BC';
                    break;

                case 'q':
                case 'Q':
                    $month = (int) $dateTime->format('n');
                    $quarter = (int) floor(($month - 1) / 3) + 1;
                    switch ($length) {
                        case 1:
                        case 2:
                            return str_pad($quarter, $length, '0', STR_PAD_LEFT);
                            break;
                        case 3:
                            return 'Q' . $quarter;
                            break;
                        default:
                            $map = array(1 => '1st quarter', 2 => '2nd quarter', 3 => '3rd quarter', 4 => '4th quarter');
                            return $map[$quarter];
                            break;
                    }
                    break;

                case 'h':
                    return str_pad($dateTime->format('g'), $length, '0', STR_PAD_LEFT);
                    break;

                case 'D':
                    $dayOfYear = $dateTime->format('z') + 1;
                    return str_pad($dayOfYear, $length, '0', STR_PAD_LEFT);
                    break;

                case 'E':
                    $dayOfWeek = $dateTime->format('l');
                    switch ($length) {
                        case 4:
                            return $dayOfWeek;
                            break;
                        case 5:
                            return $dayOfWeek[0];
                            break;
                        default:
                            return substr($dayOfWeek, 0, 3);
                    }
                    break;

                case 'a':
                    return $dateTime->format('A');
                    break;

                case 'H':
                    return str_pad($dateTime->format('G'), $length, '0', STR_PAD_LEFT);
                    break;

                case 'k':
                    $hourOfDay = $dateTime->format('G');
                    $hourOfDay = ('0' == $hourOfDay) ? '24' : $hourOfDay;
                    return str_pad($hourOfDay, $length, '0', STR_PAD_LEFT);
                    break;

                case 'K':
                    $hourOfDay = $dateTime->format('g');
                    $hourOfDay = ('12' == $hourOfDay) ? '0' : $hourOfDay;
                    return str_pad($hourOfDay, $length, '0', STR_PAD_LEFT);
                    break;

                case 'm':
                    $minuteOfHour = (int) $dateTime->format('i');
                    return str_pad($minuteOfHour, $length, '0', STR_PAD_LEFT);
                    break;

                case 's':
                    $secondOfMinute = (int) $dateTime->format('s');
                    return str_pad($secondOfMinute, $length, '0', STR_PAD_LEFT);
                    break;

                case 'z':
                    return $dateTime->format('\G\M\TP');
                    break;

                default:
                    // handle unimplemented characters
                    if (false !== strpos($notImplementedChars, $dateChars[0])) {
                        throw new NotImplementedException(sprintf("Unimplemented date character '%s' in format '%s'", $dateChars[0], $pattern));
                    }
                    break;
            }
        };

        $formatted = preg_replace_callback($regExp, $callback, $pattern);

        return $formatted;
    }

    /**
     * Returns the formatter's calendar
     *
     * @return int              The calendar being used by the formatter
     */
    public function getCalendar()
    {
        return self::GREGORIAN;
    }

    /**
     * Returns the formatter's datetype
     *
     * @return int              The current value of the formatter
     */
    public function getDateType()
    {
        return $this->datetype;
    }

    /**
     * Returns formatter's last error code. Always returns the U_ZERO_ERROR class constant value
     *
     * @return int  The error code from last formatter call
     */
    public function getErrorCode()
    {
        return self::U_ZERO_ERROR;
    }

    /**
     * Returns formatter's last error message. Always returns the U_ZERO_ERROR_MESSAGE class constant value
     *
     * @return string  The error message from last formatter call
     */
    public function getErrorMessage()
    {
        return self::U_ZERO_ERROR_MESSAGE;
    }

    /**
     * Returns the formatter's locale
     *
     * @param  int      $type     The locale name type to return between valid or actual (StubLocale::VALID_LOCALE or StubLocale::ACTUAL_LOCALE, respectively)
     * @return string             The locale name used to create the formatter
     */
    public function getLocale($type = StubLocale::ACTUAL_LOCALE)
    {
        return 'en';
    }

    /**
     * Returns the formatter's pattern
     *
     * @return string        The pattern string used by the formatter
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * Returns the formatter's time type
     *
     * @return string        The time type used by the formatter
     */
    public function getTimeType()
    {
        return $this->timetype;
    }

    /**
     * Returns the formatter's timezone identifier
     *
     * @return string        The timezone identifier used by the formatter
     */
    public function getTimeZoneId()
    {
        return $this->dateTimeZone->getName();
    }

    /**
     * Returns whether the formatter is lenient
     *
     * @return string        The timezone identifier used by the formatter
     * @throws MethodNotImplementedException
     */
    public function isLenient()
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Parse string to a field-based time value
     *
     * @param  string   $value      String to convert to a time value
     * @param  int      $position   Position at which to start the parsing in $value (zero-based).
     *                              If no error occurs before $value is consumed, $parse_pos will
     *                              contain -1 otherwise it will contain the position at which parsing
     *                              ended. If $parse_pos > strlen($value), the parse fails immediately. 
     * @return string               Localtime compatible array of integers: contains 24 hour clock value in tm_hour field 
     * @throws MethodNotImplementedException
     */
    public function localtime($value, &$position = 0)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Parse string to a timestamp value
     *
     * @param  string   $value      String to convert to a time value
     * @param  int      $position   Position at which to start the parsing in $value (zero-based).
     *                              If no error occurs before $value is consumed, $parse_pos will
     *                              contain -1 otherwise it will contain the position at which parsing
     *                              ended. If $parse_pos > strlen($value), the parse fails immediately. 
     * @return string               Parsed value as a timestamp
     * @throws MethodNotImplementedException
     */
    public function parse($value, &$position = 0)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Set the formatter's calendar
     *
     * @param  string  $calendar  The calendar to use. Default is IntlDateFormatter::GREGORIAN.
     * @return bool               true on success or false on failure
     * @throws MethodNotImplementedException
     */
    public function setCalendar($calendar)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Set the leniency of the parser
     *
     * Define if the parser is strict or lenient in interpreting inputs that do not match the pattern
     * exactly. Enabling lenient parsing allows the parser to accept otherwise flawed date or time
     * patterns, parsing as much as possible to obtain a value. Extra space, unrecognized tokens, or
     * invalid values ("February 30th") are not accepted.
     *
     * @param  bool  $lenient     Sets whether the parser is lenient or not, default is false (strict)
     * @return bool               true on success or false on failure
     * @throws MethodNotImplementedException
     */
    public function setLenient($lenient)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Set the formatter's pattern
     *
     * @param  strubg  $pattern   A pattern string in conformance with the ICU IntlDateFormatter documentation
     * @return bool               true on success or false on failure
     * @see    http://userguide.icu-project.org/formatparse/datetime
     */
    public function setPattern($pattern)
    {
        if (null === $pattern) {
            $pattern = $this->getDefaultPattern();
        }

        $this->pattern = $pattern;
    }

    /**
     * Set the formatter's timezone identifier
     *
     * @param  string  $timeZoneId      The time zone ID string of the time zone to use.
     *                                  If NULL or the empty string, the default time zone for the
     *                                  runtime is used.
     * @return bool                     true on success or false on failure
     */
    public function setTimeZoneId($timeZoneId)
    {
        try {
            $this->dateTimeZone = new \DateTimeZone($timeZoneId);
        } catch (\Exception $e) {
            $this->dateTimeZone = new \DateTimeZone('UTC');
        }
    }

    protected function createDateTime($timestamp)
    {
        $dateTime = new \DateTime();
        $dateTime->setTimestamp($timestamp);
        $dateTime->setTimezone($this->dateTimeZone);

        return $dateTime;
    }

    protected function buildCharsMatch($specialChars)
    {
        $specialCharsArray = str_split($specialChars);

        $specialCharsMatch = implode('|', array_map(function($char) {
            return $char . '+';
        }, $specialCharsArray));

        return $specialCharsMatch;
    }

    protected function getDefaultPattern()
    {
        $patternParts = array();
        if (self::NONE !== $this->datetype) {
            $patternParts[] = $this->defaultDateFormats[$this->datetype];
        }
        if (self::NONE !== $this->timetype) {
            $patternParts[] = $this->defaultTimeFormats[$this->timetype];
        }
        $pattern = implode(' ', $patternParts);

        return $pattern;
    }

    /**
     * Static constructor
     *
     * @param  string  $locale   The locale code
     * @param  int     $datetype Type of date formatting, one of the format type constants
     * @param  int     $timetype Type of time formatting, one of the format type constants
     * @param  string  $timezone Timezone identifier
     * @param  int     $calendar Calendar to use for formatting or parsing; default is Gregorian.
     *                           One of the calendar constants.
     * @param  string  $pattern  Optional pattern to use when formatting.
     * @see    http://userguide.icu-project.org/formatparse/datetime
     * @throws MethodArgumentValueNotImplementedException  When $locale different than 'en' is passed
     */
    static public function create($locale, $datetype, $timetype, $timezone = null, $calendar = self::GREGORIAN, $pattern = null)
    {
        return new self($locale, $datetype, $timetype, $timezone, $calendar, $pattern);
    }
}
