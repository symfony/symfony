<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Attribute;

use Symfony\Component\JsonStreamer\Attribute\ValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer\BooleanToStringValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer\StringToBooleanValueTransformer;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class BooleanStringValueTransformer extends ValueTransformer
{
    public function __construct()
    {
        parent::__construct(
            nativeToStream: BooleanToStringValueTransformer::class,
            streamToNative: StringToBooleanValueTransformer::class,
        );
    }
}
