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
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\ValidationStamp;
use Symfony\Component\Messenger\Transport\Serialization\Normalizer\FlattenExceptionNormalizer;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;
use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class Serializer implements SerializerInterface, MessageTypeAwareSerializerInterface
{
    public const MESSENGER_SERIALIZATION_CONTEXT = 'messenger_serialization';
    private const STAMP_HEADER_PREFIX = 'X-Message-Stamp-';

    // these options hold a callable, or let the XML parser resolve external entities
    private const CODE_AFFECTING_CONTEXT_OPTIONS = [
        AbstractNormalizer::CALLBACKS => true,
        AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => true,
        AbstractObjectNormalizer::MAX_DEPTH_HANDLER => true,
        XmlEncoder::LOAD_OPTIONS => true,
    ];

    private SymfonySerializerInterface $serializer;
    private array $stampContext;
    private static \WeakMap $decoders;

    /**
     * @var array<string-class, string>
     */
    private array $classToTypeMap = [];

    /**
     * @param array<string, class-string> $typeToClassMap
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
        // stamps have no serialization metadata, so selecting the attributes of the message would encode them empty
        $this->stampContext = array_diff_key($this->context, array_flip([AbstractNormalizer::ATTRIBUTES, AbstractNormalizer::GROUPS, AbstractNormalizer::IGNORED_ATTRIBUTES]));
    }

    public static function create(): self
    {
        if (!class_exists(SymfonySerializer::class)) {
            throw new LogicException(\sprintf('The "%s" class requires Symfony\'s Serializer component. Try running "composer require symfony/serializer" or use "%s" instead.', __CLASS__, PhpSerializer::class));
        }

        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [
            new FlattenExceptionNormalizer(),
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
        // a failure keeps the stamps that decode, so that its retries are counted
        $stamps = $this->decodeStamps($encodedEnvelope, $stampFailure);

        // encode() sends a failure under its own class when the envelope it carries has no type: decode that envelope, never a failure built from the body
        if (\is_string($type = $encodedEnvelope['headers']['type'] ?? null) && is_a($this->typeToClassMap[$type] ?? $type, MessageDecodingFailedException::class, true)) {
            unset($encodedEnvelope['headers']['type']);
        }

        if (empty($encodedEnvelope['body']) || empty($encodedEnvelope['headers'])) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, 'Encoded envelope should have at least a "body" and some "headers", or maybe you should implement your own serializer.')->with(...$stamps);
        }

        if (empty($encodedEnvelope['headers']['type'])) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, 'Encoded envelope does not have a "type" header.')->with(...$stamps);
        }

        if (null !== $stampFailure) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, $stampFailure->getMessage(), (int) $stampFailure->getCode(), $stampFailure)->with(...$stamps);
        }

        $stamps[] = $serializedMessageStamp = new SerializedMessageStamp($encodedEnvelope['body']);
        self::$decoders ??= new \WeakMap();
        self::$decoders[$serializedMessageStamp] = $this;

        $serializerStamp = $this->findFirstSerializerStamp($stamps);

        $context = $this->context;
        if (null !== $serializerStamp) {
            $context = $serializerStamp->getContext() + $context;
        }

        $type = $encodedEnvelope['headers']['type'];
        $type = $this->typeToClassMap[$type] ?? $type;

        try {
            $message = $this->serializer->deserialize($encodedEnvelope['body'], $type, $this->format, $context);
        } catch (\Throwable $e) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, 'Could not decode message: '.$e->getMessage(), (int) $e->getCode(), $e)->with(...$stamps);
        }

        return new Envelope($message, $stamps);
    }

    public function getMessageType(array $encodedEnvelope): ?string
    {
        $type = $encodedEnvelope['headers']['type'] ?? null;

        return null === $type ? null : ($this->typeToClassMap[$type] ?? $type);
    }

    public function encode(Envelope $envelope): array
    {
        $context = $this->context;
        if ($serializerStamp = $envelope->last(SerializerStamp::class)) {
            $context = $serializerStamp->getContext() + $context;
        }

        $serializedMessageStamp = $envelope->last(SerializedMessageStamp::class);

        // the body can be reused only when it is what this serializer would produce
        if ($serializedMessageStamp && ($decoder = self::$decoders[$serializedMessageStamp] ?? null) && [$decoder->serializer, $decoder->format, $decoder->context] !== [$this->serializer, $this->format, $this->context]) {
            $serializedMessageStamp = null;
        }

        // A decode failure keeps the original encoded envelope: re-emit its body as-is
        // instead of serializing the exception, which cannot be decoded back
        $isDecodingFailure = $envelope->getMessage() instanceof MessageDecodingFailedException;
        $decodingFailure = $isDecodingFailure ? $envelope->getMessage()->encodedEnvelope : [];
        $decodingFailureBody = \is_string($decodingFailure['body'] ?? null) ? $decodingFailure['body'] : null;
        // the original headers describe the original body, so they win for "type" and "Content-Type"
        $decodingFailureHeaders = null !== $decodingFailureBody && \is_array($decodingFailure['headers'] ?? null) ? $decodingFailure['headers'] : [];

        if ($isDecodingFailure && null === $decodingFailureBody) {
            // the exception itself is encoded: the frames of its stack trace hold the arguments
            // of every call, which can reference the whole object graph
            $context[AbstractNormalizer::IGNORED_ATTRIBUTES] = [...$context[AbstractNormalizer::IGNORED_ATTRIBUTES] ?? [], 'trace'];
        }

        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

        // handler results are for synchronous callers: retries only need the names of the handlers that already ran
        if ($handledStamps = $envelope->all(HandledStamp::class)) {
            $envelope = $envelope->withoutAll(HandledStamp::class)->with(...array_map(static fn (HandledStamp $stamp) => new HandledStamp(null, $stamp->getHandlerName()), $handledStamps));
        }

        $headers = [
            'type' => $decodingFailureHeaders['type'] ?? $this->getTypeFromEnvelope($envelope),
            ...$this->encodeStamps($envelope),
            ...(null !== $decodingFailureBody ? [] : $this->getContentTypeHeader()),
        ];

        return [
            'body' => $decodingFailureBody
                ?? $serializedMessageStamp?->getSerializedMessage()
                ?? $this->serializer->serialize($envelope->getMessage(), $this->format, $context),
            'headers' => $headers + $decodingFailureHeaders,
        ];
    }

    private function decodeStamps(array $encodedEnvelope, ?\Throwable &$failure = null): array
    {
        $stamps = [];
        foreach (\is_array($encodedEnvelope['headers'] ?? null) ? $encodedEnvelope['headers'] : [] as $name => $value) {
            if (!str_starts_with($name, self::STAMP_HEADER_PREFIX)) {
                continue;
            }

            $class = substr($name, \strlen(self::STAMP_HEADER_PREFIX));

            try {
                if (!is_subclass_of($class, StampInterface::class)) {
                    $failure ??= new MessageDecodingFailedException(\sprintf('Could not decode stamp: "%s" is not a "%s".', $class, StampInterface::class));
                    continue;
                }

                // encoding strips these stamps, so they never come from a transport
                if (is_subclass_of($class, NonSendableStampInterface::class)) {
                    $failure ??= new MessageDecodingFailedException(\sprintf('Could not decode stamp: "%s" is a "%s".', $class, NonSendableStampInterface::class));
                    continue;
                }

                if (ValidationStamp::class === $class && XmlEncoder::FORMAT === $this->format && $this->serializer instanceof DecoderInterface && $this->serializer instanceof DenormalizerInterface) {
                    $stamps[] = $this->decodeXmlValidationStamps($value);
                } else {
                    $stamps[] = $this->serializer->deserialize($value, $class.'[]', $this->format, $this->stampContext);
                }
            } catch (ExceptionInterface $e) {
                $failure ??= new MessageDecodingFailedException('Could not decode stamp: '.$e->getMessage(), $e->getCode(), $e);
            } catch (\Throwable $e) {
                $failure ??= $e;
            }
        }
        if ($stamps) {
            $stamps = array_merge(...$stamps);
        }

        foreach ($stamps as $i => $stamp) {
            if ($stamp instanceof SerializerStamp) {
                $stamps[$i] = new SerializerStamp(array_diff_key($stamp->getContext(), self::CODE_AFFECTING_CONTEXT_OPTIONS));
            } elseif ($stamp instanceof ValidationStamp && \is_array($groups = $stamp->getGroups()) && isset($groups['groups']) && class_exists(GroupSequence::class)) {
                // without type information, a GroupSequence decodes as an array
                $stamps[$i] = new ValidationStamp(new GroupSequence((array) $groups['groups']));
            }
        }

        return $stamps;
    }

    /**
     * The XML encoder decodes a list of one group as the group itself, and an empty list as an empty string.
     *
     * @return ValidationStamp[]
     */
    private function decodeXmlValidationStamps(string $value): array
    {
        $stamps = [];
        foreach ((array) $this->serializer->decode($value, $this->format, $this->stampContext) as $data) {
            $stamps[] = \is_string($groups = $data['groups'] ?? null)
                ? new ValidationStamp('' === $groups ? [] : [$groups])
                : $this->serializer->denormalize($data, ValidationStamp::class, $this->format, $this->stampContext);
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
            $headers[self::STAMP_HEADER_PREFIX.$class] = $this->serializer->serialize($stamps, $this->format, $this->stampContext);
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
