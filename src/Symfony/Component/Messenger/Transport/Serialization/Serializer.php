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
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class Serializer implements SerializerInterface
{
    private $serializer;
    private $format;
    private $context;

    public function __construct(SymfonySerializerInterface $serializer, string $format = 'json', array $context = array())
    {
        $this->serializer = $serializer;
        $this->format = $format;
        $this->context = $context;
    }

    public static function create(): self
    {
        if (!class_exists(SymfonySerializer::class)) {
            throw new LogicException(sprintf('The default Messenger Serializer requires Symfony\'s Serializer component. Try running "composer require symfony/serializer".'));
        }

        $encoders = array(new XmlEncoder(), new JsonEncoder());
        $normalizers = array(new ObjectNormalizer());
        $serializer = new SymfonySerializer($normalizers, $encoders);

        return new self($serializer);
    }

    /**
     * {@inheritdoc}
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        if (empty($encodedEnvelope['body']) || empty($encodedEnvelope['headers'])) {
            throw new InvalidArgumentException('Encoded envelope should have at least a "body" and some "headers".');
        }

        if (empty($encodedEnvelope['headers']['type'])) {
            throw new InvalidArgumentException('Encoded envelope does not have a "type" header.');
        }

        $stamps = $this->decodeStamps($encodedEnvelope);

        $context = $this->context;
        /** @var SerializerStamp|null $serializerStamp */
        if ($serializerStamp = $stamps[SerializerStamp::class] ?? null) {
            $context = $serializerStamp->getContext() + $context;
        }

        $message = $this->serializer->deserialize($encodedEnvelope['body'], $encodedEnvelope['headers']['type'], $this->format, $context);

        return new Envelope($message, ...$stamps);
    }

    /**
     * {@inheritdoc}
     */
    public function encode(Envelope $envelope): array
    {
        $context = $this->context;
        /** @var SerializerStamp|null $serializerStamp */
        if ($serializerStamp = $envelope->get(SerializerStamp::class)) {
            $context = $serializerStamp->getContext() + $context;
        }

        $headers = array('type' => \get_class($envelope->getMessage())) + $this->encodeStamps($envelope);

        return array(
            'body' => $this->serializer->serialize($envelope->getMessage(), $this->format, $context),
            'headers' => $headers,
        );
    }

    private function decodeStamps($encodedEnvelope)
    {
        $prefix = 'X-Message-Stamp-';
        $stamps = array();
        foreach ($encodedEnvelope['headers'] as $name => $value) {
            if (0 !== strpos($name, $prefix)) {
                continue;
            }

            $stamps[] = $this->serializer->deserialize($value, substr($name, \strlen($prefix)), $this->format, $this->context);
        }

        return $stamps;
    }

    private function encodeStamps(Envelope $envelope)
    {
        if (!$stamps = $envelope->all()) {
            return array();
        }

        $headers = array();
        foreach ($stamps as $stamp) {
            $headers['X-Message-Stamp-'.\get_class($stamp)] = $this->serializer->serialize($stamp, $this->format, $this->context);
        }

        return $headers;
    }
}
