<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Attribute;

/**
 * Defines the maximum encoding depth for the property.
 *
 * When the maximum depth is reached, the $maxDepthReachedFormatter callable is called if it has been defined.
 *
 * The first argument of that callable must be the input data.
 * Then, it is possible to inject the config and services thanks to their FQCN.
 *
 * It must return the new data.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class MaxDepth
{
    /**
     * @param positive-int                                       $maxDepth
     * @param ?callable(mixed $value, mixed ...$services): mixed $maxDepthReachedFormatter
     */
    public function __construct(
        public int $maxDepth,
        public mixed $maxDepthReachedFormatter = null,
    ) {
    }
}
