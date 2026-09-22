<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Attribute;

use Symfony\Component\Workflow\WeightedPlace;

/**
 * Defines a transition on a workflow enum or one of its cases.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_CLASS_CONSTANT | \Attribute::IS_REPEATABLE)]
final class Transition
{
    /**
     * @param \BackedEnum|WeightedPlace|list<\BackedEnum|WeightedPlace>      $to
     * @param \BackedEnum|WeightedPlace|list<\BackedEnum|WeightedPlace>|null $from
     * @param array<string, mixed>                                           $metadata
     */
    public function __construct(
        public readonly string $name,
        public readonly \BackedEnum|WeightedPlace|array $to,
        public readonly \BackedEnum|WeightedPlace|array|null $from = null,
        public readonly ?string $guard = null,
        public readonly array $metadata = [],
    ) {
    }
}
