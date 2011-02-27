<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub\DateFormat;

use Symfony\Component\Locale\Exception\NotImplementedException;
use Symfony\Component\Locale\Stub\DateFormat\MonthTransformer;

/**
 * Parser and formatter for date formats
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class FullTransformer
{
    private $quoteMatch = "'(?:[^']+|'')*'";
    private $implementedChars = 'MLydGQqhDEaHkKmsz';
    private $notImplementedChars = 'YuwWFgecSAZvVW';
    private $regExp;

    private $transformers;
    private $pattern;
    private $timezone;

    public function __construct($pattern, $timezone)
    {
        $this->pattern = $pattern;
        $this->timezone = $timezone;

        $implementedCharsMatch = $this->buildCharsMatch($this->implementedChars);
        $notImplementedCharsMatch = $this->buildCharsMatch($this->notImplementedChars);
        $this->regExp = "/($this->quoteMatch|$implementedCharsMatch|$notImplementedCharsMatch)/";

        $this->transformers = array(
            'M' => new MonthTransformer(),
            'L' => new MonthTransformer(),
            'y' => new YearTransformer(),
            'd' => new DayTransformer(),
            'G' => new EraTransformer(),
            'q' => new QuarterTransformer(),
            'Q' => new QuarterTransformer(),
            'h' => new Hour1201Transformer(),
            'D' => new DayOfYearTransformer(),
            'E' => new DayOfWeekTransformer(),
            'a' => new AmPmTransformer(),
            'H' => new Hour2400Transformer(),
            'K' => new Hour1200Transformer(),
            'k' => new Hour2401Transformer(),
            'm' => new MinuteTransformer(),
            's' => new SecondTransformer(),
            'z' => new TimeZoneTransformer(),
        );
    }

    public function getTransformers()
    {
        return $this->transformers;
    }

    public function format(\DateTime $dateTime)
    {
        $that = $this;

        $formatted = preg_replace_callback($this->regExp, function($matches) use ($that, $dateTime) {
            return $that->formatReplace($matches[0], $dateTime);
        }, $this->pattern);

        return $formatted;
    }

    public function formatReplace($dateChars, $dateTime)
    {
        $length = strlen($dateChars);

        if ("'" === $dateChars[0]) {
            if (preg_match("/^'+$/", $dateChars)) {
                return str_replace("''", "'", $dateChars);
            }
            return str_replace("''", "'", substr($dateChars, 1, -1));
        }

        if (isset($this->transformers[$dateChars[0]])) {
            $transformer = $this->transformers[$dateChars[0]];
            return $transformer->format($dateTime, $length);
        } else {
            // handle unimplemented characters
            if (false !== strpos($this->notImplementedChars, $dateChars[0])) {
                throw new NotImplementedException(sprintf("Unimplemented date character '%s' in format '%s'", $dateChars[0], $this->pattern));
            }
        }
    }

    public function getReverseMatchingRegExp($pattern)
    {
        $that = $this;

        $reverseMatchingRegExp = preg_replace_callback($this->regExp, function($matches) use ($that) {
            $length = strlen($matches[0]);
            $transformerIndex = $matches[0][0];

            $transformers = $that->getTransformers();

            if (isset($transformers[$transformerIndex])) {
                $transformer = $transformers[$transformerIndex];
                $captureName = str_repeat($transformerIndex, $length);
                return "(?P<$captureName>" . $transformer->getReverseMatchingRegExp($length) . ')';
            }
        }, $pattern);

        return $reverseMatchingRegExp;
    }

    public function parse($value)
    {
        $reverseMatchingRegExp = $this->getReverseMatchingRegExp($this->pattern);
        $reverseMatchingRegExp = '/'.$reverseMatchingRegExp.'/';

        $options = array();

        if (preg_match($reverseMatchingRegExp, $value, $matches)) {
            $matches = $this->normalizeArray($matches);

            foreach ($this->transformers as $char => $transformer) {
                if (isset($matches[$char])) {
                    $length = strlen($matches[$char]['pattern']);
                    $options = array_merge($options, $transformer->extractDateOptions($matches[$char]['value'], $length));
                }
            }

            return $this->calculateUnixTimestamp($options);
        }

        throw new \InvalidArgumentException(sprintf("Failed to match value '%s' with pattern '%s'", $value, $this->pattern));
    }

    protected function buildCharsMatch($specialChars)
    {
        $specialCharsArray = str_split($specialChars);

        $specialCharsMatch = implode('|', array_map(function($char) {
            return $char . '+';
        }, $specialCharsArray));

        return $specialCharsMatch;
    }

    protected function normalizeArray(array $data)
    {
        $ret = array();

        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $ret[$key[0]] = array(
                'value' => $value,
                'pattern' => $key
            );
        }

        return $ret;
    }

    private function calculateUnixTimestamp(array $options)
    {
        $datetime = $this->extractDateTime($options);

        $year     = $datetime['year'];
        $month    = $datetime['month'];
        $day      = $datetime['day'];
        $hour     = $datetime['hour'];
        $minute   = $datetime['minute'];
        $second   = $datetime['second'];
        $marker   = $datetime['marker'];
        $hourType = $datetime['hourType'];

        // If month is false, return immediately
        if (false === $month) {
            return false;
        }

        // If the AM/PM marker is AM or null, the hour is 12 (1-12) and the capture was 'h' or 'hh', the hour is 0
        if ('1201' === $hourType && 'PM' !== $marker && 12 === $hour) {
            $hour = 0;
        }

        // If PM and hour is not 12 (1-12), sum 12 hour
        if ('1201' === $hourType && 'PM' === $marker && 12 !== $hour) {
            $hour = $hour + 12;
        }

        // If PM, sum 12 hours when 12 hour (0-11)
        if ('1200' === $hourType && 'PM' === $marker) {
            $hour = $hour + 12;
        }

        // If 24 hours (0-23 or 1-24) and marker is set, hour is 0
        if (('2400' === $hourType || '2401' === $hourType) && null !== $marker) {
            $hour = 0;
        }

        // If 24 hours (1-24) and hour is 24, hour is 0
        if ('2401' === $hourType && 24 === $hour) {
            $hour = 0;
        }

        // Set the timezone
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set($this->timezone);

        $timestamp = mktime($hour, $minute, $second, $month, $day, $year);

        // Restore timezone
        date_default_timezone_set($originalTimezone);

        return $timestamp;
    }

    private function extractDateTime(array $datetime)
    {
        return array(
            'year'     => isset($datetime['year']) ? $datetime['year'] : 1970,
            'month'    => isset($datetime['month']) ? $datetime['month'] : 1,
            'day'      => isset($datetime['day']) ? $datetime['day'] : 1,
            'hour'     => isset($datetime['hour']) ? $datetime['hour'] : 0,
            'minute'   => isset($datetime['minute']) ? $datetime['minute'] : 0,
            'second'   => isset($datetime['second']) ? $datetime['second'] : 0,
            'marker'   => isset($datetime['marker']) ? $datetime['marker'] : null,
            'hourType' => isset($datetime['hourType']) ? $datetime['hourType'] : null,
        );
    }
}
