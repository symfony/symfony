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
}
