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

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms between a DateTimeImmutable object and a DateTime object.
 *
 * @author Valentin Udaltsov <udaltsov.valentin@gmail.com>
 */
final class DateTimeImmutableToDateTimeTransformer implements DataTransformerInterface
{
    /**
     * Transforms a DateTimeImmutable into a DateTime object.
     *
     * @param \DateTimeImmutable|null $value A DateTimeImmutable object
     *
     * @return \DateTime|null A \DateTime object
     *
     * @throws TransformationFailedException If the given value is not a \DateTimeImmutable
     */
    public function transform($value)
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof \DateTimeImmutable) {
            throw new TransformationFailedException('Expected a \DateTimeImmutable.');
        }

        return \DateTime::createFromFormat(\DateTime::RFC3339, $value->format(\DateTime::RFC3339));
    }

    /**
     * Transforms a DateTime object into a DateTimeImmutable object.
     *
     * @param \DateTime|null $value A DateTime object
     *
     * @return \DateTimeImmutable|null A DateTimeImmutable object
     *
     * @throws TransformationFailedException If the given value is not a \DateTime
     */
    public function reverseTransform($value)
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof \DateTime) {
            throw new TransformationFailedException('Expected a \DateTime.');
        }

        return \DateTimeImmutable::createFromMutable($value);
    }
}
