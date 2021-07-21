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
 *
 * @deprecated since Symfony 5.3, use symfony/polyfill-intl-icu ^1.21 instead
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
     * @param string $pattern  The pattern to be used to format and/or parse values
     * @param string $timezone The timezone to perform the date/time calculations
     */
    public function __construct(string $pattern, string $timezone)
    {
        $this->pattern = $pattern;
        $this->timezone = $timezone;

        $implementedCharsMatch = $this->buildCharsMatch($this->implementedChars);
        $notImplementedCharsMatch = $this->buildCharsMatch($this->notImplementedChars);
        $this->regExp = "/($this->quoteMatch|$implementedCharsMatch|$notImplementedCharsMatch)/";

        $this->transformers = [
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
            'z' => new TimezoneTransformer(),
        ];
    }

    /**
     * Format a DateTime using ICU dateformat pattern.
     *
     * @return string The formatted value
     */
    public function format(\DateTime $dateTime): string
    {
        $formatted = preg_replace_callback($this->regExp, function ($matches) use ($dateTime) {
            return $this->formatReplace($matches[0], $dateTime);
        }, $this->pattern);

        return $formatted;
    }

    /**
     * Return the formatted ICU value for the matched date characters.
     *
     * @throws NotImplementedException When it encounters a not implemented date character
     */
    private function formatReplace(string $dateChars, \DateTime $dateTime): string
    {
        $length = \strlen($dateChars);

        if ($this->isQuoteMatch($dateChars)) {
            return $this->replaceQuoteMatch($dateChars);
        }

        if (isset($this->transformers[$dateChars[0]])) {
            $transformer = $this->transformers[$dateChars[0]];

            return $transformer->format($dateTime, $length);
        }

        // handle unimplemented characters
        if (str_contains($this->notImplementedChars, $dateChars[0])) {
            throw new NotImplementedException(sprintf('Unimplemented date character "%s" in format "%s".', $dateChars[0], $this->pattern));
        }

        return '';
    }

    /**
     * Parse a pattern based string to a timestamp value.
     *
     * @param \DateTime $dateTime A configured DateTime object to use to perform the date calculation
     * @param string    $value    String to convert to a time value
     *
     * @return int|false The corresponding Unix timestamp
     *
     * @throws \InvalidArgumentException When the value can not be matched with pattern
     */
    public function parse(\DateTime $dateTime, string $value)
    {
        $reverseMatchingRegExp = $this->getReverseMatchingRegExp($this->pattern);
        $reverseMatchingRegExp = '/^'.$reverseMatchingRegExp.'$/';

        $options = [];

        if (preg_match($reverseMatchingRegExp, $value, $matches)) {
            $matches = $this->normalizeArray($matches);

            foreach ($this->transformers as $char => $transformer) {
                if (isset($matches[$char])) {
                    $length = \strlen($matches[$char]['pattern']);
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
     * @return string The reverse matching regular expression with named captures being formed by the
     *                transformer index in the $transformer array
     */
    private function getReverseMatchingRegExp(string $pattern): string
    {
        $escapedPattern = preg_quote($pattern, '/');

        // ICU 4.8 recognizes slash ("/") in a value to be parsed as a dash ("-") and vice-versa
        // when parsing a date/time value
        $escapedPattern = preg_replace('/\\\[\-|\/]/', '[\/\-]', $escapedPattern);

        $reverseMatchingRegExp = preg_replace_callback($this->regExp, function ($matches) {
            $length = \strlen($matches[0]);
            $transformerIndex = $matches[0][0];

            $dateChars = $matches[0];
            if ($this->isQuoteMatch($dateChars)) {
                return $this->replaceQuoteMatch($dateChars);
            }

            if (isset($this->transformers[$transformerIndex])) {
                $transformer = $this->transformers[$transformerIndex];
                $captureName = str_repeat($transformerIndex, $length);

                return "(?P<$captureName>".$transformer->getReverseMatchingRegExp($length).')';
            }

            return null;
        }, $escapedPattern);

        return $reverseMatchingRegExp;
    }

    /**
     * Check if the first char of a string is a single quote.
     */
    private function isQuoteMatch(string $quoteMatch): bool
    {
        return "'" === $quoteMatch[0];
    }

    /**
     * Replaces single quotes at the start or end of a string with two single quotes.
     */
    private function replaceQuoteMatch(string $quoteMatch): string
    {
        if (preg_match("/^'+$/", $quoteMatch)) {
            return str_replace("''", "'", $quoteMatch);
        }

        return str_replace("''", "'", substr($quoteMatch, 1, -1));
    }

    /**
     * Builds a chars match regular expression.
     */
    private function buildCharsMatch(string $specialChars): string
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
     */
    private function normalizeArray(array $data): array
    {
        $ret = [];

        foreach ($data as $key => $value) {
            if (!\is_string($key)) {
                continue;
            }

            $ret[$key[0]] = [
                'value' => $value,
                'pattern' => $key,
            ];
        }

        return $ret;
    }

    /**
     * Calculates the Unix timestamp based on the matched values by the reverse matching regular
     * expression of parse().
     *
     * @return bool|int The calculated timestamp or false if matched date is invalid
     */
    private function calculateUnixTimestamp(\DateTime $dateTime, array $options)
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
        if (\in_array('yy', $matches[0])) {
            $dateTime->setTimestamp(time());
            $year = $year > (int) $dateTime->format('y') + 20 ? 1900 + $year : 2000 + $year;
        }

        $dateTime->setDate($year, $month, $day);
        $dateTime->setTime($hour, $minute, $second);

        return $dateTime->getTimestamp();
    }

    /**
     * Add sensible default values for missing items in the extracted date/time options array. The values
     * are base in the beginning of the Unix era.
     */
    private function getDefaultValueForOptions(array $options): array
    {
        return [
            'year' => $options['year'] ?? 1970,
            'month' => $options['month'] ?? 1,
            'day' => $options['day'] ?? 1,
            'hour' => $options['hour'] ?? 0,
            'hourInstance' => $options['hourInstance'] ?? null,
            'minute' => $options['minute'] ?? 0,
            'second' => $options['second'] ?? 0,
            'marker' => $options['marker'] ?? null,
            'timezone' => $options['timezone'] ?? null,
        ];
    }
}
