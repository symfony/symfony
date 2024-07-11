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
use Symfony\Component\TypeInfo\Type\UnionType;

use Symfony\Component\PropertyInfo\Util\PhpDocTypeHelper;

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
        $typeResolver = new \phpDocumentor\Reflection\TypeResolver();
        $result = $typeResolver->resolve($type);

        if (null === $this->denormalizer) {
            throw new BadMethodCallException('Please set a denormalizer before calling denormalize()!');
        }
        if (!\is_array($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('Data expected to be "%s", "%s" given.', $type, get_debug_type($data)), $data, ['array'], $context['deserialization_path'] ?? null);
        }

        if (!$result instanceof \phpDocumentor\Reflection\Types\AbstractList) {
            throw new InvalidArgumentException('Unsupported class: '.$type);
        }


        $type = (string) $result->getValueType();

        $typeIdentifiers = [];
        $keyTYye = $result->getKeyType();
        if ($keyType == null) {
            // Overwrite if context provides keyType
            $keyType = $context['key_type'] ?? null;
        }
        if (null !== $keyType) {
            if ($keyType instanceof Type) {
                $typeIdentifiers = array_map(fn (Type $t): string => $t->getBaseType()->getTypeIdentifier()->value, $keyType instanceof UnionType ? $keyType->getTypes() : [$keyType]);
            } else {
                $typeIdentifiers = array_map(fn (LegacyType $t): string => $t->getBuiltinType(), \is_array($keyType) ? $keyType : [$keyType]);
            }
        }


        foreach ($data as $key => $value) {
            $subContext = $context;
            $subContext['deserialization_path'] = ($context['deserialization_path'] ?? false) ? \sprintf('%s[%s]', $context['deserialization_path'], $key) : "[$key]";

            $this->validateKeyType($typeIdentifiers, $key, $subContext['deserialization_path']);

            $data[$key] = $this->denormalizer->denormalize($value, $type, $format, $subContext);
        }

        return $data;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if (null === $this->denormalizer) {
            throw new BadMethodCallException(\sprintf('The nested denormalizer needs to be set to allow "%s()" to be used.', __METHOD__));
        }

        $typeResolver = new \phpDocumentor\Reflection\TypeResolver();
        $result = $typeResolver->resolve($type);


        return $result instanceof \phpDocumentor\Reflection\Types\AbstractList
            && $this->denormalizer->supportsDenormalization($data, (string) $result->getValueType(), $format, $context);
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
