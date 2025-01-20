<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Normalizer;

use Symfony\Component\JsonEncoder\Encode\Normalizer\NormalizerInterface;
use Symfony\Component\TypeInfo\Type;

final class BooleanStringNormalizer implements NormalizerInterface
{
    public function normalize(mixed $data, array $options = []): mixed
    {
        return $data ? 'true' : 'false';
    }

    public static function getNormalizedType(): Type
    {
        return Type::string();
    }
}
