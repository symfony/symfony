<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Attribute;

use Symfony\Component\JsonEncoder\Attribute\DecodeFormatter;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class BooleanStringDecodeFormatter extends DecodeFormatter
{
    public function __construct()
    {
        parent::__construct($this::toBool(...));
    }

    public static function toBool(string $string): bool
    {
        return 'true' === $string;
    }
}
