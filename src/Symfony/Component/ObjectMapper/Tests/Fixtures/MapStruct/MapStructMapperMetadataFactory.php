<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct;

use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/**
 * A Metadata factory that implements the basics behind https://mapstruct.org/.
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class MapStructMapperMetadataFactory implements ObjectMapperMetadataFactoryInterface
{
    public function __construct(private readonly string $mapper)
    {
        if (!is_a($mapper, ObjectMapperInterface::class, true)) {
            throw new \RuntimeException(\sprintf('Mapper should implement "%s".', ObjectMapperInterface::class));
        }
    }

    public function create(object $object, ?string $property = null, array $context = []): array
    {
        $refl = new \ReflectionClass($this->mapper);
        $mapTo = [];
        $source = $property ?? $object::class;
        foreach (($property ? $refl->getMethod('map') : $refl)->getAttributes(Map::class) as $mappingAttribute) {
            $map = $mappingAttribute->newInstance();
            if ($map->source === $source) {
                $mapTo[] = new Mapping(source: $map->source, target: $map->target, if: $map->if, transform: $map->transform);

                continue;
            }
        }

        // Default is to map properties to a property of the same name
        if (!$mapTo && $property) {
            $mapTo[] = new Mapping(source: $property, target: $property);
        }

        return $mapTo;
    }
}
