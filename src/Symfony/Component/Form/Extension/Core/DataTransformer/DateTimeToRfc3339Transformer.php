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
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DateTimeToRfc3339Transformer extends BaseDateTimeTransformer
{
    /**
     * Transforms a normalized date into a localized date.
     *
     * @param \DateTimeInterface $dateTime A DateTimeInterface object
     *
     * @return string The formatted date
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

        if ($this->inputTimezone !== $this->outputTimezone) {
            if (!$dateTime instanceof \DateTimeImmutable) {
                $dateTime = clone $dateTime;
            }

            $dateTime = $dateTime->setTimezone(new \DateTimeZone($this->outputTimezone));
        }

        return preg_replace('/\+00:00$/', 'Z', $dateTime->format('c'));
    }

    /**
     * Transforms a formatted string following RFC 3339 into a normalized date.
     *
     * @param string $rfc3339 Formatted string
     *
     * @return \DateTime|null Normalized date
     *
     * @throws TransformationFailedException If the given value is not a string,
     *                                       if the value could not be transformed
     */
    public function reverseTransform($rfc3339)
    {
        if (!\is_string($rfc3339)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if ('' === $rfc3339) {
            return null;
        }

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})T\d{2}:\d{2}(?::\d{2})?(?:\.\d+)?(?:Z|(?:(?:\+|-)\d{2}:\d{2}))$/', $rfc3339, $matches)) {
            throw new TransformationFailedException(sprintf('The date "%s" is not a valid date.', $rfc3339));
        }

        try {
            $dateTime = new \DateTime($rfc3339);
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
