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

use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Converts between objects and arrays using the PropertyAccess component.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ObjectNormalizer extends AbstractObjectNormalizer
{
    private static $reflectionCache = [];

    protected $propertyAccessor;
    protected $propertyInfoExtractor;
    private $writeInfoExtractor;

    private $objectClassResolver;

    public function __construct(?ClassMetadataFactoryInterface $classMetadataFactory = null, ?NameConverterInterface $nameConverter = null, ?PropertyAccessorInterface $propertyAccessor = null, ?PropertyTypeExtractorInterface $propertyTypeExtractor = null, ?ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null, ?callable $objectClassResolver = null, array $defaultContext = [], ?PropertyInfoExtractorInterface $propertyInfoExtractor = null)
    {
        if (!class_exists(PropertyAccess::class)) {
            throw new LogicException('The ObjectNormalizer class requires the "PropertyAccess" component. Install "symfony/property-access" to use it.');
        }

        parent::__construct($classMetadataFactory, $nameConverter, $propertyTypeExtractor, $classDiscriminatorResolver, $objectClassResolver, $defaultContext);

        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();

        $this->objectClassResolver = $objectClassResolver ?? function ($class) {
            return \is_object($class) ? \get_class($class) : $class;
        };

        $this->propertyInfoExtractor = $propertyInfoExtractor ?: new ReflectionExtractor();
        $this->writeInfoExtractor = new ReflectionExtractor();
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }

    /**
     * {@inheritdoc}
     */
    protected function extractAttributes(object $object, ?string $format = null, array $context = [])
    {
        if (\stdClass::class === \get_class($object)) {
            return array_keys((array) $object);
        }

        // If not using groups, detect manually
        $attributes = [];

        // methods
        $class = ($this->objectClassResolver)($object);
        $reflClass = new \ReflectionClass($class);

        foreach ($reflClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflMethod) {
            if (
                0 !== $reflMethod->getNumberOfRequiredParameters() ||
                $reflMethod->isStatic() ||
                $reflMethod->isConstructor() ||
                $reflMethod->isDestructor()
            ) {
                continue;
            }

            $name = $reflMethod->name;
            $attributeName = null;

            if (str_starts_with($name, 'get') || str_starts_with($name, 'has')) {
                // getters and hassers
                $attributeName = substr($name, 3);

                if (!$reflClass->hasProperty($attributeName)) {
                    $attributeName = lcfirst($attributeName);
                }
            } elseif (str_starts_with($name, 'is')) {
                // issers
                $attributeName = substr($name, 2);

                if (!$reflClass->hasProperty($attributeName)) {
                    $attributeName = lcfirst($attributeName);
                }
            }

            if (null !== $attributeName && $this->isAllowedAttribute($object, $attributeName, $format, $context)) {
                $attributes[$attributeName] = true;
            }
        }

        // properties
        foreach ($reflClass->getProperties() as $reflProperty) {
            if (!$reflProperty->isPublic()) {
                continue;
            }

            if ($reflProperty->isStatic() || !$this->isAllowedAttribute($object, $reflProperty->name, $format, $context)) {
                continue;
            }

            $attributes[$reflProperty->name] = true;
        }

        return array_keys($attributes);
    }

    /**
     * {@inheritdoc}
     */
    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = [])
    {
        $discriminatorProperty = null;
        if (null !== $this->classDiscriminatorResolver && null !== $mapping = $this->classDiscriminatorResolver->getMappingForMappedObject($object)) {
            $discriminatorProperty = $mapping->getTypeProperty();
        }

        return $attribute === $discriminatorProperty
            ? $this->classDiscriminatorResolver->getTypeForMappedObject($object)
            : $this->propertyAccessor->getValue($object, $attribute);
    }

    /**
     * {@inheritdoc}
     */
    protected function setAttributeValue(object $object, string $attribute, $value, ?string $format = null, array $context = [])
    {
        if ($writeInfo = $this->getWriteInfoInsteadOfGetterSetter($object, $attribute, $value)) {
            if (PropertyWriteInfo::TYPE_PROPERTY === $writeInfo->getType()) {
                $object->{$writeInfo->getName()} = $value;
            }

            return;
        }

        try {
            $this->propertyAccessor->setValue($object, $attribute, $value);
        } catch (NoSuchPropertyException $exception) {
            // Properties not found are ignored
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedAttributes($classOrObject, array $context, bool $attributesAsString = false)
    {
        if (false === $allowedAttributes = parent::getAllowedAttributes($classOrObject, $context, $attributesAsString)) {
            return false;
        }

        if (null !== $this->classDiscriminatorResolver) {
            $class = \is_object($classOrObject) ? \get_class($classOrObject) : $classOrObject;
            if (null !== $discriminatorMapping = $this->classDiscriminatorResolver->getMappingForMappedObject($classOrObject)) {
                $allowedAttributes[] = $attributesAsString ? $discriminatorMapping->getTypeProperty() : new AttributeMetadata($discriminatorMapping->getTypeProperty());
            }

            if (null !== $discriminatorMapping = $this->classDiscriminatorResolver->getMappingForClass($class)) {
                $attributes = [];
                foreach ($discriminatorMapping->getTypesMapping() as $mappedClass) {
                    $attributes[] = parent::getAllowedAttributes($mappedClass, $context, $attributesAsString);
                }
                $allowedAttributes = array_merge($allowedAttributes, ...$attributes);
            }
        }

        return $allowedAttributes;
    }

    protected function isAllowedAttribute($classOrObject, string $attribute, ?string $format = null, array $context = [])
    {
        if (!parent::isAllowedAttribute($classOrObject, $attribute, $format, $context)) {
            return false;
        }
        $class = \is_object($classOrObject) ? \get_class($classOrObject) : $classOrObject;

        if ($context['_read_attributes'] ?? true) {
            return (\is_object($classOrObject) && $this->propertyAccessor->isReadable($classOrObject, $attribute)) || $this->propertyInfoExtractor->isReadable($class, $attribute) || $this->hasAttributeAccessorMethod($class, $attribute);
        }

        if (str_contains($attribute, '.') || str_contains($attribute, '[')) {
            // a property path is writable only when the class metadata declares it as an attribute
            return $this->classMetadataFactory && isset($this->classMetadataFactory->getMetadataFor($class)->getAttributesMetadata()[$attribute]);
        }

        if ($ignoredAttributes = $context[self::IGNORED_ATTRIBUTES] ?? $this->defaultContext[self::IGNORED_ATTRIBUTES]) {
            $foldedAttribute = strtolower(str_replace(['_', ' '], '', $attribute));
            foreach ($ignoredAttributes as $ignoredAttribute) {
                if ($foldedAttribute === strtolower(str_replace(['_', ' '], '', $ignoredAttribute)) && $this->isWrittenByMethod($class, $attribute)) {
                    return false;
                }
            }
        }

        // asking PropertyInfo about attributes that the class doesn't have would fill its cache with each unknown key
        if ((property_exists($class, $attribute) || method_exists($class, 'set'.$attribute)) && $this->propertyInfoExtractor->isWritable($class, $attribute)) {
            return true;
        }

        if (($writeInfo = $this->writeInfoExtractor->getWriteInfo($class, $attribute)) && PropertyWriteInfo::TYPE_NONE !== $writeInfo->getType()) {
            return true;
        }

        return false;
    }

    private function isWrittenByMethod(string $class, string $attribute): bool
    {
        if (!$writeInfo = $this->writeInfoExtractor->getWriteInfo($class, $attribute, ['enable_constructor_extraction' => false])) {
            return false;
        }

        if (PropertyWriteInfo::TYPE_METHOD === $writeInfo->getType()) {
            return method_exists($class, $writeInfo->getName());
        }

        return PropertyWriteInfo::TYPE_ADDER_AND_REMOVER === $writeInfo->getType();
    }

    private function hasAttributeAccessorMethod(string $class, string $attribute): bool
    {
        if (!isset(self::$reflectionCache[$class])) {
            self::$reflectionCache[$class] = new \ReflectionClass($class);
        }

        $reflection = self::$reflectionCache[$class];

        if (!$reflection->hasMethod($attribute)) {
            return false;
        }

        $method = $reflection->getMethod($attribute);

        return !$method->isStatic()
            && (\PHP_VERSION_ID < 80000 || !$method->getAttributes(Ignore::class))
            && !$method->getNumberOfRequiredParameters();
    }

    /**
     * Returns how to write the attribute when PropertyAccessor would call a method named after it, like name($value), that isAllowedAttribute() doesn't account for.
     */
    private function getWriteInfoInsteadOfGetterSetter(object $object, string $attribute, $value): ?PropertyWriteInfo
    {
        $method = str_replace(['_', ' '], '', $attribute);

        if (!method_exists($object, $method) || !(new \ReflectionMethod($object, $method))->getNumberOfParameters()) {
            return null;
        }

        $context = ['enable_constructor_extraction' => false, 'enable_adder_remover_extraction' => is_iterable($value)];
        $writeInfo = $this->writeInfoExtractor->getWriteInfo(\get_class($object), $attribute, ['enable_getter_setter_extraction' => true] + $context);

        if (PropertyWriteInfo::TYPE_METHOD !== $writeInfo->getType()) {
            return null;
        }

        $writeInfoWithoutGetterSetter = $this->writeInfoExtractor->getWriteInfo(\get_class($object), $attribute, $context);

        if (PropertyWriteInfo::TYPE_METHOD === $writeInfoWithoutGetterSetter->getType() && $writeInfo->getName() === $writeInfoWithoutGetterSetter->getName()) {
            return null;
        }

        return $writeInfoWithoutGetterSetter;
    }
}
