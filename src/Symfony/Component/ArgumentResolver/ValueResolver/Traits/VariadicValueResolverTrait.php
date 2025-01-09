<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ArgumentResolver\ValueResolver\Traits;

use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;

/**
 * Yields a variadic argument's values from the request attributes.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
trait VariadicValueResolverTrait
{
    public function doResolve(ArgumentMetadata $argument, array $rawValues): iterable
    {
        if (!$argument->isVariadic() || !$rawValues) {
            return [];
        }

        return $rawValues;
    }
}
