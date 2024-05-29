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
 *
 * @extends BaseDateTimeTransformer<string>
 */
class DateTimeToLocalizedStringTransformer extends BaseDateTimeTransformer
{
    private int $dateFormat;
    private int $timeFormat;

    /**
     * @see BaseDateTimeTransformer::formats for available format options
     *
     * @param string|null $inputTimezone  The name of the input timezone
     * @param string|null $outputTimezone The name of the output timezone
     * @param int|null    $dateFormat     The date format
     * @param int|null    $timeFormat     The time format
     * @param int         $calendar       One of the \IntlDateFormatter calendar constants
     * @param string|null $pattern        A pattern to pass to \IntlDateFormatter
     *
     * @throws UnexpectedTypeException If a format is not supported or if a timezone is not a string
     */
    public function __construct(
        ?string $inputTimezone = null,
        ?string $outputTimezone = null,
        ?int $dateFormat = null,
        ?int $timeFormat = null,
        private int $calendar = \IntlDateFormatter::GREGORIAN,
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

        $this->dateFormat = $dateFormat;
        $this->timeFormat = $timeFormat;
    }

    /**
     * Transforms a normalized date into a localized date string/array.
     *
     * @param \DateTimeInterface $dateTime A DateTimeInterface object
     *
     * @throws TransformationFailedException if the given value is not a \DateTimeInterface
     *                                       or if the date could not be transformed
     */
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

        return $value;
    }

    /**
     * Transforms a localized date string/array into a normalized date.
     *
     * @param string $value Localized date string
     *
     * @throws TransformationFailedException if the given value is not a string,
     *                                       if the date could not be parsed
     */
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

        try {
            $timestamp = @$dateFormatter->parse($value);
        } catch (\IntlException $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        if (0 != intl_get_error_code()) {
            throw new TransformationFailedException(intl_get_error_message(), intl_get_error_code());
        } elseif ($timestamp > 253402214400) {
            // This timestamp represents UTC midnight of 9999-12-31 to prevent 5+ digit years
            throw new TransformationFailedException('Years beyond 9999 are not supported.');
        } elseif (false === $timestamp) {
            // the value couldn't be parsed but the Intl extension didn't report an error code, this
            // could be the case when the Intl polyfill is used which always returns 0 as the error code
            throw new TransformationFailedException(sprintf('"%s" could not be parsed as a date.', $value));
        }

        try {
            if ($dateOnly) {
                // we only care about year-month-date, which has been delivered as a timestamp pointing to UTC midnight
                $dateTime = new \DateTime(gmdate('Y-m-d', $timestamp), new \DateTimeZone($this->outputTimezone));
            } else {
                // read timestamp into DateTime object - the formatter delivers a timestamp
                $dateTime = new \DateTime(sprintf('@%s', $timestamp));
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
     *
     * @throws TransformationFailedException in case the date formatter cannot be constructed
     */
    protected function getIntlDateFormatter(bool $ignoreTimezone = false): \IntlDateFormatter
    {
        $dateFormat = $this->dateFormat;
        $timeFormat = $this->timeFormat;
        $timezone = new \DateTimeZone($ignoreTimezone ? 'UTC' : $this->outputTimezone);

        $calendar = $this->calendar;
        $pattern = $this->pattern;

        $intlDateFormatter = new \IntlDateFormatter(\Locale::getDefault(), $dateFormat, $timeFormat, $timezone, $calendar, $pattern ?? '');

        // new \intlDateFormatter may return null instead of false in case of failure, see https://bugs.php.net/66323
        if (!$intlDateFormatter) {
            throw new TransformationFailedException(intl_get_error_message(), intl_get_error_code());
        }

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
}
