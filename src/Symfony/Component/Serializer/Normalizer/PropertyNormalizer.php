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

use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Converts between objects and arrays by mapping properties.
 *
 * The normalization process looks for all the object's properties (public and private).
 * The result is a map from property names to property values. Property values
 * are normalized through the serializer.
 *
 * The denormalization first looks at the constructor of the given class to see
 * if any of the parameters have the same name as one of the properties. The
 * constructor is then called with all parameters or an exception is thrown if
 * any required parameters were not present as properties. Then the denormalizer
 * walks through the given map of property names to property values to see if a
 * property with the corresponding name exists. If found, the property gets the value.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final since Symfony 6.3
 */
class PropertyNormalizer extends AbstractObjectNormalizer
{
    public const NORMALIZE_PUBLIC = 1;
    public const NORMALIZE_PROTECTED = 2;
    public const NORMALIZE_PRIVATE = 4;

    /**
     * Flag to control whether fields should be output based on visibility.
     */
    public const NORMALIZE_VISIBILITY = 'normalize_visibility';

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory = null, NameConverterInterface $nameConverter = null, PropertyTypeExtractorInterface $propertyTypeExtractor = null, ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null, callable $objectClassResolver = null, array $defaultContext = [])
    {
        parent::__construct($classMetadataFactory, $nameConverter, $propertyTypeExtractor, $classDiscriminatorResolver, $objectClassResolver, $defaultContext);

        if (!isset($this->defaultContext[self::NORMALIZE_VISIBILITY])) {
            $this->defaultContext[self::NORMALIZE_VISIBILITY] = self::NORMALIZE_PUBLIC | self::NORMALIZE_PROTECTED | self::NORMALIZE_PRIVATE;
        }
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => __CLASS__ === static::class || $this->hasCacheableSupportsMethod()];
    }

    /**
     * @param array $context
     */
    public function supportsNormalization(mixed $data, string $format = null /* , array $context = [] */): bool
    {
        return parent::supportsNormalization($data, $format) && $this->supports($data::class);
    }

    /**
     * @param array $context
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null /* , array $context = [] */): bool
    {
        return parent::supportsDenormalization($data, $type, $format) && $this->supports($type);
    }

    /**
     * @deprecated since Symfony 6.3, use "getSupportedTypes()" instead
     */
    public function hasCacheableSupportsMethod(): bool
    {
        trigger_deprecation('symfony/serializer', '6.3', 'The "%s()" method is deprecated, implement "%s::getSupportedTypes()" instead.', __METHOD__, get_debug_type($this));

        return __CLASS__ === static::class;
    }

    /**
     * Checks if the given class has any non-static property.
     */
    private function supports(string $class): bool
    {
        if ($this->classDiscriminatorResolver?->getMappingForClass($class)) {
            return true;
        }

        $class = new \ReflectionClass($class);

        // We look for at least one non-static property
        do {
            foreach ($class->getProperties() as $property) {
                if (!$property->isStatic()) {
                    return true;
                }
            }
        } while ($class = $class->getParentClass());

        return false;
    }

    protected function isAllowedAttribute(object|string $classOrObject, string $attribute, string $format = null, array $context = []): bool
    {
        if (!parent::isAllowedAttribute($classOrObject, $attribute, $format, $context)) {
            return false;
        }

        try {
            $reflectionProperty = $this->getReflectionProperty($classOrObject, $attribute);
        } catch (\ReflectionException) {
            return false;
        }

        if ($reflectionProperty->isStatic()) {
            return false;
        }

        $normalizeVisibility = $context[self::NORMALIZE_VISIBILITY] ?? $this->defaultContext[self::NORMALIZE_VISIBILITY];

        if ((self::NORMALIZE_PUBLIC & $normalizeVisibility) && $reflectionProperty->isPublic()) {
            return true;
        }

        if ((self::NORMALIZE_PROTECTED & $normalizeVisibility) && $reflectionProperty->isProtected()) {
            return true;
        }

        if ((self::NORMALIZE_PRIVATE & $normalizeVisibility) && $reflectionProperty->isPrivate()) {
            return true;
        }

        return false;
    }

    protected function extractAttributes(object $object, string $format = null, array $context = []): array
    {
        $reflectionObject = new \ReflectionObject($object);
        $attributes = [];

        do {
            foreach ($reflectionObject->getProperties() as $property) {
                if (!$this->isAllowedAttribute($reflectionObject->getName(), $property->name, $format, $context)) {
                    continue;
                }

                $attributes[] = $property->name;
            }
        } while ($reflectionObject = $reflectionObject->getParentClass());

        return array_unique($attributes);
    }

    protected function getAttributeValue(object $object, string $attribute, string $format = null, array $context = []): mixed
    {
        try {
            $reflectionProperty = $this->getReflectionProperty($object, $attribute);
        } catch (\ReflectionException) {
            return null;
        }

        if ($reflectionProperty->hasType()) {
            return $reflectionProperty->getValue($object);
        }

        if (!method_exists($object, '__get') && !isset($object->$attribute)) {
            $propertyValues = (array) $object;

            if (($reflectionProperty->isPublic() && !\array_key_exists($reflectionProperty->name, $propertyValues))
                || ($reflectionProperty->isProtected() && !\array_key_exists("\0*\0{$reflectionProperty->name}", $propertyValues))
                || ($reflectionProperty->isPrivate() && !\array_key_exists("\0{$reflectionProperty->class}\0{$reflectionProperty->name}", $propertyValues))
            ) {
                throw new UninitializedPropertyException(sprintf('The property "%s::$%s" is not initialized.', $object::class, $reflectionProperty->name));
            }
        }

        return $reflectionProperty->getValue($object);
    }

    /**
     * @return void
     */
    protected function setAttributeValue(object $object, string $attribute, mixed $value, string $format = null, array $context = [])
    {
        try {
            $reflectionProperty = $this->getReflectionProperty($object, $attribute);
        } catch (\ReflectionException) {
            return;
        }

        if ($reflectionProperty->isStatic()) {
            return;
        }

        $reflectionProperty->setValue($object, $value);
    }

    /**
     * @throws \ReflectionException
     */
    private function getReflectionProperty(string|object $classOrObject, string $attribute): \ReflectionProperty
    {
        $reflectionClass = new \ReflectionClass($classOrObject);
        while (true) {
            try {
                return $reflectionClass->getProperty($attribute);
            } catch (\ReflectionException $e) {
                if (!$reflectionClass = $reflectionClass->getParentClass()) {
                    throw $e;
                }
            }
        }
    }
}
