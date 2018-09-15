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
 */
class DateTimeToHtml5DateTimeLocalTransformer extends BaseDateTimeTransformer
{
    const HTML5_FORMAT = 'Y-m-d\\TH:i:s';

    /**
     * Transforms a normalized date into a localized date without trailing timezone.
     *
     * According to the HTML standard, the input string of a datetime-local
     * input is a RFC3339 date followed by 'T', followed by a RFC3339 time.
     * http://w3c.github.io/html-reference/datatypes.html#form.data.datetime-local
     *
     * @param \DateTime|\DateTimeInterface $dateTime A DateTime object
     *
     * @return string The formatted date
     *
     * @throws TransformationFailedException If the given value is not an
     *                                       instance of \DateTime or \DateTimeInterface
     */
    public function transform($dateTime)
    {
        if (null === $dateTime) {
            return '';
        }

        if (!$dateTime instanceof \DateTime && !$dateTime instanceof \DateTimeInterface) {
            throw new TransformationFailedException('Expected a \DateTime or \DateTimeInterface.');
        }

        if ($this->inputTimezone !== $this->outputTimezone) {
            if (!$dateTime instanceof \DateTimeImmutable) {
                $dateTime = clone $dateTime;
            }

            $dateTime = $dateTime->setTimezone(new \DateTimeZone($this->outputTimezone));
        }

        return $dateTime->format(self::HTML5_FORMAT);
    }

    /**
     * Transforms a formatted datetime-local string into a normalized date.
     *
     * @param string $dateTimeLocal Formatted string
     *
     * @return \DateTime Normalized date
     *
     * @throws TransformationFailedException If the given value is not a string,
     *                                       if the value could not be transformed
     */
    public function reverseTransform($dateTimeLocal)
    {
        if (!\is_string($dateTimeLocal)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if ('' === $dateTimeLocal) {
            return;
        }

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ]\d{2}:\d{2}(?::\d{2})?$/', $dateTimeLocal, $matches)) {
            throw new TransformationFailedException(sprintf('The date "%s" is not a valid date.', $dateTimeLocal));
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
            throw new TransformationFailedException(sprintf('The date "%s-%s-%s" is not a valid date.', $matches[1], $matches[2], $matches[3]));
        }

        return $dateTime;
    }
}
