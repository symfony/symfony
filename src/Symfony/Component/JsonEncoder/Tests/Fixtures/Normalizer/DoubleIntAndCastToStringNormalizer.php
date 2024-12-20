<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Normalizer;

use Symfony\Component\JsonEncoder\Encode\Normalizer\NormalizerInterface;
use Symfony\Component\TypeInfo\Type;

final class DoubleIntAndCastToStringNormalizer implements NormalizerInterface
{
    public function normalize(mixed $data, object $object, array $options = []): mixed
    {
        return (string) (2 * $options['scale'] * $data);
    }

    public static function getNormalizedType(): Type
    {
        return Type::string();
    }
}
