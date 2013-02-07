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
 * Transforms between a date string and a DateTime object
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class DateTimeToStringTransformer extends BaseDateTimeTransformer
{
    /**
     * Format used for generating strings
     * @var string
     */
    private $generateFormat;

    /**
     * Format used for parsing strings
     *
     * Different than the {@link $generateFormat} because formats for parsing
     * support additional characters in PHP that are not supported for
     * generating strings.
     *
     * @var string
     */
    private $parseFormat;

    /**
     * Whether to parse by appending a pipe "|" to the parse format.
     *
     * This only works as of PHP 5.3.7.
     *
     * @var Boolean
     */
    private $parseUsingPipe;

    /**
     * Transforms a \DateTime instance to a string
     *
     * @see \DateTime::format() for supported formats
     *
     * @param string  $inputTimezone  The name of the input timezone
     * @param string  $outputTimezone The name of the output timezone
     * @param string  $format         The date format
     * @param Boolean $parseUsingPipe Whether to parse by appending a pipe "|" to the parse format
     *
     * @throws UnexpectedTypeException if a timezone is not a string
     */
    public function __construct($inputTimezone = null, $outputTimezone = null, $format = 'Y-m-d H:i:s', $parseUsingPipe = null)
    {
        parent::__construct($inputTimezone, $outputTimezone);

        $this->generateFormat = $this->parseFormat = $format;

        // The pipe in the parser pattern only works as of PHP 5.3.7
        // See http://bugs.php.net/54316
        $this->parseUsingPipe = null === $parseUsingPipe
            ? version_compare(phpversion(), '5.3.7', '>=')
            : $parseUsingPipe;

        // See http://php.net/manual/en/datetime.createfromformat.php
        // The character "|" in the format makes sure that the parts of a date
        // that are *not* specified in the format are reset to the corresponding
        // values from 1970-01-01 00:00:00 instead of the current time.
        // Without "|" and "Y-m-d", "2010-02-03" becomes "2010-02-03 12:32:47",
        // where the time corresponds to the current server time.
        // With "|" and "Y-m-d", "2010-02-03" becomes "2010-02-03 00:00:00",
        // which is at least deterministic and thus used here.
        if ($this->parseUsingPipe && false === strpos($this->parseFormat, '|')) {
            $this->parseFormat .= '|';
        }
    }

    /**
     * Transforms a DateTime object into a date string with the configured format
     * and timezone
     *
     * @param \DateTime $value A DateTime object
     *
     * @return string A value as produced by PHP's date() function
     *
     * @throws UnexpectedTypeException if the given value is not a \DateTime instance
     * @throws TransformationFailedException if the output timezone is not supported
     */
    public function transform($value)
    {
        if (null === $value) {
            return '';
        }

        if (!$value instanceof \DateTime) {
            throw new UnexpectedTypeException($value, '\DateTime');
        }

        $value = clone $value;
        try {
            $value->setTimezone(new \DateTimeZone($this->outputTimezone));
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        return $value->format($this->generateFormat);
    }

    /**
     * Transforms a date string in the configured timezone into a DateTime object.
     *
     * @param string $value A value as produced by PHP's date() function
     *
     * @return \DateTime An instance of \DateTime
     *
     * @throws UnexpectedTypeException if the given value is not a string
     * @throws TransformationFailedException if the date could not be parsed
     * @throws TransformationFailedException if the input timezone is not supported
     */
    public function reverseTransform($value)
    {
        if (empty($value)) {
            return null;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        try {
            $outputTz = new \DateTimeZone($this->outputTimezone);
            $dateTime = \DateTime::createFromFormat($this->parseFormat, $value, $outputTz);

            $lastErrors = \DateTime::getLastErrors();

            if (0 < $lastErrors['warning_count'] || 0 < $lastErrors['error_count']) {
                throw new TransformationFailedException(
                    implode(', ', array_merge(
                        array_values($lastErrors['warnings']),
                        array_values($lastErrors['errors'])
                    ))
                );
            }

            // On PHP versions < 5.3.7 we need to emulate the pipe operator
            // and reset parts not given in the format to their equivalent
            // of the UNIX base timestamp.
            if (!$this->parseUsingPipe) {
                list($year, $month, $day, $hour, $minute, $second) = explode('-', $dateTime->format('Y-m-d-H-i-s'));

                // Check which of the date parts are present in the pattern
                preg_match(
                    '/(' .
                    '(?<day>[djDl])|' .
                    '(?<month>[FMmn])|' .
                    '(?<year>[Yy])|' .
                    '(?<hour>[ghGH])|' .
                    '(?<minute>i)|' .
                    '(?<second>s)|' .
                    '(?<dayofyear>z)|' .
                    '(?<timestamp>U)|' .
                    '[^djDlFMmnYyghGHiszU]' .
                    ')*/',
                    $this->parseFormat,
                    $matches
                );

                // preg_match() does not guarantee to set all indices, so
                // set them unless given
                $matches = array_merge(array(
                    'day' => false,
                    'month' => false,
                    'year' => false,
                    'hour' => false,
                    'minute' => false,
                    'second' => false,
                    'dayofyear' => false,
                    'timestamp' => false,
                ), $matches);

                // Reset all parts that don't exist in the format to the
                // corresponding part of the UNIX base timestamp
                if (!$matches['timestamp']) {
                    if (!$matches['dayofyear']) {
                        if (!$matches['day']) {
                            $day = 1;
                        }
                        if (!$matches['month']) {
                            $month = 1;
                        }
                    }
                    if (!$matches['year']) {
                        $year = 1970;
                    }
                    if (!$matches['hour']) {
                        $hour = 0;
                    }
                    if (!$matches['minute']) {
                        $minute = 0;
                    }
                    if (!$matches['second']) {
                        $second = 0;
                    }
                    $dateTime->setDate($year, $month, $day);
                    $dateTime->setTime($hour, $minute, $second);
                }
            }

            if ($this->inputTimezone !== $this->outputTimezone) {
                $dateTime->setTimeZone(new \DateTimeZone($this->inputTimezone));
            }
        } catch (TransformationFailedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        return $dateTime;
    }
}
