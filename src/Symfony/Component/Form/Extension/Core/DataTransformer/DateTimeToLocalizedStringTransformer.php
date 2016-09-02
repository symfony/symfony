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

use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Transforms between a normalized time and a localized time string.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class DateTimeToLocalizedStringTransformer extends BaseDateTimeTransformer
{
    private $dateFormat;
    private $timeFormat;
    private $pattern;
    private $calendar;

    /**
     * Constructor.
     *
     * @see BaseDateTimeTransformer::formats for available format options
     *
     * @param string $inputTimezone  The name of the input timezone
     * @param string $outputTimezone The name of the output timezone
     * @param int    $dateFormat     The date format
     * @param int    $timeFormat     The time format
     * @param int    $calendar       One of the \IntlDateFormatter calendar constants
     * @param string $pattern        A pattern to pass to \IntlDateFormatter
     *
     * @throws UnexpectedTypeException If a format is not supported or if a timezone is not a string
     */
    public function __construct($inputTimezone = null, $outputTimezone = null, $dateFormat = null, $timeFormat = null, $calendar = \IntlDateFormatter::GREGORIAN, $pattern = null)
    {
        parent::__construct($inputTimezone, $outputTimezone);

        if (null === $dateFormat) {
            $dateFormat = \IntlDateFormatter::MEDIUM;
        }

        if (null === $timeFormat) {
            $timeFormat = \IntlDateFormatter::SHORT;
        }

        if (!in_array($dateFormat, self::$formats, true)) {
            throw new UnexpectedTypeException($dateFormat, implode('", "', self::$formats));
        }

        if (!in_array($timeFormat, self::$formats, true)) {
            throw new UnexpectedTypeException($timeFormat, implode('", "', self::$formats));
        }

        $this->dateFormat = $dateFormat;
        $this->timeFormat = $timeFormat;
        $this->calendar = $calendar;
        $this->pattern = $pattern;
    }

    /**
     * Transforms a normalized date into a localized date string/array.
     *
     * @param \DateTime|\DateTimeInterface $dateTime A DateTime object
     *
     * @return string|array Localized date string/array
     *
     * @throws TransformationFailedException If the given value is not an instance
     *                                       of \DateTime or \DateTimeInterface or
     *                                       if the date could not be transformed.
     */
    public function transform($dateTime)
    {
        if (null === $dateTime) {
            return '';
        }

        if (!$dateTime instanceof \DateTime && !$dateTime instanceof \DateTimeInterface) {
            throw new TransformationFailedException('Expected a \DateTime or \DateTimeInterface.');
        }

        $value = $this->getIntlDateFormatter()->format($dateTime->getTimestamp());

        if (intl_get_error_code() != 0) {
            throw new TransformationFailedException(intl_get_error_message());
        }

        return $value;
    }

    /**
     * Transforms a localized date string/array into a normalized date.
     *
     * @param string|array $value Localized date string/array
     *
     * @return \DateTime Normalized date
     *
     * @throws TransformationFailedException if the given value is not a string,
     *                                       if the date could not be parsed
     */
    public function reverseTransform($value)
    {
        if (!is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if ('' === $value) {
            return;
        }

        // date-only patterns require parsing to be done in UTC, as midnight might not exist in the local timezone due
        // to DST changes
        $dateOnly = $this->isPatternDateOnly();

        $timestamp = $this->getIntlDateFormatter($dateOnly)->parse($value);

        if (intl_get_error_code() != 0) {
            throw new TransformationFailedException(intl_get_error_message());
        }

        try {
            if ($dateOnly) {
                // we only care about year-month-date, which has been delivered as a timestamp pointing to UTC midnight
                return new \DateTime(gmdate('Y-m-d', $timestamp), new \DateTimeZone($this->inputTimezone));
            }

            // read timestamp into DateTime object - the formatter delivers a timestamp
            $dateTime = new \DateTime(sprintf('@%s', $timestamp), new \DateTimeZone($this->outputTimezone));
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
     * @param bool $ignoreTimezone Use UTC regardless of the configured timezone.
     *
     * @return \IntlDateFormatter
     *
     * @throws TransformationFailedException in case the date formatter can not be constructed.
     */
    protected function getIntlDateFormatter($ignoreTimezone = false)
    {
        $dateFormat = $this->dateFormat;
        $timeFormat = $this->timeFormat;
        $timezone = $ignoreTimezone ? 'UTC' : $this->outputTimezone;
        $calendar = $this->calendar;
        $pattern = $this->pattern;

        $intlDateFormatter = new \IntlDateFormatter(\Locale::getDefault(), $dateFormat, $timeFormat, $timezone, $calendar, $pattern);

        // new \intlDateFormatter may return null instead of false in case of failure, see https://bugs.php.net/bug.php?id=66323
        if (!$intlDateFormatter) {
            throw new TransformationFailedException(intl_get_error_message(), intl_get_error_code());
        }

        $intlDateFormatter->setLenient(false);

        return $intlDateFormatter;
    }

    /**
     * Checks if the pattern contains only a date.
     *
     * @return bool
     */
    protected function isPatternDateOnly()
    {
        if (null === $this->pattern) {
            return false;
        }

        // strip escaped text
        $pattern = preg_replace("#'(.*?)'#", '', $this->pattern);

        // check for the absence of time-related placeholders
        return 0 === preg_match('#[ahHkKmsSAzZOvVxX]#', $pattern);
    }
}
