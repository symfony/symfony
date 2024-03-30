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

/**
 * Transforms between a date string and a DateTime object.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @extends BaseDateTimeTransformer<string>
 */
class DateTimeToStringTransformer extends BaseDateTimeTransformer
{
    /**
     * Format used for generating strings.
     */
    private string $generateFormat;

    /**
     * Format used for parsing strings.
     *
     * Different than the {@link $generateFormat} because formats for parsing
     * support additional characters in PHP that are not supported for
     * generating strings.
     */
    private string $parseFormat;

    /**
     * Transforms a \DateTime instance to a string.
     *
     * @see \DateTime::format() for supported formats
     *
     * @param string|null $inputTimezone  The name of the input timezone
     * @param string|null $outputTimezone The name of the output timezone
     * @param string      $format         The date format
     * @param string|null $parseFormat    The parse format when different from $format
     */
    public function __construct(?string $inputTimezone = null, ?string $outputTimezone = null, string $format = 'Y-m-d H:i:s', ?string $parseFormat = null)
    {
        parent::__construct($inputTimezone, $outputTimezone);

        $this->generateFormat = $format;
        $this->parseFormat = $parseFormat ?? $format;

        // See https://php.net/datetime.createfromformat
        // The character "|" in the format makes sure that the parts of a date
        // that are *not* specified in the format are reset to the corresponding
        // values from 1970-01-01 00:00:00 instead of the current time.
        // Without "|" and "Y-m-d", "2010-02-03" becomes "2010-02-03 12:32:47",
        // where the time corresponds to the current server time.
        // With "|" and "Y-m-d", "2010-02-03" becomes "2010-02-03 00:00:00",
        // which is at least deterministic and thus used here.
        if (!str_contains($this->parseFormat, '|')) {
            $this->parseFormat .= '|';
        }
    }

    /**
     * Transforms a DateTime object into a date string with the configured format
     * and timezone.
     *
     * @param \DateTimeInterface $dateTime A DateTimeInterface object
     *
     * @throws TransformationFailedException If the given value is not a \DateTimeInterface
     */
    public function transform(mixed $dateTime): string
    {
        if (null === $dateTime) {
            return '';
        }

        if (!$dateTime instanceof \DateTimeInterface) {
            throw new TransformationFailedException('Expected a \DateTimeInterface.');
        }

        $dateTime = \DateTimeImmutable::createFromInterface($dateTime);
        $dateTime = $dateTime->setTimezone(new \DateTimeZone($this->outputTimezone));

        return $dateTime->format($this->generateFormat);
    }

    /**
     * Transforms a date string in the configured timezone into a DateTime object.
     *
     * @param string $value A value as produced by PHP's date() function
     *
     * @throws TransformationFailedException If the given value is not a string,
     *                                       or could not be transformed
     */
    public function reverseTransform(mixed $value): ?\DateTime
    {
        if (empty($value)) {
            return null;
        }

        if (!\is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if (str_contains($value, "\0")) {
            throw new TransformationFailedException('Null bytes not allowed');
        }

        $outputTz = new \DateTimeZone($this->outputTimezone);
        $dateTime = \DateTime::createFromFormat($this->parseFormat, $value, $outputTz);

        $lastErrors = \DateTime::getLastErrors() ?: ['error_count' => 0, 'warning_count' => 0];

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
