<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Messenger\Serializer\Normalizer;

use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class SchedulerTriggerNormalizer implements DenormalizerInterface, NormalizerInterface
{
    public function getSupportedTypes(?string $format): array
    {
        return [
            TriggerInterface::class => false,
        ];
    }

    /**
     * @param TriggerInterface $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): string
    {
        return (string) $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof TriggerInterface && ($context[Serializer::MESSENGER_SERIALIZATION_CONTEXT] ?? false);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): TriggerInterface
    {
        return new class($data) implements TriggerInterface {
            public function __construct(private readonly string $description)
            {
            }

            public function __toString(): string
            {
                return $this->description;
            }

            public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
            {
                throw new \LogicException('Not possible to get next run date from a deserialized trigger.');
            }
        };
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return TriggerInterface::class === $type && ($context[Serializer::MESSENGER_SERIALIZATION_CONTEXT] ?? false) && \is_string($data);
    }
}
