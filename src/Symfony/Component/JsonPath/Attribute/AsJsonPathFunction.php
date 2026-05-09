<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Attribute;

use Symfony\Component\JsonPath\FunctionReturnType;

/**
 * Service tag to autoconfigure JsonPath functions.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsJsonPathFunction
{
    public function __construct(
        public string $name,
        public FunctionReturnType $returnType = FunctionReturnType::Value,
    ) {
    }
}
