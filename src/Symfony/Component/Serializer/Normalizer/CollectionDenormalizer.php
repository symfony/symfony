<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

/**
 * Denormalizes arrays of objects into a single collection.
 *
 * @author Nikita Shipilov <nikita.shipilov.22@gmail.com>
 *
 * @final
 */
class CollectionDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface, CacheableSupportsMethodInterface
{
    use DenormalizerAwareTrait;

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): \ArrayAccess
    {
        if (null === $this->denormalizer) {
            throw new BadMethodCallException('Please set a denormalizer before calling denormalize()!');
        }

        $returnType = $this->getCollectionReturnType($type);
        if (!\is_array($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('Data expected to be "%s", "%s" given.', $returnType, get_debug_type($data)), $data, [Type::BUILTIN_TYPE_ARRAY], $context['deserialization_path'] ?? null);
        }

        /** @var \ArrayAccess $collection */
        $collection = new $type();

        foreach ($this->denormalizer->denormalize($data, $returnType, $format, $context) as $key => $value) {
            $collection[$key] = $value;
        }

        return $collection;
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        if (null === $this->denormalizer) {
            throw new BadMethodCallException(sprintf('The nested denormalizer needs to be set to allow "%s()" to be used.', __METHOD__));
        }

        return $this->isTypeValid($type)
            && $this->denormalizer->supportsDenormalization(
                $data,
                $this->getCollectionReturnType($type),
                $format,
                $context
            );
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return $this->denormalizer instanceof CacheableSupportsMethodInterface && $this->denormalizer->hasCacheableSupportsMethod();
    }

    private function isTypeValid(string $type): bool
    {
        if (!class_exists($type)) {
            return false;
        }

        $reflectionType = new \ReflectionClass($type);

        return $reflectionType->implementsInterface(\ArrayAccess::class)
            && $reflectionType->getMethod('offsetGet')->hasReturnType();
    }

    private function getCollectionReturnType(string $type): string
    {
        $reflectionType = new \ReflectionClass($type);
        /** @var \ReflectionNamedType $returnType */
        $returnType = $reflectionType->getMethod('offsetGet')->getReturnType();

        return $returnType->getName().'[]';
    }
}
