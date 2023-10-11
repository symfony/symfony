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
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\UnionType;

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

    public function setDenormalizer(DenormalizerInterface $denormalizer): void
    {
        $this->denormalizer = $denormalizer;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => null, '*' => false];
    }

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): array
    {
        if (null === $this->denormalizer) {
            throw new BadMethodCallException('Please set a denormalizer before calling denormalize()!');
        }
        if (!\is_array($data)) {
            $valueType = $context['value_type'] ?? null;
            $expected = $valueType ? 'array<'.implode('|', array_map(fn (Type $type) => $type->getClassName() ?? $type->getBuiltinType(), $valueType->getCollectionValueTypes())).'>' : $type;

            throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('Data expected to be "%s", "%s" given.', $expected, get_debug_type($data)), $data, ['array'], $context['deserialization_path'] ?? null);
        }
        if (!str_ends_with($type, '[]')) {
            throw new InvalidArgumentException('Unsupported class: '.$type);
        }

        $type = substr($type, 0, -2);
        $valueType = $context['value_type'] ?? null;

        # todo
        $typeIdentifiers = [];
        if (null !== $keyType = ($context['key_type'] ?? null)) {
            if ($keyType instanceof Type) {
                $typeIdentifiers = array_map(fn (Type $t): string => $t->getBaseType()->getTypeIdentifier()->value, $keyType instanceof UnionType ? $keyType->getTypes() : [$keyType]);
            } else {
                $typeIdentifiers = array_map(fn (LegacyType $t): string => $t->getBuiltinType(), \is_array($keyType) ? $keyType : [$keyType]);
            }
        }

        if ($valueType instanceof Type && \count($keyTypes = $valueType->getCollectionKeyTypes()) > 0) {
            $builtinTypes = array_map(static fn (Type $keyType) => $keyType->getBuiltinType(), $keyTypes);
        } else {
            $builtinTypes = array_map(static fn (LegacyType $keyType) => $keyType->getBuiltinType(), \is_array($keyType = $context['key_type'] ?? []) ? $keyType : [$keyType]);
        }

        foreach ($data as $key => $value) {
            $subContext = $context;
            $subContext['deserialization_path'] = ($context['deserialization_path'] ?? false) ? sprintf('%s[%s]', $context['deserialization_path'], $key) : "[$key]";

            $this->validateKeyType($typeIdentifiers, $key, $subContext['deserialization_path']);

            if ($valueType instanceof Type) {
                foreach ($valueType->getCollectionValueTypes() as $subtype) {
                    try {
                        $subContext['value_type'] = $subtype;

                        if ($subtype->isNullable() && null === $value) {
                            $data[$key] = null;

                            continue 2;
                        }

                        if (Type::BUILTIN_TYPE_ARRAY === $subtype->getBuiltinType()) {
                            $class = $type;
                        } else {
                            $class = $subtype->getClassName() ?? $subtype->getBuiltinType();
                        }

                        $data[$key] = $this->denormalizer->denormalize($value, $class, $format, $subContext);

                        continue 2;
                    } catch (NotNormalizableValueException|InvalidArgumentException|ExtraAttributesException|MissingConstructorArgumentsException $e) {
                    }
                }

                throw $e;
            } else {
                $data[$key] = $this->denormalizer->denormalize($value, $type, $format, $subContext);
            }
        }

        return $data;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if (null === $this->denormalizer) {
            throw new BadMethodCallException(sprintf('The nested denormalizer needs to be set to allow "%s()" to be used.', __METHOD__));
        }

        return str_ends_with($type, '[]')
            && $this->denormalizer->supportsDenormalization($data, substr($type, 0, -2), $format, $context);
    }

    /**
     * @param list<string> $typeIdentifiers
     */
    private function validateKeyType(array $builtinTypes, mixed $key, string $path): void
    {
        if (!$typeIdentifiers) {
            return;
        }

        foreach ($typeIdentifiers as $typeIdentifier) {
            if (('is_'.$typeIdentifier)($key)) {
                return;
            }
        }

        throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('The type of the key "%s" must be "%s" ("%s" given).', $key, implode('", "', $typeIdentifiers), get_debug_type($key)), $key, $typeIdentifiers, $path, true);
    }
}
