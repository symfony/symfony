<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Model;

use Symfony\Component\JsonEncoder\Attribute\Denormalizer;
use Symfony\Component\JsonEncoder\Attribute\Normalizer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Attribute\BooleanStringNormalizer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Attribute\BooleanStringDenormalizer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Denormalizer\DivideStringAndCastToIntDenormalizer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Normalizer\DoubleIntAndCastToStringNormalizer;

class DummyWithNormalizerAttributes
{
    #[Normalizer(DoubleIntAndCastToStringNormalizer::class)]
    #[Denormalizer(DivideStringAndCastToIntDenormalizer::class)]
    public int $id = 1;

    #[BooleanStringNormalizer]
    #[BooleanStringDenormalizer]
    public bool $active = false;

    #[Normalizer('strtolower')]
    #[Denormalizer('strtoupper')]
    public string $name = 'DUMMY';

    #[Normalizer([self::class, 'concatRange'])]
    #[Denormalizer([self::class, 'explodeRange'])]
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
