<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\DateFormatter\DateFormat;

use Symfony\Component\Intl\Exception\NotImplementedException;
use Symfony\Component\Intl\Globals\IntlGlobals;

/**
 * Parser and formatter for date formats.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 *
 * @internal
 */
class FullTransformer
{
    private $quoteMatch = "'(?:[^']+|'')*'";
    private $implementedChars = 'MLydQqhDEaHkKmsz';
    private $notImplementedChars = 'GYuwWFgecSAZvVW';
    private $regExp;

    /**
     * @var Transformer[]
     */
    private $transformers;

    private $pattern;
    private $timezone;

    /**
     * Constructor.
     *
     * @param string $pattern  The pattern to be used to format and/or parse values
     * @param string $timezone The timezone to perform the date/time calculations
     */
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

    /**
     * Return the array of Transformer objects.
     *
     * @return Transformer[] Associative array of Transformer objects (format char => Transformer)
     */
    public function getTransformers()
    {
        return $this->transformers;
    }

    /**
     * Format a DateTime using ICU dateformat pattern.
     *
     * @param \DateTime $dateTime A DateTime object to be used to generate the formatted value
     *
     * @return string The formatted value
     */
    public function format(\DateTime $dateTime)
    {
        $that = $this;

        $formatted = preg_replace_callback($this->regExp, function ($matches) use ($that, $dateTime) {
            return $that->formatReplace($matches[0], $dateTime);
        }, $this->pattern);

        return $formatted;
    }

    /**
     * Return the formatted ICU value for the matched date characters.
     *
     * @param string    $dateChars The date characters to be replaced with a formatted ICU value
     * @param \DateTime $dateTime  A DateTime object to be used to generate the formatted value
     *
     * @return string The formatted value
     *
     * @throws NotImplementedException When it encounters a not implemented date character
     */
    public function formatReplace($dateChars, $dateTime)
    {
        $length = strlen($dateChars);

        if ($this->isQuoteMatch($dateChars)) {
            return $this->replaceQuoteMatch($dateChars);
        }

        if (isset($this->transformers[$dateChars[0]])) {
            $transformer = $this->transformers[$dateChars[0]];

            return $transformer->format($dateTime, $length);
        }

        // handle unimplemented characters
        if (false !== strpos($this->notImplementedChars, $dateChars[0])) {
            throw new NotImplementedException(sprintf('Unimplemented date character "%s" in format "%s"', $dateChars[0], $this->pattern));
        }
    }

    /**
     * Parse a pattern based string to a timestamp value.
     *
     * @param \DateTime $dateTime A configured DateTime object to use to perform the date calculation
     * @param string    $value    String to convert to a time value
     *
     * @return int The corresponding Unix timestamp
     *
     * @throws \InvalidArgumentException When the value can not be matched with pattern
     */
    public function parse(\DateTime $dateTime, $value)
    {
        $reverseMatchingRegExp = $this->getReverseMatchingRegExp($this->pattern);
        $reverseMatchingRegExp = '/^'.$reverseMatchingRegExp.'$/';

        $options = array();

        if (preg_match($reverseMatchingRegExp, $value, $matches)) {
            $matches = $this->normalizeArray($matches);

            foreach ($this->transformers as $char => $transformer) {
                if (isset($matches[$char])) {
                    $length = strlen($matches[$char]['pattern']);
                    $options = array_merge($options, $transformer->extractDateOptions($matches[$char]['value'], $length));
                }
            }

            // reset error code and message
            IntlGlobals::setError(IntlGlobals::U_ZERO_ERROR);

            return $this->calculateUnixTimestamp($dateTime, $options);
        }

        // behave like the intl extension
        IntlGlobals::setError(IntlGlobals::U_PARSE_ERROR, 'Date parsing failed');

        return false;
    }

    /**
     * Retrieve a regular expression to match with a formatted value.
     *
     * @param string $pattern The pattern to create the reverse matching regular expression
     *
     * @return string The reverse matching regular expression with named captures being formed by the
     *                transformer index in the $transformer array
     */
    public function getReverseMatchingRegExp($pattern)
    {
        $that = $this;

        $escapedPattern = preg_quote($pattern, '/');

        // ICU 4.8 recognizes slash ("/") in a value to be parsed as a dash ("-") and vice-versa
        // when parsing a date/time value
        $escapedPattern = preg_replace('/\\\[\-|\/]/', '[\/\-]', $escapedPattern);

        $reverseMatchingRegExp = preg_replace_callback($this->regExp, function ($matches) use ($that) {
            $length = strlen($matches[0]);
            $transformerIndex = $matches[0][0];

            $dateChars = $matches[0];
            if ($that->isQuoteMatch($dateChars)) {
                return $that->replaceQuoteMatch($dateChars);
            }

            $transformers = $that->getTransformers();
            if (isset($transformers[$transformerIndex])) {
                $transformer = $transformers[$transformerIndex];
                $captureName = str_repeat($transformerIndex, $length);

                return "(?P<$captureName>".$transformer->getReverseMatchingRegExp($length).')';
            }
        }, $escapedPattern);

        return $reverseMatchingRegExp;
    }

