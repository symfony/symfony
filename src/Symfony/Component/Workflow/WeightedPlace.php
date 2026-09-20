<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

/**
 * Represents a weighted place in an enum workflow definition.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 */
final class WeightedPlace
{
    public function __construct(public readonly \UnitEnum $place, public readonly int $weight)
    {
        if ($place instanceof \BackedEnum && !\is_string($place->value)) {
            throw new \InvalidArgumentException('Integer-backed enums cannot be used as workflow places.');
        }
        if ($weight < 1) {
            throw new \InvalidArgumentException(\sprintf('The weight must be greater than 0, %d given.', $weight));
        }
        if ($place instanceof \BackedEnum && '' === $place->value) {
            throw new \InvalidArgumentException('The place name cannot be empty.');
        }
    }
}
