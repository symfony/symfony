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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Handler\RawMessage;
use Symfony\Component\Messenger\Stamp\ContentTypeStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.3
 */
class Serializer implements SerializerInterface
{
    // There is no registered PHP serialized data mime type by IANA, using
    // a custom arbitrary one to identify it.
    // @todo later add "; charset=utf-8" and support for encoding
    //   although this probably should be handled by the serialize component
    const CONTENT_TYPE_PHP_SERIALIZED = 'application/x-php-serialized';

    private const STAMP_HEADER_PREFIX = 'X-Message-Stamp-';

    private $phpSerializer;
    private $serializer;
    private $format;
    private $context;
    private $allowRawMessages;

    /**
     * Constructor
     *
     * @param SerializerInterface $serializer Symfony serializer
     * @param string $format                  Default serialization format for messages that don't carry a ContentTypeStamp
     * @param array $context                  Default serializer context
     * @param bool $allowRawMessages          If set true messages that cannot be unserialized will be wrapped in RawMessage instances
     */
    public function __construct(?SymfonySerializerInterface $serializer = null, string $format = 'json', array $context = [], bool $allowRawMessages = false)
    {
        $this->phpSerializer = new PhpSerializer();
        $this->serializer = $serializer;
        $this->format = 'php' === $format ? self::CONTENT_TYPE_PHP_SERIALIZED : $format;
        $this->context = $context;
        $this->allowRawMessages = $allowRawMessages;
    }

    public static function create(): self
    {
        $symfonySerializer = null;

        if (class_exists(SymfonySerializer::class)) {
            $encoders = [new XmlEncoder(), new JsonEncoder()];
            $normalizers = [new ArrayDenormalizer(), new ObjectNormalizer()];
            $symfonySerializer = new SymfonySerializer($normalizers, $encoders);
        }

        return new self($symfonySerializer);
    }

    /**
     * {@inheritdoc}
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        if (empty($encodedEnvelope['body'])) {
            throw new MessageDecodingFailedException('Encoded envelope should have at least a "body".');
        }

        $stamps = $this->decodeStamps($encodedEnvelope);

        // $this->format can be a mimetype fomatted string as well
        $contentType = $this->getMimeTypeForFormat($encodedEnvelope['headers']['Content-Type'] ?? $this->format);

        if (self::CONTENT_TYPE_PHP_SERIALIZED === $contentType) {
            return $this->phpSerializer->decode($encodedEnvelope)->with(...$stamps);
        }

        if (!$this->serializer) {
            if (!$this->allowRawMessages) {
                throw $this->createSerializerInstallHintException();
            }
            return new Envelope(new RawMessage($encodedEnvelope['body'], $encodedEnvelope['headers'] ?? []), $stamps);
        }

        if (empty($encodedEnvelope['headers']['type'])) {
            if (!$this->allowRawMessages) {
                throw new MessageDecodingFailedException('Encoded envelope does not have a "type" header.');
            }
            return new Envelope(new RawMessage($encodedEnvelope['body'], $encodedEnvelope['headers'] ?? []), $stamps);
        }

        $serializerStamp = $this->findFirstSerializerStamp($stamps);
        $format = $this->getFormatForMimetype($contentType);

        $context = $this->context;
        if (null !== $serializerStamp) {
            $context = $serializerStamp->getContext() + $context;
        }

        try {
            $message = $this->serializer->deserialize($encodedEnvelope['body'], $encodedEnvelope['headers']['type'], $format, $context);
        } catch (UnexpectedValueException $e) {
            if (!$this->allowRawMessages) {
                throw new MessageDecodingFailedException(sprintf('Could not decode message: %s.', $e->getMessage()), $e->getCode(), $e);
            }
            $message = new RawMessage($encodedEnvelope['body'], $encodedEnvelope['headers']);
        }

        return new Envelope($message, $stamps);
    }

    /**
     * {@inheritdoc}
     */
    public function encode(Envelope $envelope): array
    {
        $context = $this->context;
        /** @var SerializerStamp|null $serializerStamp */
        if ($serializerStamp = $envelope->last(SerializerStamp::class)) {
            $context = $serializerStamp->getContext() + $context;
        }

        $contentType = null;
        // Allow messages to be sent with an arbitrary content-type
        if ($contentTypeStamps = $envelope->all(ContentTypeStamp::class)) {
            $contentType = $contentTypeStamps[0]->getContentType();
        }
        if (!$contentType) {
            $contentType = $this->format;
        }

        if (self::CONTENT_TYPE_PHP_SERIALIZED === $contentType) {
            $encodedEnvelope = $this->phpSerializer->encode($envelope);
            $encodedEnvelope['headers']['Content-Type'] = self::CONTENT_TYPE_PHP_SERIALIZED;

            return $encodedEnvelope;
        }

        if (!$this->serializer) {
            throw $this->createSerializerInstallHintException();
        }

        // Apply both transformations to ensure everything is normalized,
        // content type could be a serializer format if set by the developers
        // and format could be a content type by configuration.
        $contentType = $this->getMimeTypeForFormat($contentType);
        $format = $this->getFormatForMimetype($contentType);

        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);
        $message = $envelope->getMessage();

