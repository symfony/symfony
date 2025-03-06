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

use Symfony\Component\TypeInfo\Type;

/**
 * Transforms GMP number to string during stream writing.
 *
 * Does nothing if the native value is not a valid object.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class GmpNumberToStringValueTransformer implements ValueTransformerInterface
{
    public function transform(mixed $value, array $options = []): mixed
    {
        if (!$value instanceof \GMP) {
            return $value;
        }

        return gmp_strval($value);
    }

    public static function getStreamValueType(): Type
    {
        return Type::string();
    }
}
