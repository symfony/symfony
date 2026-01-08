<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Transform;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Attribute\MapCollection as MapCollectionAttribute;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\MappingAwareTransformCallableInterface;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/**
 * @template T of object
 *
 * @implements MappingAwareTransformCallableInterface<object, T>
 */
class MapCollection implements MappingAwareTransformCallableInterface
{
    public function __construct(
        private ObjectMapperInterface $objectMapper = new ObjectMapper(),
    ) {
    }

    public function __invoke(mixed $value, object $source, ?object $target, Map $map): mixed
    {
        if (!is_iterable($value)) {
            throw new MappingException(\sprintf('The MapCollection transform expects an iterable, "%s" given.', get_debug_type($value)));
        }

        $itemClass = $map instanceof MapCollectionAttribute ? $map->itemClass : null;

        $values = [];
        foreach ($value as $k => $v) {
            $values[$k] = $this->objectMapper->map($v, $itemClass);
        }

        return $values;
    }
}
