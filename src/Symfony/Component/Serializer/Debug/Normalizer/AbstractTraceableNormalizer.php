<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Debug\Normalizer;

use Symfony\Component\Serializer\Debug\SerializerActionFactoryInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractTraceableNormalizer implements SerializerAwareInterface, DenormalizerAwareInterface, NormalizerAwareInterface, CacheableSupportsMethodInterface
{
    /**
     * @var DenormalizerInterface|NormalizerInterface
     */
    protected $delegate;
    private $serializerActionFactory;
    private $normalizations = [];
    private $denormalizations = [];

    /**
     * @param DenormalizerInterface|NormalizerInterface $delegate
     */
    public function __construct(object $delegate, SerializerActionFactoryInterface $serializerActionFactory)
    {
        $this->delegate = $delegate;
        $this->serializerActionFactory = $serializerActionFactory;
    }

    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        $result = $this->delegate->denormalize($data, $type, $format, $context);
        $this->denormalizations[] = $this->serializerActionFactory->createDenormalization($this->delegate, $data, $result, $type, $format, $context);

        return $result;
    }

    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        return $this->delegate->supportsDenormalization($data, $type, $format);
    }

    public function normalize($object, string $format = null, array $context = [])
    {
        $result = $this->delegate->normalize($object, $format, $context);
        $this->normalizations[] = $this->serializerActionFactory->createNormalization($this->delegate, $object, $result, $format, $context);

        return $result;
    }

    public function supportsNormalization($data, string $format = null): bool
    {
        return $this->delegate->supportsNormalization($data, $format);
    }

    /*
     * Since the Serializer, in its constructor, injects itself into its normalizers,
     * depending on the implementing interfaces, we need to mimic this behaviour here
     * and pass them to the delegate.
     *
     * Unfortunately this is heavily bound to the Serializer implementation. :(
     * @see \Symfony\Component\Serializer\Serializer:__construct()
     */

    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->delegate instanceof SerializerAwareInterface) {
            $this->delegate->setSerializer($serializer);
        }
    }

    public function setDenormalizer(DenormalizerInterface $denormalizer): void
    {
        if ($this->delegate instanceof DenormalizerAwareInterface) {
            $this->delegate->setDenormalizer($denormalizer);
        }
    }

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        if ($this->delegate instanceof NormalizerAwareInterface) {
            $this->delegate->setNormalizer($normalizer);
        }
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return $this->delegate instanceof CacheableSupportsMethodInterface && $this->delegate->hasCacheableSupportsMethod();
    }

    public function getNormalizations(): array
    {
        return $this->normalizations;
    }

    public function getDenormalizations(): array
    {
        return $this->denormalizations;
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this->delegate, $name)) {
            return $this->delegate->$name(...$arguments);
        }

        throw new \LogicException('Unexpected method call');
    }
}
