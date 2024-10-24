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
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;

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
    ) {
    }

    public function load(string $className, array $options = [], array $context = []): array
    {
        $initialResult = $this->decorated->load($className, $options, $context);
        $result = [];

        foreach ($initialResult as $initialEncodedName => $initialMetadata) {
            $attributesMetadata = $this->getPropertyAttributesMetadata(new \ReflectionProperty($className, $initialMetadata->getName()));
            $encodedName = $attributesMetadata['name'] ?? $initialEncodedName;

            if (null !== $denormalizerId = $attributesMetadata['denormalizer_id'] ?? null) {
                $denormalizer = $this->getAndValidateDenormalizer($denormalizerId);

                $result[$encodedName] = $initialMetadata
                    ->withType($denormalizer::getNormalizedType())
                    ->withAdditionalDenormalizer($denormalizerId);

                continue;
            }

            $result[$encodedName] = $initialMetadata;
        }

        return $result;
    }

    /**
     * @return array{name?: string, denormalizer_id?: string}
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
            $metadata['denormalizer_id'] = $reflectionAttribute->newInstance()->getServiceId();
        }

        return $metadata;
    }

    private function getAndValidateDenormalizer(string $denormalizerId): DenormalizerInterface
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
