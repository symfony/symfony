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
use Symfony\Component\ObjectMapper\Attribute\TransformAllProperties;
use Symfony\Component\ObjectMapper\Exception\MappingException;

/**
 * @internal
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class ReflectionObjectMapperMetadataFactory implements ObjectMapperMetadataFactoryInterface
{
    private array $reflectionClassCache = [];
    private array $attributesCache = [];
    /** @var array<class-string, list<string|callable>> */
    private array $classAttributesCache = [];

    public function create(object $object, ?string $property = null, array $context = []): array
    {
        try {
            $key = $object::class.($property ?? '');

            if (isset($this->attributesCache[$key])) {
                return $this->attributesCache[$key];
            }

            $refl = $this->reflectionClassCache[$object::class] ??= new \ReflectionClass($object);
            $attributes = ($property ? $refl->getProperty($property) : $refl)->getAttributes(Map::class, \ReflectionAttribute::IS_INSTANCEOF);

            $globalTransforms = null !== $property ? $this->getClassPropertyTransforms($refl) : [];
            $mappings = [];

            foreach ($attributes as $attribute) {
                $map = $attribute->newInstance();
                $transforms = $map->transform ?? [];
                if ($transforms && \is_callable($transforms) || !\is_array($transforms)) {
                    $transforms = [$transforms];
                }

                $mappings[] = new Mapping($map->target, $map->source, $map->if, [...$globalTransforms, ...$transforms]);
            }

            if ($globalTransforms && !$mappings) {
                $mappings[] = new Mapping(null, null, null, $globalTransforms);
            }

            return $this->attributesCache[$key] = $mappings;
        } catch (\ReflectionException $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return list<string|callable>
     */
    private function getClassPropertyTransforms(\ReflectionClass $refl): array
    {
        $key = $refl->getName();
        if (isset($this->classAttributesCache[$key])) {
            return $this->classAttributesCache[$key];
        }

        $attributes = $refl->getAttributes(TransformAllProperties::class, \ReflectionAttribute::IS_INSTANCEOF);
        if (!$attributes) {
            return $this->classAttributesCache[$key] = [];
        }

        $globalTransforms = [];
        foreach ($attributes as $attribute) {
            if ($t = $attribute->newInstance()->transform ?? []) {
                if (\is_callable($t) || !\is_array($t)) {
                    $t = [$t];
                }

                foreach ($t as $transform) {
                    $globalTransforms[] = $transform;
                }
            }
        }

        return $this->classAttributesCache[$key] = $globalTransforms;
    }
}
