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
use Symfony\Component\ObjectMapper\Exception\MappingException;

/**
 * @internal
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class ReflectionObjectMapperMetadataFactory implements ObjectMapperMetadataFactoryInterface
{
    public function create(object $object, ?string $property = null, array $context = []): array
    {
        try {
            $refl = new \ReflectionClass($object);
            $mapTo = [];
            foreach (($property ? $refl->getProperty($property) : $refl)->getAttributes(Map::class, \ReflectionAttribute::IS_INSTANCEOF) as $mapAttribute) {
                $map = $mapAttribute->newInstance();
                $mapTo[] = new Mapping($map->target, $map->source, $map->if, $map->transform);
            }

            return $mapTo;
        } catch (\ReflectionException $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
