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
 * Transforms between a date string and a DateTime object.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class DateTimeToStringTransformer extends BaseDateTimeTransformer
{
    /**
     * Format used for generating strings.
     *
     * @var string
     */
    private $generateFormat;

    /**
     * Format used for parsing strings.
     *
     * Different than the {@link $generateFormat} because formats for parsing
     * support additional characters in PHP that are not supported for
     * generating strings.
     *
     * @var string
     */
    private $parseFormat;

    /**
     * Transforms a \DateTime instance to a string.
     *
     * @see \DateTime::format() for supported formats
     *
     * @param string $inputTimezone  The name of the input timezone
     * @param string $outputTimezone The name of the output timezone
     * @param string $format         The date format
     *
     * @throws UnexpectedTypeException if a timezone is not a string
     */
    public function __construct($inputTimezone = null, $outputTimezone = null, $format = 'Y-m-d H:i:s')
    {
        parent::__construct($inputTimezone, $outputTimezone);

        $this->generateFormat = $this->parseFormat = $format;

        // See http://php.net/manual/en/datetime.createfromformat.php
        // The character "|" in the format makes sure that the parts of a date
        // that are *not* specified in the format are reset to the corresponding
        // values from 1970-01-01 00:00:00 instead of the current time.
        // Without "|" and "Y-m-d", "2010-02-03" becomes "2010-02-03 12:32:47",
        // where the time corresponds to the current server time.
        // With "|" and "Y-m-d", "2010-02-03" becomes "2010-02-03 00:00:00",
        // which is at least deterministic and thus used here.
        if (false === strpos($this->parseFormat, '|')) {
            $this->parseFormat .= '|';
        }
    }

    /**
     * Transforms a DateTime object into a date string with the configured format
     * and timezone.
     *
     * @param \DateTimeInterface $dateTime A DateTimeInterface object
     *
     * @return string A value as produced by PHP's date() function
     *
     * @throws TransformationFailedException If the given value is not a \DateTimeInterface
     */
    public function transform($dateTime)
    {
        if (null === $dateTime) {
            return '';
        }

        if (!$dateTime instanceof \DateTimeInterface) {
            throw new TransformationFailedException('Expected a \DateTimeInterface.');
        }

        if (!$dateTime instanceof \DateTimeImmutable) {
            $dateTime = clone $dateTime;
        }

        $dateTime = $dateTime->setTimezone(new \DateTimeZone($this->outputTimezone));

        return $dateTime->format($this->generateFormat);
    }

    /**
     * Transforms a date string in the configured timezone into a DateTime object.
     *
     * @param string $value A value as produced by PHP's date() function
     *
     * @return \DateTime An instance of \DateTime
     *
     * @throws TransformationFailedException If the given value is not a string,
     *                                       or could not be transformed
     */
    public function reverseTransform($value)
    {
        if (empty($value)) {
            return;
        }

        if (!\is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        $outputTz = new \DateTimeZone($this->outputTimezone);
        $dateTime = \DateTime::createFromFormat($this->parseFormat, $value, $outputTz);

        $lastErrors = \DateTime::getLastErrors();

        if (0 < $lastErrors['warning_count'] || 0 < $lastErrors['error_count']) {
            throw new TransformationFailedException(implode(', ', array_merge(array_values($lastErrors['warnings']), array_values($lastErrors['errors']))));
        }

        try {
            if ($this->inputTimezone !== $this->outputTimezone) {
                $dateTime->setTimezone(new \DateTimeZone($this->inputTimezone));
            }
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        return $dateTime;
    }
}
