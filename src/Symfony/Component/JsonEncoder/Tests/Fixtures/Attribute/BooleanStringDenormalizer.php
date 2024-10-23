<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Attribute;

use Symfony\Component\JsonEncoder\Attribute\Denormalizer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Denormalizer\BooleanStringDenormalizer as BooleanStringDenormalizerService;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class BooleanStringDenormalizer extends Denormalizer
{
    public function __construct()
    {
        parent::__construct(BooleanStringDenormalizerService::class);
    }
}
