<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Serializer;

use Symfony\Component\Semaphore\Key;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalize {@see Key} instances for transferring between processes.
 *
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
class SemaphoreKeyNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @return array<string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [Key::class => true];
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return $data->__serialize();
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Key;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Key
    {
        $key = (new \ReflectionClass(Key::class))->newInstanceWithoutConstructor();
        $key->__unserialize($data);

        return $key;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Key::class === $type;
    }
}
