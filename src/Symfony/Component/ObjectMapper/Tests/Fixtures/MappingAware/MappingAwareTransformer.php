<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MappingAware;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\MappingAwareTransformCallableInterface;

/**
 * @implements MappingAwareTransformCallableInterface<object, object>
 */
class MappingAwareTransformer implements MappingAwareTransformCallableInterface
{
    public function __invoke(mixed $value, object $source, ?object $target, ?Map $mapping = null): mixed
    {
        if (!$mapping) {
            return $value;
        }

        return $mapping->target ?? 'source:'.$mapping->source;
    }
}
