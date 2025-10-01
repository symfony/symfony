<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Attribute\ValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Attribute\BooleanStringValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\DivideStringAndCastToIntValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\DoubleIntAndCastToStringValueTransformer;

class DummyWithValueTransformerAttributes
{
    #[ValueTransformer(
        nativeToStream: DoubleIntAndCastToStringValueTransformer::class,
        streamToNative: DivideStringAndCastToIntValueTransformer::class,
    )]
    public int $id = 1;

    #[BooleanStringValueTransformer]
    public bool $active = false;

    #[ValueTransformer(nativeToStream: 'strtolower', streamToNative: 'strtoupper')]
    public string $name = 'DUMMY';

    #[ValueTransformer(
        nativeToStream: [self::class, 'concatRange'],
        streamToNative: [self::class, 'explodeRange'],
    )]
    public array $range = [10, 20];

    /**
     * @param array{0: int, 1: int} $range
     */
    public static function concatRange(array $range): string
    {
        return $range[0].'..'.$range[1];
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function explodeRange(string $range): array
    {
        return array_map(static fn (string $v): int => (int) $v, explode('..', $range));
    }
}
