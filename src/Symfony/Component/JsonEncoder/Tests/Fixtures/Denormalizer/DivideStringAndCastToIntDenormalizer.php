<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Denormalizer;

use Symfony\Component\JsonEncoder\Decode\Denormalizer\DenormalizerInterface;
use Symfony\Component\TypeInfo\Type;

final class DivideStringAndCastToIntDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, array $options = []): mixed
    {
        return (int) (((int) $data) / (2 * $options['scale']));
    }

    public static function getNormalizedType(): Type
    {
        return Type::string();
    }
}
