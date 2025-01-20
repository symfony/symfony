<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Mapping\Decode;

use Psr\Container\ContainerInterface;
use Symfony\Component\JsonEncoder\Attribute\Denormalizer;
use Symfony\Component\JsonEncoder\Attribute\EncodedName;
use Symfony\Component\JsonEncoder\Decode\Denormalizer\DenormalizerInterface;
use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;
use Symfony\Component\JsonEncoder\Exception\RuntimeException;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Enhances properties decoding metadata based on properties' attributes.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class AttributePropertyMetadataLoader implements PropertyMetadataLoaderInterface
{
    public function __construct(
        private PropertyMetadataLoaderInterface $decorated,
        private ContainerInterface $denormalizers,
        private TypeResolverInterface $typeResolver,
    ) {
    }

    public function load(string $className, array $options = [], array $context = []): array
    {
        $initialResult = $this->decorated->load($className, $options, $context);
        $result = [];

        foreach ($initialResult as $initialEncodedName => $initialMetadata) {
            try {
                $propertyReflection = new \ReflectionProperty($className, $initialMetadata->getName());
            } catch (\ReflectionException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            $attributesMetadata = $this->getPropertyAttributesMetadata($propertyReflection);
            $encodedName = $attributesMetadata['name'] ?? $initialEncodedName;

            if (null === $denormalizer = $attributesMetadata['denormalizer'] ?? null) {
                $result[$encodedName] = $initialMetadata;

                continue;
            }

            if (\is_string($denormalizer)) {
                $denormalizerService = $this->getAndValidateDenormalizerService($denormalizer);
                $normalizedType = $denormalizerService::getNormalizedType();

                $result[$encodedName] = $initialMetadata
                    ->withType($normalizedType)
                    ->withAdditionalDenormalizer($denormalizer);

                continue;
            }

            try {
                $denormalizerReflection = new \ReflectionFunction($denormalizer);
            } catch (\ReflectionException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            if (null === ($parameterReflection = $denormalizerReflection->getParameters()[0] ?? null)) {
                throw new InvalidArgumentException(\sprintf('"%s" property\'s  denormalizer callable has no parameter.', $initialEncodedName));
            }

            $normalizedType = $this->typeResolver->resolve($parameterReflection);

            $result[$encodedName] = $initialMetadata
                ->withType($normalizedType)
                ->withAdditionalDenormalizer($denormalizer);
        }

        return $result;
    }

    /**
     * @return array{name?: string, denormalizer?: string|\Closure}
     */
    private function getPropertyAttributesMetadata(\ReflectionProperty $reflectionProperty): array
    {
        $metadata = [];

        $reflectionAttribute = $reflectionProperty->getAttributes(EncodedName::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        if (null !== $reflectionAttribute) {
            $metadata['name'] = $reflectionAttribute->newInstance()->getName();
        }

        $reflectionAttribute = $reflectionProperty->getAttributes(Denormalizer::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        if (null !== $reflectionAttribute) {
            $metadata['denormalizer'] = $reflectionAttribute->newInstance()->getDenormalizer();
        }

        return $metadata;
    }

    private function getAndValidateDenormalizerService(string $denormalizerId): DenormalizerInterface
    {
        if (!$this->denormalizers->has($denormalizerId)) {
            throw new InvalidArgumentException(\sprintf('You have requested a non-existent denormalizer service "%s". Did you implement "%s"?', $denormalizerId, DenormalizerInterface::class));
        }

        $denormalizer = $this->denormalizers->get($denormalizerId);
        if (!$denormalizer instanceof DenormalizerInterface) {
            throw new InvalidArgumentException(\sprintf('The "%s" denormalizer service does not implement "%s".', $denormalizerId, DenormalizerInterface::class));
        }

        return $denormalizer;
    }
}
