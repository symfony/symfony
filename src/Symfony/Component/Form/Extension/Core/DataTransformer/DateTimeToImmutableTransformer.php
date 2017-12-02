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
 * A BC-layer transformer for DateTimeImmutableTo* transformers.
 *
 * @author Valentin Udaltsov <udaltsov.valentin@gmail.com>
 *
 * @internal
 */
class DateTimeToImmutableTransformer implements DataTransformerInterface
{
    private $reverseTransform;

    public function __construct(bool $reverseTransform = true)
    {
        $this->reverseTransform = $reverseTransform;
    }

    /**
     * Transforms \DateTime values to \DateTimeImmutable and triggers a deprecation.
     */
    public function transform($value)
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof \DateTimeInterface) {
            throw new TransformationFailedException('Expected a \DateTimeInterface.');
        }

        if ($value instanceof \DateTime) {
            // deprecation

            return \DateTimeImmutable::createFromMutable($value);
        }

        return $value;
    }

    /**
     * Transforms \DateTimeImmutable values to \DateTime when needed.
     */
    public function reverseTransform($value)
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof \DateTimeInterface) {
            throw new TransformationFailedException('Expected a \DateTimeInterface.');
        }

        if ($this->reverseTransform && $value instanceof \DateTimeImmutable) {
            $dateTime = new \DateTime(null, $value->getTimezone());
            $dateTime->setTimestamp($value->getTimestamp());

            return $dateTime;
        }

        return $value;
    }
}
