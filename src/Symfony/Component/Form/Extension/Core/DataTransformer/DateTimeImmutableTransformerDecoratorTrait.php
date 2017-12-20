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

/**
 * A BC-layer trait for DateTimeTo*Transformers.
 *
 * @author Valentin Udaltsov <udaltsov.valentin@gmail.com>
 *
 * @internal
 */
trait DateTimeImmutableTransformerDecoratorTrait
{
    /**
     * @var DataTransformerInterface
     */
    private $decorated;

    /**
     * Converts \DateTime values to \DateTimeImmutable and forwards the transform() call
     * to the decorated transformer.
     */
    public function transform($value)
    {
        if ($value instanceof \DateTime) {
            $value = \DateTimeImmutable::createFromMutable($value);
        }

        return $this->decorated->transform($value);
    }

    /**
     * Forwards the reverseTransform() call to the decorated transformer and
     * converts \DateTimeImmutable return values to \DateTime.
     */
    public function reverseTransform($value)
    {
        $value = $this->decorated->reverseTransform($value);

        if ($value instanceof \DateTimeImmutable) {
            $dateTime = new \DateTime(null, $value->getTimezone());
            $dateTime->setTimestamp($value->getTimestamp());

            return $dateTime;
        }

        return $value;
    }
}
