<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Normalize Mime message classes.
 *
 * It forces the use of a PropertyNormalizer instance for normalization
 * of all data objects composing a Message.
 *
 * Emails using resources for any parts are not serializable.
 */
final class MimeMessageNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    private NormalizerInterface&DenormalizerInterface $serializer;
    private array $headerClassMap;
    private \ReflectionProperty $headersProperty;

    public function __construct(private readonly PropertyNormalizer $normalizer)
    {
        $this->headerClassMap = (new \ReflectionClassConstant(Headers::class, 'HEADER_CLASS_MAP'))->getValue();
        $this->headersProperty = new \ReflectionProperty(Headers::class, 'headers');
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            RawMessage::class => true,
            Headers::class => true,
            HeaderInterface::class => true,
            Address::class => true,
            AbstractPart::class => true,
        ];
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if (!$serializer instanceof NormalizerInterface || !$serializer instanceof DenormalizerInterface) {
            throw new LogicException(\sprintf('The passed serializer should implement both NormalizerInterface and DenormalizerInterface, "%s" given.', get_debug_type($serializer)));
        }
        $this->serializer = $serializer;
        $this->normalizer->setSerializer($serializer);
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        if ($data instanceof Headers) {
            $ret = [];
            foreach ($this->headersProperty->getValue($data) as $name => $header) {
                $ret[$name] = $this->serializer->normalize($header, $format, $context);
            }

            return $ret;
        }

        $ret = $this->normalizer->normalize($data, $format, $context);

        if ($data instanceof AbstractPart) {
            $ret['class'] = $data::class;
            unset($ret['seekable'], $ret['cid'], $ret['handle']);
        }

        if ($data instanceof RawMessage) {
            $ret['class'] = $data::class;

            if (\array_key_exists('message', $ret) && null === $ret['message']) {
                unset($ret['message']);
            }
        }

        return $ret;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (Headers::class === $type) {
            $ret = [];
            foreach ($data as $headers) {
                foreach ($headers as $header) {
                    $ret[] = $this->serializer->denormalize($header, $this->headerClassMap[strtolower($header['name'])] ?? UnstructuredHeader::class, $format, $context);
                }
            }

            return new Headers(...$ret);
        }

        if (AbstractPart::class === $type) {
            $type = $this->resolveClass($data, AbstractPart::class, $context);
            unset($data['class']);
            $data['headers'] = $this->serializer->denormalize($data['headers'], Headers::class, $format, $context);
        } elseif (RawMessage::class === $type && !\is_array($data)) {
            // a raw message is a string, an iterable of strings or a resource
            return new RawMessage($data);
        } elseif (\is_array($data) && is_a($type, RawMessage::class, true)) {
            if (RawMessage::class === $type && \array_key_exists('class', $data)) {
                $type = $this->resolveClass($data, RawMessage::class, $context);
            }

            unset($data['class']);
        }

        return $this->normalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof RawMessage || $data instanceof Headers || $data instanceof HeaderInterface || $data instanceof Address || $data instanceof AbstractPart;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_a($type, RawMessage::class, true) || Headers::class === $type || AbstractPart::class === $type;
    }

    private function resolveClass(mixed $data, string $baseClass, array $context): string
    {
        $class = $data['class'] ?? null;

        if (!\is_string($class) || !is_a($class, $baseClass, true)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('Expected a subclass of "%s", got "%s".', $baseClass, \is_string($class) ? $class : get_debug_type($class)), $data, [$baseClass], $context['deserialization_path'] ?? null);
        }

        return $class;
    }
}
