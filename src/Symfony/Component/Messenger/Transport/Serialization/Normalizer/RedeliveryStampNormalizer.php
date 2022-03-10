<?php

namespace Symfony\Component\Messenger\Transport\Serialization\Normalizer;

use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class RedeliveryStampNormalizer implements DenormalizerInterface
{
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        return new RedeliveryStamp(
            $data['retryCount'] ?? 0,
            $data['redeliveredAt'] ?? null
        );
    }

    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        return RedeliveryStamp::class === $type
            && null === ($data['exceptionMessage'] ?? null)
            && null === ($data['flattenException'] ?? null)
        ;
    }
}
