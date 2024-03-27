<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Mapping\Encode;

use Symfony\Component\JsonEncoder\Attribute\EncodedName;
use Symfony\Component\JsonEncoder\Attribute\EncodeFormatter;
use Symfony\Component\JsonEncoder\Attribute\MaxDepth;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Enhances properties encoding metadata based on properties' attributes.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final readonly class AttributePropertyMetadataLoader implements PropertyMetadataLoaderInterface
{
    public function __construct(
        private PropertyMetadataLoaderInterface $decorated,
        private TypeResolverInterface $typeResolver,
    ) {
    }

    public function load(string $className, array $config, array $context): array
    {
        $initialResult = $this->decorated->load($className, $config, $context);
        $result = [];

        foreach ($initialResult as $initialEncodedName => $initialMetadata) {
            $attributesMetadata = $this->getPropertyAttributesMetadata(new \ReflectionProperty($className, $initialMetadata->name));
            $encodedName = $attributesMetadata['name'] ?? $initialEncodedName;

            if (isset($attributesMetadata['max_depth']) && ($context['depth_counters'][$className] ?? 0) > $attributesMetadata['max_depth']) {
                if (null === $formatter = $attributesMetadata['max_depth_reached_formatter'] ?? null) {
                    continue;
                }

                $reflectionFormatter = new \ReflectionFunction(\Closure::fromCallable($formatter));
                $type = $this->typeResolver->resolve($reflectionFormatter);

                $result[$encodedName] = $initialMetadata
                    ->withType($type)
                    ->withFormatter($formatter(...));

                continue;
            }

            if (null !== $formatter = $attributesMetadata['formatter'] ?? null) {
                $reflectionFormatter = new \ReflectionFunction(\Closure::fromCallable($formatter));
                $type = $this->typeResolver->resolve($reflectionFormatter);

                $result[$encodedName] = $initialMetadata
                    ->withType($type)
                    ->withFormatter($formatter(...));

                continue;
            }

            $result[$encodedName] = $initialMetadata;
        }

        return $result;
    }

    /**
     * @return array{name?: string, formatter?: callable, max_depth?: int, max_depth_reached_formatter?: ?callable}
     */
    private function getPropertyAttributesMetadata(\ReflectionProperty $reflectionProperty): array
    {
        $metadata = [];

        $reflectionAttribute = $reflectionProperty->getAttributes(EncodedName::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        if (null !== $reflectionAttribute) {
            $metadata['name'] = $reflectionAttribute->newInstance()->name;
        }

        $reflectionAttribute = $reflectionProperty->getAttributes(EncodeFormatter::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        if (null !== $reflectionAttribute) {
            $metadata['formatter'] = $reflectionAttribute->newInstance()->formatter;
        }

        $reflectionAttribute = $reflectionProperty->getAttributes(MaxDepth::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        if (null !== $reflectionAttribute) {
            $attributeInstance = $reflectionAttribute->newInstance();

            $metadata['max_depth'] = $attributeInstance->maxDepth;
            $metadata['max_depth_reached_formatter'] = $attributeInstance->maxDepthReachedFormatter;
        }

        return $metadata;
    }
}