        return [
            'body' => $this->serializer->serialize($message, $format, $context),
            'headers' => [
                // @todo 'string' is a bit stupid here, but type should be propagated
                //   by the envelope from the end-user developer
                'type' => \is_object($message) ? \get_class($message) : 'string',
                'Content-Type' => $contentType,
            ] + $this->encodeStamps($envelope),
        ];
    }

    private function decodeStamps(array $encodedEnvelope): array
    {
        if (empty($encodedEnvelope['headers'])) {
            return [];
        }

        $stamps = [];

        foreach ($encodedEnvelope['headers'] as $name => $value) {
            if (0 !== strpos($name, self::STAMP_HEADER_PREFIX)) {
                continue;
            }

            try {
                $stamps[] = $this->serializer->deserialize($value, substr($name, \strlen(self::STAMP_HEADER_PREFIX)).'[]', $this->format, $this->context);
            } catch (UnexpectedValueException $e) {
                throw new MessageDecodingFailedException(sprintf('Could not decode stamp: %s.', $e->getMessage()), $e->getCode(), $e);
            }
        }
        if ($stamps) {
            $stamps = array_merge(...$stamps);
        }

        return $stamps;
    }

    private function encodeStamps(Envelope $envelope): array
    {
        // Drop content-type stamp since it is supposed be stored in headers
        if (!$allStamps = $envelope->withoutAll(ContentTypeStamp::class)->all()) {
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

    private function getFormatForMimetype(string $contentType): string
    {
        // @todo there is room for improvement here, and it would the
        //    serializer component responsability to do this transparently.
        if (false === \strpos($contentType, '/')) {
            return $contentType;
        }
        if (false !== \strpos($contentType, 'csv')) {
            return 'csv';
        }
        if (false !== \strpos($contentType, 'json')) {
            return 'json';
        }
        if (false !== \strpos($contentType, 'xml')) {
            return 'xml';
        }
        if (false !== \strpos($contentType, 'yaml')) {
            return 'yaml';
        }
        return $contentType;
    }

    private function getMimeTypeForFormat(string $format): string
    {
        if (false !== \strpos($format, '/')) {
            return $format;
        }

        switch ($this->format) {
            case 'json':
                return 'application/json';
            case 'xml':
                return 'application/xml';
            case 'yml':
            case 'yaml':
                return 'application/x-yaml';
            case 'csv':
                return 'text/csv';
        }

        return 'application/'.$format;
    }

    private function createSerializerInstallHintException(): LogicException
    {
        return new LogicException(sprintf('The "%s" class requires Symfony\'s Serializer component. Try running "composer require symfony/serializer" or use "%s" instead.', __CLASS__, PhpSerializer::class));
    }
}
