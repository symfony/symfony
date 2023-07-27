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
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

/**
 * Denormalizes arrays of objects.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @final
 */
class ArrayDenormalizer implements ContextAwareDenormalizerInterface, DenormalizerAwareInterface, CacheableSupportsMethodInterface
{
    use DenormalizerAwareTrait;

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): array
    {
        if (null === $this->denormalizer) {
            throw new BadMethodCallException('Please set a denormalizer before calling denormalize()!');
        }
        if (!\is_array($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('Data expected to be "%s", "%s" given.', $type, get_debug_type($data)), $data, [Type::BUILTIN_TYPE_ARRAY], $context['deserialization_path'] ?? null);
        }
        if (!str_ends_with($type, '[]')) {
            throw new InvalidArgumentException('Unsupported class: '.$type);
        }

        $type = substr($type, 0, -2);

        $builtinTypes = array_map(static function (Type $keyType) {
            return $keyType->getBuiltinType();
        }, \is_array($keyType = $context['key_type'] ?? []) ? $keyType : [$keyType]);

        foreach ($data as $key => $value) {
            $subContext = $context;
            $subContext['deserialization_path'] = ($context['deserialization_path'] ?? false) ? sprintf('%s[%s]', $context['deserialization_path'], $key) : "[$key]";

            $this->validateKeyType($builtinTypes, $key, $subContext['deserialization_path']);

            $data[$key] = $this->denormalizer->denormalize($value, $type, $format, $subContext);
        }

        return $data;
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        if (null === $this->denormalizer) {
            throw new BadMethodCallException(sprintf('The nested denormalizer needs to be set to allow "%s()" to be used.', __METHOD__));
        }

        return str_ends_with($type, '[]')
            && $this->denormalizer->supportsDenormalization($data, substr($type, 0, -2), $format, $context);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return $this->denormalizer instanceof CacheableSupportsMethodInterface && $this->denormalizer->hasCacheableSupportsMethod();
    }

    /**
     * @param mixed $key
     */
    private function validateKeyType(array $builtinTypes, $key, string $path): void
    {
        if (!$builtinTypes) {
            return;
        }

        foreach ($builtinTypes as $builtinType) {
            if (('is_'.$builtinType)($key)) {
                return;
            }
        }

        throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('The type of the key "%s" must be "%s" ("%s" given).', $key, implode('", "', $builtinTypes), get_debug_type($key)), $key, $builtinTypes, $path, true);
    }
}
