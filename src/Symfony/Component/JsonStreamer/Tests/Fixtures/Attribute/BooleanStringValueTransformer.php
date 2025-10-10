<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Attribute;

use Symfony\Component\JsonStreamer\Attribute\ValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\BooleanToStringValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\StringToBooleanValueTransformer;

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
