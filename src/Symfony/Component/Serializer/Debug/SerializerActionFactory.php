<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Debug;

use Symfony\Component\Serializer\Debug\Normalizer\Denormalization;
use Symfony\Component\Serializer\Debug\Normalizer\Normalization;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class SerializerActionFactory implements SerializerActionFactoryInterface
{
    public function createDenormalization(DenormalizerInterface $denormalizer, $data, $result, string $type, string $format, array $context = []): Denormalization
    {
        return new Denormalization(
            $denormalizer,
            $this->sanitize($data),
            $this->sanitize($result),
            $type,
            $format,
            $context
        );
    }

    public function createNormalization(NormalizerInterface $normalizer, $object, $result, string $format, array $context = []): Normalization
    {
        return new Normalization(
            $normalizer,
            $this->sanitize($object),
            $this->sanitize($result),
            $format,
            $context
        );
    }

    public function createSerialization($data, string $result, string $format, array $context = []): Serialization
    {
        return new Serialization(
            $this->sanitize($data),
            $this->sanitize($result),
            $format,
            $context
        );
    }

    public function createDeserialization(string $data, $result, string $type, string $format, array $context = []): Deserialization
    {
        return new Deserialization(
            $this->sanitize($data),
            $this->sanitize($result),
            $type,
            $format,
            $context
        );
    }

    /**
     * @param mixed $data
     *
     * @return mixed|string|LargeContent
     */
    private function sanitize($data)
    {
        if ($this->getVarSize($data) > LargeContent::LIMIT_BYTES) {
            $data = $this->markAsHugeContent($data);
        }

        return $data;
    }

    /**
     * @param mixed $data
     *
     * @return string|LargeContent
     */
    private function markAsHugeContent($data)
    {
        $output = new LargeContent();
        if (\is_string($data)) {
            return (string) $output;
        }

        return $output;
    }

    private function getVarSize($data): int
    {
        return mb_strlen(serialize($data), 'UTF-8') ?? 0;
    }
}
