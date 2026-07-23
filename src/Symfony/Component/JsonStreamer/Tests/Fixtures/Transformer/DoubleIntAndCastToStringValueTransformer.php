<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer;

use Symfony\Component\JsonStreamer\Transformer\PropertyValueTransformerInterface;
use Symfony\Component\TypeInfo\Type;

final class DoubleIntAndCastToStringValueTransformer implements PropertyValueTransformerInterface
{
    public function transform(mixed $value, array $options = []): mixed
    {
        return (string) (2 * $options['scale'] * $value);
    }

    public static function getStreamValueType(): Type
    {
        return Type::string();
    }
}
