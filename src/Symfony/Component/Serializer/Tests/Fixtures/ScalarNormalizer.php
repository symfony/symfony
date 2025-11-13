<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ScalarNormalizer implements NormalizerInterface
{
    public function normalize(mixed $object, ?string $format = null, array $context = []): string
    {
        $data = $object;

        if (!\is_string($data)) {
            $data = (string) $object;
        }

        return strtoupper($data);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return \is_scalar($data);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            'native-boolean' => true,
            'native-integer' => true,
            'native-string' => true,
        ];
    }
}
