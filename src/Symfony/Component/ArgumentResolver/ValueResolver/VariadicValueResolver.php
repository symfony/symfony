<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ArgumentResolver\ValueResolver;

use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\ArgumentValueSource\SourceValue;
use Symfony\Component\ArgumentResolver\Exception\InvalidArgumentException;

/**
 * Yields a variadic argument's values from the request attributes.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final readonly class VariadicValueResolver implements ValueResolverInterface
{
    public function resolveArgument(ArgumentMetadata $argument, SourceValue $value): iterable
    {
        $values = $value->get();

        if (!$argument->isVariadic() || SourceValue::NOT_FOUND === $values) {
            return [];
        }

        if (!\is_array($values)) {
            throw new InvalidArgumentException(\sprintf('Argument "...$%1$s" is required to be an array, source value "%1$s" contains a type of "%2$s" instead.', $argument->getName(), get_debug_type($values)));
        }


        return $values;
    }
}
