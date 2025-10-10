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

use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms between a DatePoint object and a DateTime object.
 *
 * @implements DataTransformerInterface<DatePoint, \DateTime>
 */
final class DatePointToDateTimeTransformer implements DataTransformerInterface
{
    /**
     * Transforms a DatePoint into a DateTime object.
     *
     * @param DatePoint|null $value A DatePoint object
     *
     * @throws TransformationFailedException If the given value is not a DatePoint
     */
    public function transform(mixed $value): ?\DateTime
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof DatePoint) {
            throw new TransformationFailedException(\sprintf('Expected a "%s".', DatePoint::class));
        }

        return \DateTime::createFromImmutable($value);
    }

    /**
     * Transforms a DateTime object into a DatePoint object.
     *
     * @param \DateTime|null $value A DateTime object
     *
     * @throws TransformationFailedException If the given value is not a \DateTime
     */
    public function reverseTransform(mixed $value): ?DatePoint
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof \DateTime) {
            throw new TransformationFailedException('Expected a \DateTime.');
        }

        return DatePoint::createFromMutable($value);
    }
}
