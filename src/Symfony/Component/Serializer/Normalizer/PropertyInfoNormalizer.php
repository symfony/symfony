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

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\Serializer\Extractor\ObjectPropertyListExtractor;
use Symfony\Component\Serializer\NameConverter\AdvancedNameConverterInterface;


/**
 * Converts between objects and arrays using the PropertyInfo and PropertyAccess component.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class PropertyInfoNormalizer implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface, DenormalizerAwareInterface, CacheableSupportsMethodInterface
{
    use NormalizerAwareTrait;
    use DenormalizerAwareTrait;

    private $instantiator;

    private $normalizerExtractor;

    private $denormalizerExtractor;

    private $propertyAccessor;

    private $nameConverter;

    private $objectClassResolver;

    public function __construct($instantiator, ObjectPropertyListExtractor $normalizerExtractor, PropertyListExtractorInterface $denormalizerExtractor, PropertyAccessorInterface $propertyAccessor, AdvancedNameConverterInterface $nameConverter = null, callable $objectClassReolver = null)
    {
        $this->instantiator = $instantiator;
        $this->normalizerExtractor = $normalizerExtractor;
        $this->denormalizerExtractor = $denormalizerExtractor;
        $this->propertyAccessor = $propertyAccessor;
        $this->nameConverter = $nameConverter;
        $this->objectClassResolver = $objectClassReolver;
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        $object = $this->instantiator->instantiate($class, $data, $format, $context);
        $properties = $this->denormalizerExtractor->getProperties($class, $context);

        foreach ($data as $key => $value) {
            $key = $this->nameConverter ? $this->nameConverter->denormalize($key, $class, $format, $context) : $key;

            if (\in_array($key, $properties, true)) {
                $this->propertyAccessor->setValue($object, $key, $value);
            }
        }

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return \class_exists($type) && \is_array($data);
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $properties = $this->normalizerExtractor->getProperties($object, $context);
        $class = $this->objectClassResolver ? ($this->objectClassResolver)($object) : \get_class($object);
        $data = [];

        foreach ($properties as $property) {
            $propertyName = $this->nameConverter ? $this->nameConverter->normalize($property, $class, $format, $context) : $property;
            $value = $this->propertyAccessor->getValue($object, $property);

            if (!is_scalar($value)) {
                $value = $this->normalizer->normalize($value, $format, $context);
            }

            $data[$propertyName] = $value;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return \is_object($data) && !$data instanceof \Traversable;
    }
}
