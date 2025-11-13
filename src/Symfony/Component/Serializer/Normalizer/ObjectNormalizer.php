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
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Converts between objects and arrays using the PropertyAccess component.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class ObjectNormalizer extends AbstractObjectNormalizer
{
    private static $reflectionCache = [];
    private static $isReadableCache = [];
    private static $isWritableCache = [];

    protected PropertyAccessorInterface $propertyAccessor;
    protected $propertyInfoExtractor;
    private $writeInfoExtractor;

    private readonly \Closure $objectClassResolver;

    public function __construct(?ClassMetadataFactoryInterface $classMetadataFactory = null, ?NameConverterInterface $nameConverter = null, ?PropertyAccessorInterface $propertyAccessor = null, ?PropertyTypeExtractorInterface $propertyTypeExtractor = null, ?ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null, ?callable $objectClassResolver = null, array $defaultContext = [], ?PropertyInfoExtractorInterface $propertyInfoExtractor = null)
    {
        if (!class_exists(PropertyAccess::class)) {
            throw new LogicException('The ObjectNormalizer class requires the "PropertyAccess" component. Try running "composer require symfony/property-access".');
        }

        parent::__construct($classMetadataFactory, $nameConverter, $propertyTypeExtractor, $classDiscriminatorResolver, $objectClassResolver, $defaultContext);

        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();

        $this->objectClassResolver = ($objectClassResolver ?? static fn ($class) => \is_object($class) ? $class::class : $class)(...);
        $this->propertyInfoExtractor = $propertyInfoExtractor ?: new ReflectionExtractor();
        $this->writeInfoExtractor = new ReflectionExtractor();
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => true];
    }

    protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
    {
        if (\stdClass::class === $object::class) {
            return array_keys((array) $object);
        }

        // If not using groups, detect manually
        $attributes = [];

        // methods
        $class = ($this->objectClassResolver)($object);
        $reflClass = new \ReflectionClass($class);

        foreach ($reflClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflMethod) {
            if (
                0 !== $reflMethod->getNumberOfRequiredParameters()
                || $reflMethod->isStatic()
                || $reflMethod->isConstructor()
                || $reflMethod->isDestructor()
            ) {
                continue;
            }

            $name = $reflMethod->name;
            $attributeName = null;

            // ctype_lower check to find out if method looks like accessor but actually is not, e.g. hash, cancel
            if (match ($name[0]) {
                'g' => str_starts_with($name, 'get') && isset($name[$i = 3]),
                'h' => str_starts_with($name, 'has') && isset($name[$i = 3]),
                'c' => str_starts_with($name, 'can') && isset($name[$i = 3]),
                'i' => str_starts_with($name, 'is') && isset($name[$i = 2]),
                default => false,
            } && !ctype_lower($name[$i])) {
                if ($this->hasProperty($reflMethod, $name)) {
                    $attributeName = $name;
                } else {
                    $attributeName = substr($name, $i);

                    if (!$reflClass->hasProperty($attributeName)) {
                        $attributeName = lcfirst($attributeName);
                    }
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

    private function hasProperty(\ReflectionMethod $method, string $propName): bool
    {
        $class = $method->getDeclaringClass();

        do {
            if ($class->hasProperty($propName)) {
                return true;
            }
        } while ($class = $class->getParentClass());

        return false;
    }

    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
    {
        $mapping = $this->classDiscriminatorResolver?->getMappingForMappedObject($object);

        return $attribute === $mapping?->getTypeProperty()
            ? $mapping
            : $this->propertyAccessor->getValue($object, $attribute);
    }

    protected function setAttributeValue(object $object, string $attribute, mixed $value, ?string $format = null, array $context = []): void
    {
        try {
            $this->propertyAccessor->setValue($object, $attribute, $value);
        } catch (NoSuchPropertyException) {
            // Properties not found are ignored
        }
    }

    protected function isAllowedAttribute($classOrObject, string $attribute, ?string $format = null, array $context = []): bool
    {
        if (!parent::isAllowedAttribute($classOrObject, $attribute, $format, $context)) {
            return false;
        }

        $class = \is_object($classOrObject) ? $classOrObject::class : $classOrObject;

        if ($this->classDiscriminatorResolver?->getMappingForMappedObject($classOrObject)?->getTypeProperty() === $attribute) {
            return true;
        }

        if ($context['_read_attributes'] ?? true) {
            if (!isset(self::$isReadableCache[$class.$attribute])) {
                self::$isReadableCache[$class.$attribute] = $this->propertyInfoExtractor->isReadable($class, $attribute) || $this->hasAttributeAccessorMethod($class, $attribute) || (\is_object($classOrObject) && $this->propertyAccessor->isReadable($classOrObject, $attribute));
            }

            return self::$isReadableCache[$class.$attribute];
        }

        return self::$isWritableCache[$class.$attribute] ??= str_contains($attribute, '.')
            || $this->propertyInfoExtractor->isWritable($class, $attribute)
            || !\in_array($this->writeInfoExtractor->getWriteInfo($class, $attribute)?->getType(), [null, PropertyWriteInfo::TYPE_NONE, PropertyWriteInfo::TYPE_PROPERTY], true);
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
            && !$method->getAttributes(Ignore::class)
            && !$method->getNumberOfRequiredParameters();
    }
}
