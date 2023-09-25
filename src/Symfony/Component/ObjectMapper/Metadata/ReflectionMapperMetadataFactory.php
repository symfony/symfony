<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Metadata;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Exception\ReflectionException;

/**
 * @internal
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class ReflectionMapperMetadataFactory implements MapperMetadataFactoryInterface
{
    public function create(object $object, ?string $property = null, array $context = []): array
    {
        try {
            $refl = new \ReflectionClass($object);
            $mapTo = [];
            foreach (($property ? $refl->getProperty($property) : $refl)->getAttributes(Map::class) as $mapAttribute) {
                $map = $mapAttribute->newInstance();
                $mapTo[] = new Mapping(source: $map->source, target: $map->target, if: $map->if, transform: $map->transform);
            }

            return $mapTo;
        } catch (\ReflectionException $e) {
            throw new ReflectionException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
