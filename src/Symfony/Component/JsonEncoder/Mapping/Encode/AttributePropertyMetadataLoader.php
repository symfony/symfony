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

use Psr\Container\ContainerInterface;
use Symfony\Component\JsonEncoder\Attribute\EncodedName;
use Symfony\Component\JsonEncoder\Attribute\Normalizer;
use Symfony\Component\JsonEncoder\Encode\Normalizer\NormalizerInterface;
use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;
use Symfony\Component\JsonEncoder\Exception\RuntimeException;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Enhances properties encoding metadata based on properties' attributes.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class AttributePropertyMetadataLoader implements PropertyMetadataLoaderInterface
{
    public function __construct(
        private PropertyMetadataLoaderInterface $decorated,
        private ContainerInterface $normalizers,
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

            if (null === $normalizer = $attributesMetadata['normalizer'] ?? null) {
                $result[$encodedName] = $initialMetadata;

                continue;
            }

            if (\is_string($normalizer)) {
                $normalizerService = $this->getAndValidateNormalizerService($normalizer);
                $normalizedType = $normalizerService::getNormalizedType();

                $result[$encodedName] = $initialMetadata
                    ->withType($normalizedType)
                    ->withAdditionalNormalizer($normalizer);

                continue;
            }

            try {
                $normalizerReflection = new \ReflectionFunction($normalizer);
            } catch (\ReflectionException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            $normalizedType = $this->typeResolver->resolve($normalizerReflection);

            $result[$encodedName] = $initialMetadata
                ->withType($normalizedType)
                ->withAdditionalNormalizer($normalizer);
        }

        return $result;
    }

    /**
     * @return array{name?: string, normalizer?: string|\Closure}
     */
    private function getPropertyAttributesMetadata(\ReflectionProperty $reflectionProperty): array
    {
        $metadata = [];

        $reflectionAttribute = $reflectionProperty->getAttributes(EncodedName::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        if (null !== $reflectionAttribute) {
            $metadata['name'] = $reflectionAttribute->newInstance()->getName();
        }

        $reflectionAttribute = $reflectionProperty->getAttributes(Normalizer::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        if (null !== $reflectionAttribute) {
            $metadata['normalizer'] = $reflectionAttribute->newInstance()->getNormalizer();
        }

        return $metadata;
    }

    private function getAndValidateNormalizerService(string $normalizerId): NormalizerInterface
    {
        if (!$this->normalizers->has($normalizerId)) {
            throw new InvalidArgumentException(\sprintf('You have requested a non-existent normalizer service "%s". Did you implement "%s"?', $normalizerId, NormalizerInterface::class));
        }

        $normalizer = $this->normalizers->get($normalizerId);
        if (!$normalizer instanceof NormalizerInterface) {
            throw new InvalidArgumentException(\sprintf('The "%s" normalizer service does not implement "%s".', $normalizerId, NormalizerInterface::class));
        }

        return $normalizer;
    }
}
