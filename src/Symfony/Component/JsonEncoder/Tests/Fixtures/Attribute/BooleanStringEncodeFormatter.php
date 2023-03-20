<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Attribute;

use Symfony\Component\JsonEncoder\Attribute\EncodeFormatter;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class BooleanStringEncodeFormatter extends EncodeFormatter
{
    public function __construct()
    {
        parent::__construct($this::toString(...));
    }

    public static function toString(bool $bool): string
    {
        return $bool ? 'true' : 'false';
    }
}
