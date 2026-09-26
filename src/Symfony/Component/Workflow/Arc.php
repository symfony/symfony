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
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class Arc
{
    public readonly string $place;

    public function __construct(
        string|\BackedEnum $place,
        public readonly int $weight,
    ) {
        if ($place instanceof \BackedEnum) {
            if (!\is_string($place->value)) {
                throw new \InvalidArgumentException(\sprintf('Only string-backed enums can be used as places, "%s" is not.', $place::class));
            }
            $place = $place->value;
        }
        if ($weight < 1) {
            throw new \InvalidArgumentException(\sprintf('The weight must be greater than 0, %d given.', $weight));
        }
        if ('' === $place) {
            throw new \InvalidArgumentException('The place name cannot be empty.');
        }

        $this->place = $place;
    }
}
