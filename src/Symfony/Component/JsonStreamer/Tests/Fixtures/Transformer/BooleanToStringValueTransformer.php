<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer;

use Symfony\Component\JsonStreamer\Transformer\PropertyValueTransformerInterface;
use Symfony\Component\TypeInfo\Type;

final class BooleanToStringValueTransformer implements PropertyValueTransformerInterface
{
    public function transform(mixed $value, array $options = []): mixed
    {
        return $value ? 'true' : 'false';
    }

    public static function getStreamValueType(): Type
    {
        return Type::string();
    }
}
