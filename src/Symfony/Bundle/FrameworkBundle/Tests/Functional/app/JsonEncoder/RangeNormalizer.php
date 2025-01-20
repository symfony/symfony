<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonEncoder;

use Symfony\Component\JsonEncoder\Decode\Denormalizer\DenormalizerInterface;
use Symfony\Component\JsonEncoder\Encode\Normalizer\NormalizerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class RangeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function normalize(mixed $denormalized, array $options = []): string
    {
        return $denormalized[0].'..'.$denormalized[1];
    }

    public function denormalize(mixed $normalized, array $options = []): array
    {
        return array_map(static fn (string $v): int => (int) $v, explode('..', $normalized));
    }

    public static function getNormalizedType(): BuiltinType
    {
        return Type::string();
    }
}
