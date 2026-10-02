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
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\FailedMessageStamp;
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
 * Normalizes the messages held by the ChainStamp, DispatchOnFailureStamp and FailedMessageStamp of an envelope.
 *
 * Each message is normalized with its class, and when it is wrapped in an envelope, with the stamps of that envelope and their classes.
 */
final class MessageStampNormalizer implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    public function getSupportedTypes(?string $format): array
    {
        return [
            ChainStamp::class => false,
            DispatchOnFailureStamp::class => false,
            FailedMessageStamp::class => false,
        ];
    }

    /**
     * @param ChainStamp|DispatchOnFailureStamp|FailedMessageStamp $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof ChainStamp) {
            return $this->normalizeMessage($data->getMessage(), $format, $context);
        }

        $messages = [];
        foreach ($data->getMessages() as $message) {
            $messages[] = $this->normalizeMessage($message, $format, $context);
        }

        return ['messages' => $messages];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return ($data instanceof ChainStamp || $data instanceof DispatchOnFailureStamp || $data instanceof FailedMessageStamp) && ($context[Serializer::MESSENGER_SERIALIZATION_CONTEXT] ?? false);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ChainStamp|DispatchOnFailureStamp|FailedMessageStamp
    {
        if (ChainStamp::class === $type) {
            $messages = [];
            foreach (self::toList($data['messages'] ?? null, $type, $format) as $message) {
                $messages[] = $this->denormalizeMessage($message, $type, $format, $context);
            }

            return new ChainStamp(...$messages);
        }

        $message = $this->denormalizeMessage($data, $type, $format, $context);

        return DispatchOnFailureStamp::class === $type ? new DispatchOnFailureStamp($message) : new FailedMessageStamp($message);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return \in_array($type, [ChainStamp::class, DispatchOnFailureStamp::class, FailedMessageStamp::class], true) && ($context[Serializer::MESSENGER_SERIALIZATION_CONTEXT] ?? false);
    }

    private function normalizeMessage(object $message, ?string $format, array $context): array
    {
        $envelope = Envelope::wrap($message)->withoutStampsOfType(NonSendableStampInterface::class);
        $stamps = [];
        foreach ($envelope->all() as $class => $classStamps) {
            foreach ($classStamps as $stamp) {
                $stamps[] = ['type' => $class, 'stamp' => $this->normalizer->normalize($stamp, $format, $context)];
            }
        }

        return [
            'type' => $envelope->getMessage()::class,
            'message' => $this->normalizer->normalize($envelope->getMessage(), $format, $context),
            'stamps' => $stamps,
        ];
    }

    /**
     * @param class-string $type The class of the stamp holding the message
     */
    private function denormalizeMessage(mixed $data, string $type, ?string $format, array $context): object
    {
        if (!\is_array($data) || !\is_string($class = $data['type'] ?? null) || !class_exists($class) || !\array_key_exists('message', $data)) {
            throw new NotNormalizableValueException(\sprintf('The data is not a valid "%s" representation.', $type));
        }

        $stampsByClass = [];
        foreach (self::toList($data['stamps'] ?? [], $type, $format) as $stamp) {
            if (!\is_string($stampClass = $stamp['type'] ?? null) || !\array_key_exists('stamp', $stamp)) {
                throw new NotNormalizableValueException(\sprintf('The data is not a valid "%s" representation.', $type));
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

        $message = $this->denormalizer->denormalize($data['message'], $class, $format, $context);

        return $stamps ? new Envelope($message, $stamps) : $message;
    }

    /**
     * The XML encoder decodes a list of one item as the item itself, and an empty list as an empty string.
     *
     * @param class-string $type The class of the stamp holding the list
     */
    private static function toList(mixed $data, string $type, ?string $format): array
    {
        if (!\is_array($data)) {
            return XmlEncoder::FORMAT === $format && '' === $data ? [] : throw new NotNormalizableValueException(\sprintf('The data is not a valid "%s" representation.', $type));
        }

        return XmlEncoder::FORMAT !== $format || array_is_list($data) ? $data : [$data];
    }
}
