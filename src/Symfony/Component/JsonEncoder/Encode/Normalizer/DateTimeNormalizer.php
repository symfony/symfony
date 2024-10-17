<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Encode\Normalizer;

use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Casts DateTimeInterface to string.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class DateTimeNormalizer implements NormalizerInterface
{
    public const FORMAT_KEY = 'date_time_format';

    public function normalize(mixed $denormalized, array $options = []): string
    {
        if (!$denormalized instanceof \DateTimeInterface) {
            throw new InvalidArgumentException('The denormalized data must implement the "\DateTimeInterface".');
        }

        return $denormalized->format($options[self::FORMAT_KEY] ?? \DateTimeInterface::RFC3339);
    }

    /**
     * @return BuiltinType<TypeIdentifier::STRING>
     */
    public static function getNormalizedType(): BuiltinType
    {
        return Type::string();
    }
}
