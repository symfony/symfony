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
 * Defines a callable that will be used to format the property data during encoding.
 *
 * The first argument of that callable must be the input data.
 * Then, it is possible to inject the config and services thanks to their FQCN.
 *
 * It must return the formatted data.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class EncodeFormatter
{
    /**
     * @param callable(mixed $value, mixed ...$services): mixed $formatter
     */
    public function __construct(
        public mixed $formatter,
    ) {
    }
}
