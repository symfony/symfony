<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Functions;

/**
 * Declares a service as a JsonPath function, usable in JSONPath expressions.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsJsonPathFunction
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
