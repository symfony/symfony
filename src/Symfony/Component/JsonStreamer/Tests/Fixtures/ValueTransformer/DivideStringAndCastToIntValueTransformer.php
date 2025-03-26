<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer;

use Symfony\Component\JsonStreamer\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\TypeInfo\Type;

final class DivideStringAndCastToIntValueTransformer implements ValueTransformerInterface
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
