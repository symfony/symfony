<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Serialization;

use Symfony\Component\Lock\Serializer\LockKeyNormalizer;
use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class Serializer implements SerializerInterface
{
    public const MESSENGER_SERIALIZATION_CONTEXT = 'messenger_serialization';
    private const STAMP_HEADER_PREFIX = 'X-Message-Stamp-';

    private SymfonySerializerInterface $serializer;

    /**
     * @var array<string-class, string>
     */
    private array $classToTypeMap = [];

    /**
     * @pram array<string, class-string> $typeToClassMap
     */
    public function __construct(
        ?SymfonySerializerInterface $serializer = null,
        private string $format = 'json',
        private array $context = [],
        private array $typeToClassMap = [],
    ) {
        $this->serializer = $serializer ?? self::create()->serializer;
        $this->context += [self::MESSENGER_SERIALIZATION_CONTEXT => true];
        $this->classToTypeMap = array_flip($this->typeToClassMap);
    }

    public static function create(): self
    {
        if (!class_exists(SymfonySerializer::class)) {
            throw new LogicException(\sprintf('The "%s" class requires Symfony\'s Serializer component. Try running "composer require symfony/serializer" or use "%s" instead.', __CLASS__, PhpSerializer::class));
        }

        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [
            new DateTimeNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(propertyTypeExtractor: new ReflectionExtractor()),
        ];
        if (class_exists(LockKeyNormalizer::class)) {
            array_unshift($normalizers, new LockKeyNormalizer());
        }

        $serializer = new SymfonySerializer($normalizers, $encoders);

        return new self($serializer);
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        if (empty($encodedEnvelope['body']) || empty($encodedEnvelope['headers'])) {
            throw new MessageDecodingFailedException('Encoded envelope should have at least a "body" and some "headers", or maybe you should implement your own serializer.');
        }

        if (empty($encodedEnvelope['headers']['type'])) {
            throw new MessageDecodingFailedException('Encoded envelope does not have a "type" header.');
        }

        $stamps = $this->decodeStamps($encodedEnvelope);
        $stamps[] = new SerializedMessageStamp($encodedEnvelope['body']);

        $serializerStamp = $this->findFirstSerializerStamp($stamps);

        $context = $this->context;
        if (null !== $serializerStamp) {
            $context = $serializerStamp->getContext() + $context;
        }

        $type = $encodedEnvelope['headers']['type'];
        $type = $this->typeToClassMap[$type] ?? $type;

        try {
            $message = $this->serializer->deserialize($encodedEnvelope['body'], $type, $this->format, $context);
        } catch (ExceptionInterface $e) {
            throw new MessageDecodingFailedException('Could not decode message: '.$e->getMessage(), $e->getCode(), $e);
        }

        return new Envelope($message, $stamps);
    }

    public function encode(Envelope $envelope): array
    {
        $context = $this->context;
        /** @var SerializerStamp|null $serializerStamp */
        if ($serializerStamp = $envelope->last(SerializerStamp::class)) {
            $context = $serializerStamp->getContext() + $context;
        }

        /** @var SerializedMessageStamp|null $serializedMessageStamp */
        $serializedMessageStamp = $envelope->last(SerializedMessageStamp::class);

        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

        $headers = [
            'type' => $this->getTypeFromEnvelope($envelope),
            ...$this->encodeStamps($envelope),
            ...$this->getContentTypeHeader(),
        ];

        return [
            'body' => $serializedMessageStamp
                ? $serializedMessageStamp->getSerializedMessage()
                : $this->serializer->serialize($envelope->getMessage(), $this->format, $context),
            'headers' => $headers,
        ];
    }

    private function decodeStamps(array $encodedEnvelope): array
    {
        $stamps = [];
        foreach ($encodedEnvelope['headers'] as $name => $value) {
            if (!str_starts_with($name, self::STAMP_HEADER_PREFIX)) {
                continue;
            }

            try {
                $stamps[] = $this->serializer->deserialize($value, substr($name, \strlen(self::STAMP_HEADER_PREFIX)).'[]', $this->format, $this->context);
            } catch (ExceptionInterface $e) {
                throw new MessageDecodingFailedException('Could not decode stamp: '.$e->getMessage(), $e->getCode(), $e);
            }
        }
        if ($stamps) {
            $stamps = array_merge(...$stamps);
        }

        return $stamps;
    }

    private function encodeStamps(Envelope $envelope): array
    {
        if (!$allStamps = $envelope->all()) {
            return [];
        }

        $headers = [];
        foreach ($allStamps as $class => $stamps) {
            $headers[self::STAMP_HEADER_PREFIX.$class] = $this->serializer->serialize($stamps, $this->format, $this->context);
        }

        return $headers;
    }

    /**
     * @param StampInterface[] $stamps
     */
    private function findFirstSerializerStamp(array $stamps): ?SerializerStamp
    {
        foreach ($stamps as $stamp) {
            if ($stamp instanceof SerializerStamp) {
                return $stamp;
            }
        }

        return null;
    }

    private function getContentTypeHeader(): array
    {
        $mimeType = $this->getMimeTypeForFormat();

        return null === $mimeType ? [] : ['Content-Type' => $mimeType];
    }

    private function getMimeTypeForFormat(): ?string
    {
        return match ($this->format) {
            'json' => 'application/json',
            'xml' => 'application/xml',
            'yml',
            'yaml' => 'application/yaml',
            'csv' => 'text/csv',
            default => null,
        };
    }

    private function getTypeFromEnvelope(Envelope $envelope): string
    {
        $messageClass = $envelope->getMessage()::class;

        if (isset($this->classToTypeMap[$messageClass])) {
            return $this->classToTypeMap[$messageClass];
        }

        foreach ([$messageClass] + class_parents($messageClass) + class_implements($messageClass) as $class) {
            foreach ((new \ReflectionClass($class))->getAttributes(AsMessage::class, \ReflectionAttribute::IS_INSTANCEOF) as $refAttr) {
                $asMessage = $refAttr->newInstance();

                if ($asMessage->serializedTypeName) {
                    return $this->classToTypeMap[$messageClass] = $asMessage->serializedTypeName;
                }
            }
        }

        return $this->classToTypeMap[$messageClass] = $messageClass;
    }
}
