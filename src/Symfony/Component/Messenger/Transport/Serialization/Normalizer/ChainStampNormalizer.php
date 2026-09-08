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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ChainStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\ValidationStamp;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes each message of a ChainStamp with its class, and the stamps of its envelope with theirs.
 */
final class ChainStampNormalizer implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    public function getSupportedTypes(?string $format): array
    {
        return [
            ChainStamp::class => false,
        ];
    }

    /**
     * @param ChainStamp $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $messages = [];
        foreach ($data->getMessages() as $message) {
            $envelope = Envelope::wrap($message)->withoutStampsOfType(NonSendableStampInterface::class);
            $stamps = [];
            foreach ($envelope->all() as $class => $classStamps) {
                foreach ($classStamps as $stamp) {
                    $stamps[] = ['type' => $class, 'stamp' => $this->normalizer->normalize($stamp, $format, $context)];
                }
            }

            $messages[] = [
                'type' => $envelope->getMessage()::class,
                'message' => $this->normalizer->normalize($envelope->getMessage(), $format, $context),
                'stamps' => $stamps,
            ];
        }

        return ['messages' => $messages];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ChainStamp && ($context[Serializer::MESSENGER_SERIALIZATION_CONTEXT] ?? false);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ChainStamp
    {
        $messages = [];
        foreach (self::toList($data['messages'] ?? null, $format) as $message) {
            if (!\is_string($class = $message['type'] ?? null) || !class_exists($class) || !\array_key_exists('message', $message)) {
                throw new NotNormalizableValueException(\sprintf('The data is not a valid "%s" representation.', ChainStamp::class));
            }

            $stampsByClass = [];
            foreach (self::toList($message['stamps'] ?? [], $format) as $stamp) {
                if (!\is_string($stampClass = $stamp['type'] ?? null) || !\array_key_exists('stamp', $stamp)) {
                    throw new NotNormalizableValueException(\sprintf('The data is not a valid "%s" representation.', ChainStamp::class));
                }

                $stampsByClass[$stampClass][] = $stamp['stamp'];
            }

            $failure = null;
            $stamps = Serializer::decodeStampValues($stampsByClass, fn (string $stampClass, array $list): array => ValidationStamp::class === $stampClass && XmlEncoder::FORMAT === $format
                ? Serializer::denormalizeXmlValidationStamps($list, $this->denormalizer, $context)
                : $this->denormalizer->denormalize($list, $stampClass.'[]', $format, $context), $failure);

            if (null !== $failure) {
                throw $failure;
            }

            $message = $this->denormalizer->denormalize($message['message'], $class, $format, $context);
            $messages[] = $stamps ? new Envelope($message, $stamps) : $message;
        }

        return new ChainStamp(...$messages);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return ChainStamp::class === $type && ($context[Serializer::MESSENGER_SERIALIZATION_CONTEXT] ?? false);
    }

    /**
     * The XML encoder decodes a list of one item as the item itself, and an empty list as an empty string.
     */
    private static function toList(mixed $data, ?string $format): array
    {
        if (!\is_array($data)) {
            return XmlEncoder::FORMAT === $format && '' === $data ? [] : throw new NotNormalizableValueException(\sprintf('The data is not a valid "%s" representation.', ChainStamp::class));
        }

        return XmlEncoder::FORMAT !== $format || array_is_list($data) ? $data : [$data];
    }
}
