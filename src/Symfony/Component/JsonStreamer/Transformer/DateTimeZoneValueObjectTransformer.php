<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Transformer;

use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Transforms a {@see \DateTimeZone} to a timezone string and vice versa.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @implements ValueObjectTransformerInterface<\DateTimeZone, string>
 */
final class DateTimeZoneValueObjectTransformer implements ValueObjectTransformerInterface
{
    public function transform(object $object, array $options = []): string
    {
        if (!$object instanceof \DateTimeZone) {
            throw new InvalidArgumentException('The native value must be an instance of "\DateTimeZone".');
        }

        return $object->getName();
    }

    public function reverseTransform(int|float|string|bool|null $scalar, array $options = []): \DateTimeZone
    {
        if (!\is_string($scalar)) {
            throw new InvalidArgumentException(\sprintf('The JSON value must be a string, "%s" given.', get_debug_type($scalar)));
        }

        try {
            return new \DateTimeZone($scalar);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return BuiltinType<TypeIdentifier::STRING>
     */
    public static function getStreamValueType(): BuiltinType
    {
        return Type::string();
    }

    public static function getValueObjectClassName(): string
    {
        return \DateTimeZone::class;
    }
}
