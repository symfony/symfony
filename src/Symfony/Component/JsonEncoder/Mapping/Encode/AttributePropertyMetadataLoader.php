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
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;

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
    ) {
    }

    public function load(string $className, array $options = [], array $context = []): array
    {
        $initialResult = $this->decorated->load($className, $options, $context);
        $result = [];

        foreach ($initialResult as $initialEncodedName => $initialMetadata) {
            $attributesMetadata = $this->getPropertyAttributesMetadata(new \ReflectionProperty($className, $initialMetadata->getName()));
            $encodedName = $attributesMetadata['name'] ?? $initialEncodedName;

            if (null !== $normalizerId = $attributesMetadata['normalizer_id'] ?? null) {
                $normalizer = $this->getAndValidateNormalizer($normalizerId);

                $result[$encodedName] = $initialMetadata
                    ->withType($normalizer::getNormalizedType())
                    ->withAdditionalNormalizer($normalizerId);

                continue;
            }

            $result[$encodedName] = $initialMetadata;
        }

        return $result;
    }

    /**
     * @return array{name?: string, normalizer_id?: string}
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
            $metadata['normalizer_id'] = $reflectionAttribute->newInstance()->getServiceId();
        }

        return $metadata;
    }

    private function getAndValidateNormalizer(string $normalizerId): NormalizerInterface
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
