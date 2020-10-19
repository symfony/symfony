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
    public function transform($value): ?\DateTime
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof \DateTimeImmutable) {
            throw new TransformationFailedException('Expected a \DateTimeImmutable.');
        }

        if (\PHP_VERSION_ID >= 70300) {
            return \DateTime::createFromImmutable($value);
        }

        return \DateTime::createFromFormat('U.u', $value->format('U.u'))->setTimezone($value->getTimezone());
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
    public function reverseTransform($value): ?\DateTimeImmutable
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
