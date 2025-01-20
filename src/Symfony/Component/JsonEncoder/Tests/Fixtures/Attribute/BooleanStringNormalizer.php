<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Attribute;

use Symfony\Component\JsonEncoder\Attribute\Normalizer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Normalizer\BooleanStringNormalizer as BooleanStringNormalizerService;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class BooleanStringNormalizer extends Normalizer
{
    public function __construct()
    {
        parent::__construct(BooleanStringNormalizerService::class);
    }
}
