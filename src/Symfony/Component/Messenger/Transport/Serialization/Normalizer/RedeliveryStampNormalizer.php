<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Serialization\Normalizer;

use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class RedeliveryStampNormalizer implements DenormalizerInterface
{
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        $redeliveredAt = $data['redeliveredAt'] ?? null;

        return new RedeliveryStamp(
            $data['retryCount'] ?? 0,
            $redeliveredAt ? new \DateTimeImmutable($redeliveredAt) : null
        );
    }

    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        return RedeliveryStamp::class === $type
            && null === ($data['exceptionMessage'] ?? null)
            && null === ($data['flattenException'] ?? null)
        ;
    }

    public function normalize($object, string $format = null, array $context = [])
    {
        if (! $object instanceof RedeliveryStamp) {
            throw new InvalidArgumentException();
        }

        $dateTimeFormat = $context[DateTimeNormalizer::FORMAT_KEY] ?? \DateTimeInterface::RFC3339;

        return [
            'retryCount' => $object->getRetryCount(),
            'redeliveredAt' => $object->getRedeliveredAt()->format($dateTimeFormat),
        ];
    }

    public function supportsNormalization($data, string $format = null): bool
    {
        return Kernel::VERSION_ID >= 50200
            && $data instanceof RedeliveryStamp
        ;
    }
}
