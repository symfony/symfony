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
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Serializer\Result\DenormalizationResult;
use Symfony\Component\Serializer\Result\NormalizationResult;
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
final class MimeMessageNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface, CacheableSupportsMethodInterface
{
    private $serializer;
    private $normalizer;
    private $headerClassMap;
    private $headersProperty;

    public function __construct(PropertyNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
        $this->headerClassMap = (new \ReflectionClassConstant(Headers::class, 'HEADER_CLASS_MAP'))->getValue();
        $this->headersProperty = new \ReflectionProperty(Headers::class, 'headers');
        $this->headersProperty->setAccessible(true);
    }

    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
        $this->normalizer->setSerializer($serializer);
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, ?string $format = null, array $context = [])
    {
        if ($object instanceof Headers) {
            $ret = [];
            foreach ($this->headersProperty->getValue($object) as $name => $header) {
                $ret[$name] = $this->serializer->normalize($header, $format, $context);
            }

            if ($context[SerializerInterface::RETURN_RESULT] ?? false) {
                return NormalizationResult::success($ret);
            }

            return $ret;
        }

        if ($object instanceof AbstractPart) {
            $ret = $this->normalizer->normalize($object, $format, $context);
            $ret['class'] = \get_class($object);

            if ($context[SerializerInterface::RETURN_RESULT] ?? false) {
                return NormalizationResult::success($ret);
            }

            return $ret;
        }

        $ret = $this->normalizer->normalize($object, $format, $context);

        if ($context[SerializerInterface::RETURN_RESULT] ?? false) {
            return NormalizationResult::success($ret);
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, ?string $format = null, array $context = [])
    {
        if (Headers::class === $type) {
            $ret = [];
            $invariantViolations = [];
            foreach ($data as $headers) {
                foreach ($headers as $header) {
                    $name = $header['name'];
                    $result = $this->serializer->denormalize($header, $this->headerClassMap[strtolower($name)] ?? UnstructuredHeader::class, $format, $context);

                    if ($result instanceof DenormalizationResult) {
                        if (!$result->isSucessful()) {
                            $invariantViolations += $result->getInvariantViolations();

                            continue;
                        }

                        $result = $result->getDenormalizedValue();
                    }

                    $ret[] = $result;
                }
            }

            if ([] !== $invariantViolations) {
                return DenormalizationResult::failure($invariantViolations);
            }

            $headers = new Headers(...$ret);

            if ($context[SerializerInterface::RETURN_RESULT] ?? false) {
                return DenormalizationResult::success($headers);
            }

            return $headers;
        }

        if (AbstractPart::class === $type) {
            $type = $data['class'];
            unset($data['class']);
        }

        return $this->normalizer->denormalize($data, $type, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null)
    {
        return $data instanceof Message || $data instanceof Headers || $data instanceof HeaderInterface || $data instanceof Address || $data instanceof AbstractPart;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, ?string $format = null)
    {
        return is_a($type, Message::class, true) || Headers::class === $type || AbstractPart::class === $type;
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }
}
