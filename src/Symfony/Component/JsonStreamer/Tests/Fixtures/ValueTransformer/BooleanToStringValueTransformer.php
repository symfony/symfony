<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer;

use Symfony\Component\JsonStreamer\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\TypeInfo\Type;

final class BooleanToStringValueTransformer implements ValueTransformerInterface
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
