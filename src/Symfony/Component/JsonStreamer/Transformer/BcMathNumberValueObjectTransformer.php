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

use BcMath\Number;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Transforms a {@see Number} to a string and vice versa.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @implements ValueObjectTransformerInterface<Number, string>
 */
final class BcMathNumberValueObjectTransformer implements ValueObjectTransformerInterface
{
    public function transform(object $object, array $options = []): string
    {
        if (!$object instanceof Number) {
            throw new InvalidArgumentException(\sprintf('The native value must be an instance of "%s".', Number::class));
        }

        return (string) $object;
    }

    /**
     * @return Number
     */
    public function reverseTransform(int|float|string|bool|null $scalar, array $options = []): object
    {
        if (!\is_string($scalar) && !\is_int($scalar)) {
            throw new InvalidArgumentException('The JSON value must be a string or an integer.');
        }

        try {
            return new Number($scalar);
        } catch (\ValueError $e) {
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
        return Number::class;
    }
}
