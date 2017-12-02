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
 * Transforms between a timestamp and a DateTimeImmutable object.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class DateTimeImmutableToTimestampTransformer extends BaseDateTimeTransformer
{
    /**
     * Transforms a DateTimeImmutable object into a timestamp in the configured timezone.
     *
     * @param \DateTimeImmutable $dateTime A DateTimeImmutable object
     *
     * @return int A timestamp
     *
     * @throws TransformationFailedException If the given value is not a \DateTimeImmutable
     */
    public function transform($dateTime): ?int
    {
        if (null === $dateTime) {
            return null;
        }

        if (!$dateTime instanceof \DateTimeImmutable) {
            throw new TransformationFailedException('Expected a \DateTimeImmutable.');
        }

        return $dateTime->getTimestamp();
    }

    /**
     * Transforms a timestamp in the configured timezone into a DateTime object.
     *
     * @param string $value A timestamp
     *
     * @return \DateTimeImmutable A \DateTimeImmutable object
     *
     * @throws TransformationFailedException If the given value is not a timestamp
     *                                       or if the given timestamp is invalid
     */
    public function reverseTransform($value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        if (!is_numeric($value)) {
            throw new TransformationFailedException('Expected a numeric.');
        }

        try {
            $dateTime = (new \DateTimeImmutable())
                ->setTimezone(new \DateTimeZone($this->outputTimezone))
                ->setTimestamp($value);

            if ($this->inputTimezone !== $this->outputTimezone) {
                $dateTime = $dateTime->setTimezone(new \DateTimeZone($this->inputTimezone));
            }
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        return $dateTime;
    }
}
