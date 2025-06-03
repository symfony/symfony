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
 * @author Franz Wilding <franz.wilding@me.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Fred Cox <mcfedr@gmail.com>
 *
 * @extends BaseDateTimeTransformer<string>
 */
class DateTimeToHtml5LocalDateTimeTransformer extends BaseDateTimeTransformer
{
    public const HTML5_FORMAT = 'Y-m-d\\TH:i:s';
    public const HTML5_FORMAT_NO_SECONDS = 'Y-m-d\\TH:i';

    public function __construct(?string $inputTimezone = null, ?string $outputTimezone = null, private bool $withSeconds = false)
    {
        parent::__construct($inputTimezone, $outputTimezone);
    }

    /**
     * Transforms a \DateTime into a local date and time string.
     *
     * According to the HTML standard, the input string of a datetime-local
     * input is an RFC3339 date followed by 'T', followed by an RFC3339 time.
     * https://html.spec.whatwg.org/multipage/common-microsyntaxes.html#valid-local-date-and-time-string
     *
     * @param \DateTimeInterface $dateTime
     *
     * @throws TransformationFailedException If the given value is not an
     *                                       instance of \DateTime or \DateTimeInterface
     */
    public function transform(mixed $dateTime): string
    {
        if (null === $dateTime) {
            return '';
        }

        if (!$dateTime instanceof \DateTimeInterface) {
            throw new TransformationFailedException('Expected a \DateTimeInterface.');
        }

        if ($this->inputTimezone !== $this->outputTimezone) {
            $dateTime = \DateTimeImmutable::createFromInterface($dateTime);
            $dateTime = $dateTime->setTimezone(new \DateTimeZone($this->outputTimezone));
        }

        return $dateTime->format($this->withSeconds ? self::HTML5_FORMAT : self::HTML5_FORMAT_NO_SECONDS);
    }

    /**
     * Transforms a local date and time string into a \DateTime.
     *
     * When transforming back to DateTime the regex is slightly laxer, taking into
     * account rules for parsing a local date and time string
     * https://html.spec.whatwg.org/multipage/common-microsyntaxes.html#parse-a-local-date-and-time-string
     *
     * @param string $dateTimeLocal Formatted string
     *
     * @throws TransformationFailedException If the given value is not a string,
     *                                       if the value could not be transformed
     */
    public function reverseTransform(mixed $dateTimeLocal): ?\DateTime
    {
        if (!\is_string($dateTimeLocal)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if ('' === $dateTimeLocal) {
            return null;
        }

        // to maintain backwards compatibility we do not strictly validate the submitted date
        // see https://github.com/symfony/symfony/issues/28699
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ]\d{2}:\d{2}(?::\d{2})?/', $dateTimeLocal, $matches)) {
            throw new TransformationFailedException(\sprintf('The date "%s" is not a valid date.', $dateTimeLocal));
        }

        try {
            $dateTime = new \DateTime($dateTimeLocal, new \DateTimeZone($this->outputTimezone));
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        if ($this->inputTimezone !== $dateTime->getTimezone()->getName()) {
            $dateTime->setTimezone(new \DateTimeZone($this->inputTimezone));
        }

        if (!checkdate($matches[2], $matches[3], $matches[1])) {
            throw new TransformationFailedException(\sprintf('The date "%s-%s-%s" is not a valid date.', $matches[1], $matches[2], $matches[3]));
        }

        return $dateTime;
    }
}
