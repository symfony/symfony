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
use Symfony\Component\TypeInfo\Type;

/**
 * Transforms BcMath number to string during stream writing.
 *
 * Does nothing if the native value is not a valid object.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class BcMathNumberToStringValueTransformer implements ValueTransformerInterface
{
    public function transform(mixed $value, array $options = []): mixed
    {
        if (!$value instanceof Number) {
            return $value;
        }

        return (string) $value;
    }

    public static function getStreamValueType(): Type
    {
        return Type::string();
    }
}
