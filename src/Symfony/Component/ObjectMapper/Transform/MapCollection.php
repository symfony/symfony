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

use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\TransformCallableInterface;

/**
 * @template T of object
 *
 * @implements TransformCallableInterface<object, T>
 */
class MapCollection implements TransformCallableInterface
{
    public function __construct(
        private ObjectMapperInterface $objectMapper = new ObjectMapper(),
    ) {
    }

    public function __invoke(mixed $value, object $source, ?object $target): mixed
    {
        if (!is_iterable($value)) {
            throw new MappingException(\sprintf('The MapCollection transform expects an iterable, "%s" given.', get_debug_type($value)));
        }

        $values = [];
        foreach ($value as $k => $v) {
            $values[$k] = $this->objectMapper->map($v);
        }

        return $values;
    }
}
