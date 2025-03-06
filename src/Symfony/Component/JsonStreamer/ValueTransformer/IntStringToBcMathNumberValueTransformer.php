<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\ValueTransformer;

use BcMath\Number;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;

/**
 * Transforms int or string to BcMath number during stream reading.
 *
 * Does nothing if the stream value type is not valid.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class IntStringToBcMathNumberValueTransformer implements ValueTransformerInterface
{
    public function transform(mixed $value, array $options = []): mixed
    {
        if (!\is_string($value) && !\is_int($value)) {
            return $value;
        }

        try {
            return new Number($value);
        } catch (\ValueError $e) {
            throw new InvalidArgumentException(\sprintf('Unable to create a "%s"; the stream value must a parsable string or an int.', Number::class), $e->getCode(), $e);
        }
    }

    public static function getStreamValueType(): Type
    {
        return Type::union(Type::int(), Type::string());
    }
}
