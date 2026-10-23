<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Attribute\ValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum;

class DummyWithBackedEnumValueTransformer
{
    #[ValueTransformer(streamToNative: [self::class, 'enumToName'])]
    public string $enum = 'DEFAULT';

    #[ValueTransformer(streamToNative: [self::class, 'nullableEnumToName'])]
    public ?string $nullableEnum = 'DEFAULT';

    #[ValueTransformer(streamToNative: [self::class, 'enumToName'])]
    public string $requiredEnum;

    public static function enumToName(DummyBackedEnum $enum): string
    {
        return $enum->name;
    }

    public static function nullableEnumToName(?DummyBackedEnum $enum): ?string
    {
        return $enum?->name;
    }
}
