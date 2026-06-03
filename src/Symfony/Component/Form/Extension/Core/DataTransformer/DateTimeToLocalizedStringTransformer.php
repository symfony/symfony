<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Transforms between a normalized time and a localized time string.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @extends BaseDateTimeTransformer<string>
 */
class DateTimeToLocalizedStringTransformer extends BaseDateTimeTransformer
{
    /**
     * Unicode whitespace characters used by ICU in formatted date strings.
     *
     * @see https://unicode-org.atlassian.net/browse/CLDR-14032
     */
    private const NO_BREAK_SPACE = "\u{00A0}";
    private const NARROW_NO_BREAK_SPACE = "\u{202F}"; // Used by ICU 72+ before AM/PM
    private const THIN_SPACE = "\u{2009}";

    private int $dateFormat;
    private int $timeFormat;

    /**
     * @see BaseDateTimeTransformer::formats for available format options
     *
     * @param string|null       $inputTimezone  The name of the input timezone
     * @param string|null       $outputTimezone The name of the output timezone
     * @param int|null          $dateFormat     The date format
     * @param int|null          $timeFormat     The time format
     * @param int|\IntlCalendar $calendar       One of the \IntlDateFormatter calendar constants or an \IntlCalendar instance
     * @param string|null       $pattern        A pattern to pass to \IntlDateFormatter
     *
     * @throws UnexpectedTypeException If a format is not supported or if a timezone is not a string
     */
    public function __construct(
        ?string $inputTimezone = null,
        ?string $outputTimezone = null,
        ?int $dateFormat = null,
        ?int $timeFormat = null,
        private int|\IntlCalendar $calendar = \IntlDateFormatter::GREGORIAN,
        private ?string $pattern = null,
    ) {
        parent::__construct($inputTimezone, $outputTimezone);

        $dateFormat ??= \IntlDateFormatter::MEDIUM;
        $timeFormat ??= \IntlDateFormatter::SHORT;

        if (!\in_array($dateFormat, self::$formats, true)) {
            throw new UnexpectedTypeException($dateFormat, implode('", "', self::$formats));
        }

        if (!\in_array($timeFormat, self::$formats, true)) {
            throw new UnexpectedTypeException($timeFormat, implode('", "', self::$formats));
        }

        if (\is_int($calendar) && !\in_array($calendar, [\IntlDateFormatter::GREGORIAN, \IntlDateFormatter::TRADITIONAL], true)) {
            throw new InvalidArgumentException('The "calendar" option should be either an \IntlDateFormatter constant or an \IntlCalendar instance.');
        }

        $this->dateFormat = $dateFormat;
        $this->timeFormat = $timeFormat;
    }

    public function transform(mixed $dateTime): string
    {
        if (null === $dateTime) {
            return '';
        }

        if (!$dateTime instanceof \DateTimeInterface) {
            throw new TransformationFailedException('Expected a \DateTimeInterface.');
        }

        $value = $this->getIntlDateFormatter()->format($dateTime->getTimestamp());

        if (0 != intl_get_error_code()) {
            throw new TransformationFailedException(intl_get_error_message());
        }

        return self::normalizeWhitespace($value);
    }

