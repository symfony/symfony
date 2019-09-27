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

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

/**
 * Normalizes FlattenException instances.
 *
 * @author Pascal Luna <skalpa@zetareticuli.org>
 *
 * @experimental
 */
class FlattenExceptionNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function normalize($object, $format = null, array $context = [])
    {
        if (!$object instanceof FlattenException) {
            throw new InvalidArgumentException(sprintf('The object must be an instance of %s.', FlattenException::class));
        }
        /* @var FlattenException $object */

        $normalized = [
            'detail' => $object->getMessage(),
            'code' => $object->getCode(),
            'headers' => $object->getHeaders(),
            'class' => $object->getClass(),
            'file' => $object->getFile(),
            'line' => $object->getLine(),
            'previous' => null === $object->getPrevious() ? null : $this->normalize($object->getPrevious(), $format, $context),
            'trace' => $object->getTrace(),
            'trace_as_string' => $object->getTraceAsString(),
        ];
        if (null !== $status = $object->getStatusCode()) {
            $normalized['status'] = $status;
        }

        return $normalized;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof FlattenException;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type, $format = null, array $context = [])
    {
        if (!\is_array($data)) {
            throw new NotNormalizableValueException(sprintf(
                'The normalized data must be an array, got %s.',
                \is_object($data) ? \get_class($data) : \gettype($data)
            ));
        }

        $object = new FlattenException();

        $object->setMessage($data['detail'] ?? null);
        $object->setCode($data['code'] ?? null);
        $object->setStatusCode($data['status'] ?? null);
        $object->setClass($data['class'] ?? null);
        $object->setFile($data['file'] ?? null);
        $object->setLine($data['line'] ?? null);

        if (isset($data['headers'])) {
            $object->setHeaders((array) $data['headers']);
        }
        if (isset($data['previous'])) {
            $object->setPrevious($this->denormalize($data['previous'], $type, $format, $context));
        }
        if (isset($data['trace'])) {
            $property = new \ReflectionProperty(FlattenException::class, 'trace');
            $property->setAccessible(true);
            $property->setValue($object, (array) $data['trace']);
        }
        if (isset($data['trace_as_string'])) {
            $property = new \ReflectionProperty(FlattenException::class, 'traceAsString');
            $property->setAccessible(true);
            $property->setValue($object, $data['trace_as_string']);
        }

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = [])
    {
        return FlattenException::class === $type;
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === \get_class($this);
    }
}
