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

use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ClassDiscriminatorNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface, DenormalizerAwareInterface, SerializerAwareInterface
{
    private $denormalizer;

    private $denormalizerChain;

    private $classDiscriminatorResolver;

    public function __construct(DenormalizerInterface $denormalizer, ClassDiscriminatorResolverInterface $classDiscriminatorResolver)
    {
        $this->denormalizer = $denormalizer;
        $this->classDiscriminatorResolver = $classDiscriminatorResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = [])
    {
        if (!$this->denormalizer instanceof NormalizerInterface) {
            throw new LogicException('Cannot normalize object because injected denormalizer is not a normalizer');
        }

        $data = $this->denormalizer->normalize($object, $format, $context);
        $mapping = $this->classDiscriminatorResolver->getMappingForMappedObject($object);

        if (null !== $mapping && (null !== ($typeValue = $mapping->getMappedObjectType($object)))) {
            $data[$mapping->getTypeProperty()] = $typeValue;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        if (!$this->denormalizer instanceof NormalizerInterface) {
            return false;
        }

        return $this->denormalizer->supportsNormalization($data, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        $mapping = $this->classDiscriminatorResolver->getMappingForClass($class);

        if (null === $mapping) {
            return $this->denormalizer->denormalize($data, $class, $format, $context);
        }

        if (!isset($data[$mapping->getTypeProperty()])) {
            throw new RuntimeException(sprintf('Type property "%s" not found for the abstract object "%s"', $mapping->getTypeProperty(), $class));
        }

        $type = $data[$mapping->getTypeProperty()];

        if (null === ($mappedClass = $mapping->getClassForType($type))) {
            throw new RuntimeException(sprintf('The type "%s" has no mapped class for the abstract object "%s"', $type, $class));
        }

        return $this->denormalizerChain->denormalize($data, $mappedClass, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return
            $this->denormalizer->supportsDenormalization($data, $type, $format)
            ||
            (\interface_exists($type, false) && null !== $this->classDiscriminatorResolver->getMappingForClass($type))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return $this->denormalizer instanceof CacheableSupportsMethodInterface && $this->denormalizer->hasCacheableSupportsMethod();
    }

    /**
     * {@inheritdoc}
     */
    public function setDenormalizer(DenormalizerInterface $denormalizer)
    {
        if ($this->denormalizer instanceof DenormalizerAwareInterface) {
            $this->denormalizer->setDenormalizer($denormalizer);
        }
        $this->denormalizerChain = $denormalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function setNormalizer(NormalizerInterface $normalizer)
    {
        if ($this->denormalizer instanceof NormalizerAwareInterface) {
            $this->denormalizer->setNormalizer($normalizer);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        if ($this->denormalizer instanceof SerializerAwareInterface) {
            $this->denormalizer->setSerializer($serializer);
        }
    }
}