    /**
     * Check if the first char of a string is a single quote.
     *
     * @param string $quoteMatch The string to check
     *
     * @return bool true if matches, false otherwise
     */
    public function isQuoteMatch($quoteMatch)
    {
        return "'" === $quoteMatch[0];
    }

    /**
     * Replaces single quotes at the start or end of a string with two single quotes.
     *
     * @param string $quoteMatch The string to replace the quotes
     *
     * @return string A string with the single quotes replaced
     */
    public function replaceQuoteMatch($quoteMatch)
    {
        if (preg_match("/^'+$/", $quoteMatch)) {
            return str_replace("''", "'", $quoteMatch);
        }

        return str_replace("''", "'", substr($quoteMatch, 1, -1));
    }

    /**
     * Builds a chars match regular expression.
     *
     * @param string $specialChars A string of chars to build the regular expression
     *
     * @return string The chars match regular expression
     */
    protected function buildCharsMatch($specialChars)
    {
        $specialCharsArray = str_split($specialChars);

        $specialCharsMatch = implode('|', array_map(function ($char) {
            return $char.'+';
        }, $specialCharsArray));

        return $specialCharsMatch;
    }

    /**
     * Normalize a preg_replace match array, removing the numeric keys and returning an associative array
     * with the value and pattern values for the matched Transformer.
     *
     * @param array $data
     *
     * @return array
     */
    protected function normalizeArray(array $data)
    {
        $ret = array();

        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $ret[$key[0]] = array(
                'value' => $value,
                'pattern' => $key,
            );
        }

        return $ret;
    }

    /**
     * Calculates the Unix timestamp based on the matched values by the reverse matching regular
     * expression of parse().
     *
     * @param \DateTime $dateTime The DateTime object to be used to calculate the timestamp
     * @param array     $options  An array with the matched values to be used to calculate the timestamp
     *
     * @return bool|int The calculated timestamp or false if matched date is invalid
     */
    protected function calculateUnixTimestamp(\DateTime $dateTime, array $options)
    {
        $options = $this->getDefaultValueForOptions($options);

        $year = $options['year'];
        $month = $options['month'];
        $day = $options['day'];
        $hour = $options['hour'];
        $hourInstance = $options['hourInstance'];
        $minute = $options['minute'];
        $second = $options['second'];
        $marker = $options['marker'];
        $timezone = $options['timezone'];

        // If month is false, return immediately (intl behavior)
        if (false === $month) {
            IntlGlobals::setError(IntlGlobals::U_PARSE_ERROR, 'Date parsing failed');

            return false;
        }

        // Normalize hour
        if ($hourInstance instanceof HourTransformer) {
            $hour = $hourInstance->normalizeHour($hour, $marker);
        }

        // Set the timezone if different from the default one
        if (null !== $timezone && $timezone !== $this->timezone) {
            $dateTime->setTimezone(new \DateTimeZone($timezone));
        }

        // Normalize yy year
        preg_match_all($this->regExp, $this->pattern, $matches);
        if (in_array('yy', $matches[0])) {
            $dateTime->setTimestamp(time());
            $year = $year > $dateTime->format('y') + 20 ? 1900 + $year : 2000 + $year;
        }

        $dateTime->setDate($year, $month, $day);
        $dateTime->setTime($hour, $minute, $second);

        return $dateTime->getTimestamp();
    }

    /**
     * Add sensible default values for missing items in the extracted date/time options array. The values
     * are base in the beginning of the Unix era.
     *
     * @param array $options
     *
     * @return array
     */
    private function getDefaultValueForOptions(array $options)
    {
        return array(
            'year' => isset($options['year']) ? $options['year'] : 1970,
            'month' => isset($options['month']) ? $options['month'] : 1,
            'day' => isset($options['day']) ? $options['day'] : 1,
            'hour' => isset($options['hour']) ? $options['hour'] : 0,
            'hourInstance' => isset($options['hourInstance']) ? $options['hourInstance'] : null,
            'minute' => isset($options['minute']) ? $options['minute'] : 0,
            'second' => isset($options['second']) ? $options['second'] : 0,
            'marker' => isset($options['marker']) ? $options['marker'] : null,
            'timezone' => isset($options['timezone']) ? $options['timezone'] : null,
        );
    }
}
