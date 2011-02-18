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

use Symfony\Component\Locale\Exception\MethodNotImplementedException;
use Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException;

/**
 * Provides a stub IntlDateFormatter for the 'en' locale.
 */
class StubIntlDateFormatter
{
    /* formats */
    const NONE = -1;
    const FULL = 0;
    const LONG = 1;
    const MEDIUM = 2;
    const SHORT = 3;

    /* formats */
    const TRADITIONAL = 0;
    const GREGORIAN = 1;

    private $defaultDateFormats = array(
        self::NONE      => '',
        self::FULL      => 'EEEE, LLLL d, y',
        self::LONG      => 'LLLL d, y',
        self::MEDIUM    => 'LLL d, y',
        self::SHORT     => 'M/d/yy',
    );

    private $defaultTimeFormats = array(
        self::FULL      => 'h:mm:ss a zzzz',
        self::LONG      => 'h:mm:ss a z',
        self::MEDIUM    => 'h:mm:ss a',
        self::SHORT     => 'h:mm a',
    );

    private $datetype;
    private $timetype;
    private $pattern;

    private $dateTimeZone;

    public function __construct($locale, $datetype, $timetype, $timezone = null, $calendar = null, $pattern = null)
    {
        if ('en' != $locale) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'locale', $locale, 'Only the \'en\' locale is supported');
        }

        $this->datetype = $datetype;
        $this->timetype = $timetype;

        $this->setPattern($pattern);
        $this->setTimeZoneId($timezone);
    }

    public function format($timestamp)
    {
        $dateTime = new \DateTime();
        $dateTime->setTimestamp($timestamp);
        $dateTime->setTimezone($this->dateTimeZone);

        // not implemented: YuwWFgecSAZvVW
        $specialChars = 'MLydGQqhDEaHkKmsz';
        $specialCharsArray = str_split($specialChars);
        $specialCharsMatch = implode('|', array_map(function($char) {
            return $char . '+';
        }, $specialCharsArray));
        $quoteMatch = "'(?:[^']+|'')*'";
        $regExp = "/($quoteMatch|$specialCharsMatch)/";

        $callback = function($matches) use ($dateTime) {
            $datePattern = $matches[0];
            $length = strlen($datePattern);

            if ("'" === $datePattern[0]) {
                if (preg_match("/^'+$/", $datePattern)) {
                    return str_replace("''", "'", $datePattern);
                }
                return str_replace("''", "'", substr($datePattern, 1, -1));
            }

            switch ($datePattern[0]) {
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
                    $matchLengthMap = array(
                        1   => 'Y',
                        2   => 'y',
                        3   => 'Y',
                        4   => 'Y',
                    );

                    if (isset($matchLengthMap[$length])) {
                       return $dateTime->format($matchLengthMap[$length]);
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
            }
        };

        $formatted = preg_replace_callback($regExp, $callback, $this->getPattern());

        return $formatted;
    }

    public function getCalendar()
    {
        return self::GREGORIAN;
    }

    public function getDateType()
    {
        return $this->datetype;
    }

    public function getErrorCode()
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    public function getErrorMessage()
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    public function getLocale()
    {
        return 'en';
    }

    public function getPattern()
    {
        return $this->pattern;
    }

    public function getTimeType()
    {
        return $this->timetype;
    }

    public function getTimeZoneId()
    {
        return $this->dateTimeZone->getName();
    }

    public function isLenient()
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    public function localtime($value, &$position = 0)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    public function parse($value, &$position = 0)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    public function setCalendar()
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    public function setLenient($lenient)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    public function setPattern($pattern)
    {
        if (null === $pattern) {
            $patternParts = array();
            if (self::NONE !== $this->datetype) {
                $patternParts[] = $this->defaultDateFormats[$this->datetype];
            }
            if (self::NONE !== $this->timetype) {
                $patternParts[] = $this->defaultTimeFormats[$this->timetype];
            }
            $pattern = implode(' ', $patternParts);
        }

        $this->pattern = $pattern;
    }

    public function setTimeZoneId($timeZoneId)
    {
        try {
            $this->dateTimeZone = new \DateTimeZone($timeZoneId);
        } catch (\Exception $e) {
            $this->dateTimeZone = new \DateTimeZone('UTC');
        }
    }

    static public function create($locale, $datetype, $timetype, $timezone = null, $calendar = null, $pattern = null)
    {
        return new self($locale, $datetype, $timetype, $timezone, $calendar, $pattern);
    }
}
