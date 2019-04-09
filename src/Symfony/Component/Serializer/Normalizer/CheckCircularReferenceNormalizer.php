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

use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Handle circular references.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
final class CheckCircularReferenceNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface, DenormalizerAwareInterface, SerializerAwareInterface
{
    public const CIRCULAR_REFERENCE_LIMIT = 'circular_reference_limit';
    public const CIRCULAR_REFERENCE_HANDLER = 'circular_reference_handler';
    private const CIRCULAR_REFERENCE_LIMIT_COUNTERS = 'circular_reference_limit_counters';

    private $normalizer;

    public function __construct(NormalizerInterface $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        if (!$this->normalizer instanceof DenormalizerInterface) {
            return null;
        }

        return $this->normalizer->denormalize($data, $class, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return ($this->normalizer instanceof DenormalizerInterface && $this->normalizer->supportsDenormalization($data, $type, $format));
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = [])
    {
        if ($this->isCircularReference($object, $context)) {
            return $this->handleCircularReference($object, $format, $context);
        }

        return $this->normalizer->normalize($object, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return $this->normalizer instanceof CacheableSupportsMethodInterface && $this->normalizer->hasCacheableSupportsMethod();
    }

    /**
     * {@inheritdoc}
     */
    public function setDenormalizer(DenormalizerInterface $denormalizer)
    {
        if ($this->normalizer instanceof DenormalizerAwareInterface) {
            $this->normalizer->setDenormalizer($denormalizer);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setNormalizer(NormalizerInterface $normalizer)
    {
        if ($this->normalizer instanceof NormalizerAwareInterface) {
            $this->normalizer->setNormalizer($normalizer);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        if ($this->normalizer instanceof SerializerAwareInterface) {
            $this->normalizer->setSerializer($serializer);
        }
    }

    private function isCircularReference($object, &$context)
    {
        $objectHash = spl_object_hash($object);

        $circularReferenceLimit = $context[self::CIRCULAR_REFERENCE_LIMIT] ?? 1;

        if (isset($context[self::CIRCULAR_REFERENCE_LIMIT_COUNTERS][$objectHash])) {
            if ($context[self::CIRCULAR_REFERENCE_LIMIT_COUNTERS][$objectHash] >= $circularReferenceLimit) {
                unset($context[self::CIRCULAR_REFERENCE_LIMIT_COUNTERS][$objectHash]);

                return true;
            }

            ++$context[self::CIRCULAR_REFERENCE_LIMIT_COUNTERS][$objectHash];
        } else {
            $context[self::CIRCULAR_REFERENCE_LIMIT_COUNTERS][$objectHash] = 1;
        }

        return false;
    }

    protected function handleCircularReference($object, string $format = null, array $context = [])
    {
        $circularReferenceHandler = $context[self::CIRCULAR_REFERENCE_HANDLER] ?? null;

        if ($circularReferenceHandler) {
            return $circularReferenceHandler($object, $format, $context);
        }

        $circularReferenceLimit = $context[self::CIRCULAR_REFERENCE_LIMIT] ?? 1;

        throw new CircularReferenceException(sprintf('A circular reference has been detected when serializing the object of class "%s" (configured limit: %d)', \get_class($object), $circularReferenceLimit));
    }
}
