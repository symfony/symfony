<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Model;

use Symfony\Component\JsonEncoder\Attribute\DecodeFormatter;
use Symfony\Component\JsonEncoder\Attribute\EncodeFormatter;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Attribute\BooleanStringEncodeFormatter;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Attribute\BooleanStringDecodeFormatter;

class DummyWithFormatterAttributes
{
    #[EncodeFormatter([self::class, 'doubleAndCastToString'])]
    #[DecodeFormatter([self::class, 'divideAndCastToInt'])]
    public int $id = 1;

    #[EncodeFormatter('strtoupper')]
    #[DecodeFormatter('strtolower')]
    public string $name = 'dummy';

    #[BooleanStringEncodeFormatter]
    #[BooleanStringDecodeFormatter]
    public bool $active = false;

    public static function doubleAndCastToString(int $value): string
    {
        return (string) (2 * $value);
    }

    public static function divideAndCastToInt(string $value): int
    {
        return (int) (((int) $value) / 2);
    }

    public static function doubleAndCastToStringWithConfig(int $value, array $config): string
    {
        return (string) (2 * $config['scale'] * $value);
    }

    public static function divideAndCastToIntWithConfig(string $value, array $config): int
    {
        return (int) (((int) $value) / (2 * $config['scale']));
    }
}
