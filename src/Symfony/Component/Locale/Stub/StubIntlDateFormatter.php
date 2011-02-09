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

use Symfony\Component\Locale\Locale;

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

    private $pattern;
    private $dateTimeZone;

    public function __construct($locale, $datetype, $timetype, $timezone = null, $calendar = null, $pattern = null)
    {
        if ('en' != $locale) {
            throw new \InvalidArgumentException('Unsupported $locale value. Only the \'en\' locale is supported. Install the intl extension for full localization capabilities.');
        }

        $this->setPattern($pattern);

        try {
            $this->dateTimeZone = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            $this->dateTimeZone = new \DateTimeZone('UTC');
        }
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
        $quoteMatch = "'(?>(?>[^']+|'{2}+|)?)'";
        $regExp = "/($quoteMatch|$specialCharsMatch)/";

        $callback = function($matches) use ($dateTime) {
            $pattern = $matches[0];
            $length = strlen($pattern);

            if ("'" === $pattern[0]) {
                return preg_replace("/'{2}/", "'", substr($pattern, 1, -1));
            }

            switch ($pattern[0]) {
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

    public function getPattern()
    {
        return $this->pattern;
    }

    public function getCalendar()
    {
        $this->throwMethodNotImplementException(__METHOD__);
    }

    public function setPattern($pattern)
    {
        $this->pattern = $pattern;
    }

    private function throwMethodNotImplementException($methodName)
    {
        $message = sprintf('The %s::%s() is not implemented. Install the intl extension for full localization capabilities.', __CLASS__, $methodName);
        throw new \RuntimeException($message);
    }
}
