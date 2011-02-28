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
    private $implementedChars = 'MLydQqhDEaHkKmsz';
    private $notImplementedChars = 'GYuwWFgecSAZvVW';
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

        $escapedPattern = preg_quote($pattern, '/');

        $reverseMatchingRegExp = preg_replace_callback($this->regExp, function($matches) use ($that) {
            $length = strlen($matches[0]);
            $transformerIndex = $matches[0][0];

            $transformers = $that->getTransformers();

            if (isset($transformers[$transformerIndex])) {
                $transformer = $transformers[$transformerIndex];
                $captureName = str_repeat($transformerIndex, $length);
                return "(?P<$captureName>" . $transformer->getReverseMatchingRegExp($length) . ')';
            }
        }, $escapedPattern);

        return $reverseMatchingRegExp;
    }

    public function parse(\DateTime $dateTime, $value)
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

            return $this->calculateUnixTimestamp($dateTime, $options);
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

    private function calculateUnixTimestamp($dateTime, array $options)
    {
        $datetime = $this->extractDateTime($options);

        $year     = $datetime['year'];
        $month    = $datetime['month'];
        $day      = $datetime['day'];
        $hour     = $datetime['hour'];
        $minute   = $datetime['minute'];
        $second   = $datetime['second'];
        $marker   = $datetime['marker'];
        $hourInstance = $datetime['hourInstance'];
        $timezone = $datetime['timezone'];

        // If month is false, return immediately
        if (false === $month) {
            return false;
        }

        // Normalize hour for mktime
        if ($hourInstance instanceof HourTransformer) {
            $hour = $hourInstance->getMktimeHour($hour, $marker);
        }

        // Set the timezone if different from the default one
        if (null !== $timezone && $timezone !== $this->timezone) {
            $dateTime->setTimezone(new \DateTimeZone($timezone));
        }

        $dateTime->setDate($year, $month, $day);
        $dateTime->setTime($hour, $minute, $second);

        return $dateTime->getTimestamp();
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
            'hourInstance' => isset($datetime['hourInstance']) ? $datetime['hourInstance'] : null,
            'timezone' => isset($datetime['timezone']) ? $datetime['timezone'] : null,
        );
    }
}
