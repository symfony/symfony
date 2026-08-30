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

use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Denormalizes arrays of objects.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @final
 */
class ArrayDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => null, '*' => false];
    }

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): array
    {
        if (!isset($this->denormalizer)) {
            throw new BadMethodCallException('Please set a denormalizer before calling denormalize()!');
        }
        if (!\is_array($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('Data expected to be "%s", "%s" given.', $type, get_debug_type($data)), $data, ['array'], $context['deserialization_path'] ?? null);
        }
        if (!str_ends_with($type, '[]')) {
            throw new InvalidArgumentException('Unsupported class: '.$type);
        }

        $type = substr($type, 0, -2);

        $typeIdentifiers = [];
        if (null !== $keyType = ($context['key_type'] ?? null)) {
            if ($keyType instanceof Type) {
                // BC layer for type-info < 7.2
                if (method_exists(Type::class, 'getBaseType')) {
                    $typeIdentifiers = array_map(static fn (Type $t): string => $t->getBaseType()->getTypeIdentifier()->value, $keyType instanceof UnionType ? $keyType->getTypes() : [$keyType]);
                } else {
                    /** @var list<BuiltinType<TypeIdentifier::INT>|BuiltinType<TypeIdentifier::STRING>> */
                    $keyTypes = $keyType instanceof UnionType ? $keyType->getTypes() : [$keyType];

                    $typeIdentifiers = array_map(static fn (BuiltinType $t): string => $t->getTypeIdentifier()->value, $keyTypes);
                }
            } else {
                $typeIdentifiers = array_map(static fn (LegacyType $t): string => $t->getBuiltinType(), \is_array($keyType) ? $keyType : [$keyType]);
            }
        }

        $valueType = $context['value_type'] ?? null;
        if ($valueType instanceof CollectionType) {
            $context['key_type'] = $valueType->getCollectionKeyType();
            $context['value_type'] = $valueType->getCollectionValueType();
        } elseif ($valueType instanceof LegacyType && $valueType->isCollection()) {
            if ($collectionKeyTypes = $valueType->getCollectionKeyTypes()) {
                $context['key_type'] = \count($collectionKeyTypes) > 1 ? $collectionKeyTypes : $collectionKeyTypes[0];
            }

            if ($collectionValueTypes = $valueType->getCollectionValueTypes()) {
                $context['value_type'] = $collectionValueTypes[0];
            }
        }

        if (\is_array($objectsToPopulate = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null)) {
            unset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);
        } else {
            $objectsToPopulate = [];
        }

        foreach ($data as $key => $value) {
            $subContext = $context;
            $subContext['deserialization_path'] = ($context['deserialization_path'] ?? false) ? \sprintf('%s[%s]', $context['deserialization_path'], $key) : "[$key]";

            if (\is_object($objectsToPopulate[$key] ?? null) || \is_array($objectsToPopulate[$key] ?? null)) {
                $subContext[AbstractNormalizer::OBJECT_TO_POPULATE] = $objectsToPopulate[$key];
            }

            $this->validateKeyType($typeIdentifiers, $key, $subContext['deserialization_path']);

            $data[$key] = $this->denormalizer->denormalize($value, $type, $format, $subContext);
        }

        return $data;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if (!isset($this->denormalizer)) {
            throw new BadMethodCallException(\sprintf('The nested denormalizer needs to be set to allow "%s()" to be used.', __METHOD__));
        }

        if (!str_ends_with($type, '[]') || !\is_array($data)) {
            return false;
        }

        $itemType = substr($type, 0, -2);

        foreach ($data as $item) {
            return $this->denormalizer->supportsDenormalization($item, $itemType, $format, $context);
        }

        return true;
    }

    /**
     * @param list<string> $typeIdentifiers
     */
    private function validateKeyType(array $typeIdentifiers, mixed $key, string $path): void
    {
        if (!$typeIdentifiers) {
            return;
        }

        foreach ($typeIdentifiers as $typeIdentifier) {
            if (('is_'.$typeIdentifier)($key)) {
                return;
            }
        }

        throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('The type of the key "%s" must be "%s" ("%s" given).', $key, implode('", "', $typeIdentifiers), get_debug_type($key)), $key, $typeIdentifiers, $path, true);
    }
}
