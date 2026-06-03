<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer;

use Symfony\Component\JsonStreamer\Transformer\PropertyValueTransformerInterface;
use Symfony\Component\TypeInfo\Type;

final class DivideStringAndCastToIntValueTransformer implements PropertyValueTransformerInterface
{
    public function transform(mixed $value, array $options = []): mixed
    {
        return (int) (((int) $value) / (2 * $options['scale']));
    }

    public static function getStreamValueType(): Type
    {
        return Type::string();
    }
}
