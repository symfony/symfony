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
use Symfony\Component\ObjectMapper\ClassHierarchyTrait;
use Symfony\Component\ObjectMapper\Exception\MappingException;

/**
 * @internal
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class ReflectionObjectMapperMetadataFactory implements ObjectMapperMetadataFactoryInterface
{
    use ClassHierarchyTrait;

    private array $reflectionClassCache = [];
    private array $attributesCache = [];

    public function create(object $object, ?string $property = null, array $context = []): array
    {
        try {
            $key = $property ?? '';

            if (isset($this->attributesCache[$object::class][$key])) {
                return $this->attributesCache[$object::class][$key];
            }

            $refl = $this->reflectionClassCache[$object::class] ??= new \ReflectionClass($object);
            $target = $refl;
            if ($property && null === $target = $this->getPropertyFromHierarchy($refl, $property)) {
                return $this->attributesCache[$object::class][$key] = [];
            }
            $attributes = $target->getAttributes(Map::class, \ReflectionAttribute::IS_INSTANCEOF);
            $mappings = [];
            foreach ($attributes as $attribute) {
                $map = $attribute->newInstance();
                $mappings[] = new Mapping($map->target, $map->source, $map->if, $map->transform);
            }

            return $this->attributesCache[$object::class][$key] = $mappings;
        } catch (\ReflectionException $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