    public function reverseTransform(mixed $value): ?\DateTime
    {
        if (!\is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if ('' === $value) {
            return null;
        }

        // date-only patterns require parsing to be done in UTC, as midnight might not exist in the local timezone due
        // to DST changes
        $dateOnly = $this->isPatternDateOnly();
        $dateFormatter = $this->getIntlDateFormatter($dateOnly);

        $timestamp = $this->parse($dateFormatter, $value);

        if (0 != intl_get_error_code()) {
            throw new TransformationFailedException(intl_get_error_message(), intl_get_error_code());
        } elseif ($timestamp > 253402214400) {
            // This timestamp represents UTC midnight of 9999-12-31 to prevent 5+ digit years
            throw new TransformationFailedException('Years beyond 9999 are not supported.');
        } elseif (false === $timestamp) {
            // the value couldn't be parsed but the Intl extension didn't report an error code, this
            // could be the case when the Intl polyfill is used which always returns 0 as the error code
            throw new TransformationFailedException(\sprintf('"%s" could not be parsed as a date.', $value));
        }

        try {
            if ($dateOnly) {
                // we only care about year-month-date, which has been delivered as a timestamp pointing to UTC midnight
                $dateTime = new \DateTime(gmdate('Y-m-d', $timestamp), new \DateTimeZone($this->outputTimezone));
            } else {
                // read timestamp into DateTime object - the formatter delivers a timestamp
                $dateTime = new \DateTime(\sprintf('@%s', $timestamp));
            }
            // set timezone separately, as it would be ignored if set via the constructor,
            // see https://php.net/datetime.construct
            $dateTime->setTimezone(new \DateTimeZone($this->outputTimezone));
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        if ($this->outputTimezone !== $this->inputTimezone) {
            $dateTime->setTimezone(new \DateTimeZone($this->inputTimezone));
        }

        return $dateTime;
    }

    /**
     * Returns a preconfigured IntlDateFormatter instance.
     *
     * @param bool $ignoreTimezone Use UTC regardless of the configured timezone
     */
    protected function getIntlDateFormatter(bool $ignoreTimezone = false): \IntlDateFormatter
    {
        $dateFormat = $this->dateFormat;
        $timeFormat = $this->timeFormat;
        $timezone = new \DateTimeZone($ignoreTimezone ? 'UTC' : $this->outputTimezone);

        $calendar = $this->calendar;
        $pattern = $this->pattern;

        $intlDateFormatter = new \IntlDateFormatter(\Locale::getDefault(), $dateFormat, $timeFormat, $timezone, $calendar, $pattern ?? '');
        $intlDateFormatter->setLenient(false);

        return $intlDateFormatter;
    }

    /**
     * Checks if the pattern contains only a date.
     */
    protected function isPatternDateOnly(): bool
    {
        if (null === $this->pattern) {
            return false;
        }

        // strip escaped text
        $pattern = preg_replace("#'(.*?)'#", '', $this->pattern);

        // check for the absence of time-related placeholders
        return 0 === preg_match('#[ahHkKmsSAzZOvVxX]#', $pattern);
    }

    /**
     * Normalizes various Unicode whitespace characters to regular ASCII spaces.
     *
     * ICU 72+ uses special Unicode whitespace characters (such as narrow no-break space U+202F)
     * in formatted date strings. This method ensures consistent handling regardless of ICU version
     * by normalizing these characters to regular ASCII spaces (U+0020).
     */
    private static function normalizeWhitespace(string $string): string
    {
        return str_replace([self::NO_BREAK_SPACE, self::NARROW_NO_BREAK_SPACE, self::THIN_SPACE], ' ', $string);
    }

    /**
     * Parses a localized date string, handling ICU version differences in whitespace.
     *
     * ICU 72+ uses special Unicode whitespace characters (such as narrow no-break space U+202F)
     * that users typically don't type. This method first tries parsing the input as-is, then
     * tries with whitespace normalization to ensure compatibility across ICU versions.
     *
     * @throws TransformationFailedException When the input cannot be parsed
     */
    private function parse(\IntlDateFormatter $dateFormatter, string $value): int|float|false
    {
        try {
            $timestamp = @$dateFormatter->parse($value);
        } catch (\IntlException $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        // If parsing failed and the value contains regular spaces, try with ICU 72+ whitespace
        if ((false === $timestamp || 0 !== intl_get_error_code()) && str_contains($value, ' ')) {
            $icuValue = str_replace(' ', self::NARROW_NO_BREAK_SPACE, $value);

            try {
                $timestamp = @$dateFormatter->parse($icuValue);
            } catch (\IntlException) {
                // Ignore, we'll use the original error below
            }
        }

        return $timestamp;
    }
}
