<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Mapping\Read;

use Psr\Container\ContainerInterface;
use Symfony\Component\JsonStreamer\Attribute\StreamedName;
use Symfony\Component\JsonStreamer\Attribute\ValueTransformer;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\JsonStreamer\Exception\RuntimeException;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonStreamer\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Enhances properties stream reading metadata based on properties' attributes.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class AttributePropertyMetadataLoader implements PropertyMetadataLoaderInterface
{
    public function __construct(
        private PropertyMetadataLoaderInterface $decorated,
        private ContainerInterface $valueTransformers,
        private TypeResolverInterface $typeResolver,
    ) {
    }

    public function load(string $className, array $options = [], array $context = []): array
    {
        $initialResult = $this->decorated->load($className, $options, $context);
        $result = [];

        foreach ($initialResult as $initialStreamedName => $initialMetadata) {
            try {
                $propertyReflection = new \ReflectionProperty($className, $initialMetadata->getName());
            } catch (\ReflectionException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            $attributesMetadata = $this->getPropertyAttributesMetadata($propertyReflection);
            $streamedName = $attributesMetadata['name'] ?? $initialStreamedName;

            if (null === $valueTransformer = $attributesMetadata['streamToNativeValueTransformer'] ?? null) {
                $result[$streamedName] = $initialMetadata;

                continue;
            }

            if (\is_string($valueTransformer)) {
                $valueTransformerService = $this->getAndValidateValueTransformerService($valueTransformer);

                $result[$streamedName] = $initialMetadata
                    ->withType($valueTransformerService::getStreamValueType())
                    ->withAdditionalStreamToNativeValueTransformer($valueTransformer);

                continue;
            }

            try {
                $valueTransformerReflection = new \ReflectionFunction($valueTransformer);
            } catch (\ReflectionException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            if (null === ($parameterReflection = $valueTransformerReflection->getParameters()[0] ?? null)) {
                throw new InvalidArgumentException(\sprintf('"%s" property\'s streamToNative callable has no parameter.', $initialStreamedName));
            }

            $result[$streamedName] = $initialMetadata
                ->withType($this->typeResolver->resolve($parameterReflection))
                ->withAdditionalStreamToNativeValueTransformer($valueTransformer);
        }

        return $result;
    }

    /**
     * @return array{name?: string, streamToNativeValueTransformer?: string|\Closure}
     */
    private function getPropertyAttributesMetadata(\ReflectionProperty $reflectionProperty): array
    {
        $metadata = [];

        $reflectionAttribute = $reflectionProperty->getAttributes(StreamedName::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        if (null !== $reflectionAttribute) {
            $metadata['name'] = $reflectionAttribute->newInstance()->getName();
        }

        $reflectionAttribute = $reflectionProperty->getAttributes(ValueTransformer::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        if (null !== $reflectionAttribute) {
            $metadata['streamToNativeValueTransformer'] = $reflectionAttribute->newInstance()->getStreamToNative();
        }

        return $metadata;
    }

    private function getAndValidateValueTransformerService(string $valueTransformerId): ValueTransformerInterface
    {
        if (!$this->valueTransformers->has($valueTransformerId)) {
            throw new InvalidArgumentException(\sprintf('You have requested a non-existent value transformer service "%s". Did you implement "%s"?', $valueTransformerId, ValueTransformerInterface::class));
        }

        $valueTransformer = $this->valueTransformers->get($valueTransformerId);
        if (!$valueTransformer instanceof ValueTransformerInterface) {
            throw new InvalidArgumentException(\sprintf('The "%s" value transformer service does not implement "%s".', $valueTransformerId, ValueTransformerInterface::class));
        }

        return $valueTransformer;
    }
}
