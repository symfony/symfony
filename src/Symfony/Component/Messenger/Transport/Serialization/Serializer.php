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
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\Serialization\TypeResolver\DecodedAwareTypeResolverInterface;
use Symfony\Component\Messenger\Transport\Serialization\TypeResolver\HeaderTypeResolver;
use Symfony\Component\Messenger\Transport\Serialization\TypeResolver\TypeResolverInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class Serializer implements SerializerInterface
{
    public const MESSENGER_SERIALIZATION_CONTEXT = 'messenger_serialization';
    public const TYPE_RESOLVER = 'messenger_type_resolver';

    private const STAMP_HEADER_PREFIX = 'X-Message-Stamp-';

    private $serializer;
    private $format;
    private $context;

    public function __construct(SymfonySerializerInterface $serializer = null, string $format = 'json', array $context = [])
    {
        $this->serializer = $serializer ?? self::create()->serializer;
        $this->format = $format;
        $this->context = $context + [self::MESSENGER_SERIALIZATION_CONTEXT => true];
    }

    public static function create(): self
    {
        if (!class_exists(SymfonySerializer::class)) {
            throw new LogicException(sprintf('The "%s" class requires Symfony\'s Serializer component. Try running "composer require symfony/serializer" or use "%s" instead.', __CLASS__, PhpSerializer::class));
        }

        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new ArrayDenormalizer(), new ObjectNormalizer()];
        $serializer = new SymfonySerializer($normalizers, $encoders);

        return new self($serializer);
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
        $serializerStamp = $this->findFirstSerializerStamp($stamps);

        $context = $this->context;
        if (null !== $serializerStamp) {
            $context = $serializerStamp->getContext() + $context;
        }

        try {
            $message = $this->deserialize($encodedEnvelope, $context);
        } catch (ExceptionInterface $e) {
            throw new MessageDecodingFailedException('Could not decode message: '.$e->getMessage(), $e->getCode(), $e);
        }

        return new Envelope($message, $stamps);
    }

    private function deserialize(array $encodedEnvelope, array $context)
    {
        $resolver = $context[self::TYPE_RESOLVER] ?? new HeaderTypeResolver();

        if ($resolver instanceof TypeResolverInterface) {
            $type = $resolver->resolve($encodedEnvelope);

            return $this->serializer->deserialize($encodedEnvelope['body'], $type, $this->format, $context);
        }

        if ($resolver instanceof DecodedAwareTypeResolverInterface) {
            if (!$this->serializer instanceof DecoderInterface || !$this->serializer instanceof DenormalizerInterface) {
                throw new MessageDecodingFailedException(sprintf('A serializer implementing "%s" and "%s" is needed to use "%s".', DecoderInterface::class, DenormalizerInterface::class, \get_class($resolver)));
            }

            $decodedBody = $this->serializer->decode($encodedEnvelope['body'], $this->format, $context);
            $type = $resolver->resolve($encodedEnvelope, $decodedBody);

            return $this->serializer->denormalize($decodedBody, $type, $this->format, $context);
        }

        throw new MessageDecodingFailedException(sprintf(sprintf('"%s" must be an instance of "%s" or "%s", "%s" given.', self::TYPE_RESOLVER, TypeResolverInterface::class, DecodedAwareTypeResolverInterface::class, \is_object($resolver) ? \get_class($resolver) : \gettype($resolver))));
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

        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

        $headers = ['type' => \get_class($envelope->getMessage())] + $this->encodeStamps($envelope) + $this->getContentTypeHeader();

        return [
            'body' => $this->serializer->serialize($envelope->getMessage(), $this->format, $context),
            'headers' => $headers,
        ];
    }

    private function decodeStamps(array $encodedEnvelope): array
    {
        $stamps = [];
        foreach ($encodedEnvelope['headers'] ?? [] as $name => $value) {
            if (0 !== strpos($name, self::STAMP_HEADER_PREFIX)) {
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

        return null;
    }
}
