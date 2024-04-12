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

use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException as PropertyAccessInvalidArgumentException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Base class for a normalizer dealing with objects.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
abstract class AbstractObjectNormalizer extends AbstractNormalizer
{
    /**
     * Set to true to respect the max depth metadata on fields.
     */
    public const ENABLE_MAX_DEPTH = 'enable_max_depth';

    /**
     * How to track the current depth in the context.
     */
    public const DEPTH_KEY_PATTERN = 'depth_%s::%s';

    /**
     * While denormalizing, we can verify that types match.
     *
     * You can disable this by setting this flag to true.
     */
    public const DISABLE_TYPE_ENFORCEMENT = 'disable_type_enforcement';

    /**
     * Flag to control whether fields with the value `null` should be output
     * when normalizing or omitted.
     */
    public const SKIP_NULL_VALUES = 'skip_null_values';

    /**
     * Flag to control whether uninitialized PHP>=7.4 typed class properties
     * should be excluded when normalizing.
     */
    public const SKIP_UNINITIALIZED_VALUES = 'skip_uninitialized_values';

    /**
     * Callback to allow to set a value for an attribute when the max depth has
     * been reached.
     *
     * If no callback is given, the attribute is skipped. If a callable is
     * given, its return value is used (even if null).
     *
     * The arguments are:
     *
     * - mixed  $attributeValue value of this field
     * - object $object         the whole object being normalized
     * - string $attributeName  name of the attribute being normalized
     * - string $format         the requested format
     * - array  $context        the serialization context
     */
    public const MAX_DEPTH_HANDLER = 'max_depth_handler';

    /**
     * Specify which context key are not relevant to determine which attributes
     * of an object to (de)normalize.
     */
    public const EXCLUDE_FROM_CACHE_KEY = 'exclude_from_cache_key';

    /**
     * Flag to tell the denormalizer to also populate existing objects on
     * attributes of the main object.
     *
     * Setting this to true is only useful if you also specify the root object
     * in OBJECT_TO_POPULATE.
     */
    public const DEEP_OBJECT_TO_POPULATE = 'deep_object_to_populate';

    /**
     * Flag to control whether an empty object should be kept as an object (in
     * JSON: {}) or converted to a list (in JSON: []).
     */
    public const PRESERVE_EMPTY_OBJECTS = 'preserve_empty_objects';

    private array $typesCache = [];
    private array $attributesCache = [];
    private readonly \Closure $objectClassResolver;

    /**
     * @var ClassDiscriminatorResolverInterface|null
     */
    protected $classDiscriminatorResolver;

    public function __construct(
        ?ClassMetadataFactoryInterface $classMetadataFactory = null,
        ?NameConverterInterface $nameConverter = null,
        private ?PropertyTypeExtractorInterface $propertyTypeExtractor = null,
        ?ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null,
        ?callable $objectClassResolver = null,
        array $defaultContext = [],
    ) {
        parent::__construct($classMetadataFactory, $nameConverter, $defaultContext);

        if (isset($this->defaultContext[self::MAX_DEPTH_HANDLER]) && !\is_callable($this->defaultContext[self::MAX_DEPTH_HANDLER])) {
            throw new InvalidArgumentException(sprintf('The "%s" given in the default context is not callable.', self::MAX_DEPTH_HANDLER));
        }

        $this->defaultContext[self::EXCLUDE_FROM_CACHE_KEY] = array_merge($this->defaultContext[self::EXCLUDE_FROM_CACHE_KEY] ?? [], [self::CIRCULAR_REFERENCE_LIMIT_COUNTERS]);

        if ($classMetadataFactory) {
            $classDiscriminatorResolver ??= new ClassDiscriminatorFromClassMetadata($classMetadataFactory);
        }
        $this->classDiscriminatorResolver = $classDiscriminatorResolver;
        $this->objectClassResolver = ($objectClassResolver ?? 'get_class')(...);
    }

    /**
     * @param array $context
     *
     * @return bool
     */
    public function supportsNormalization(mixed $data, ?string $format = null /* , array $context = [] */)
    {
        return \is_object($data) && !$data instanceof \Traversable;
    }

    /**
     * @return array|string|int|float|bool|\ArrayObject|null
     */
    public function normalize(mixed $object, ?string $format = null, array $context = [])
    {
        $context['_read_attributes'] = true;

        if (!isset($context['cache_key'])) {
            $context['cache_key'] = $this->getCacheKey($format, $context);
        }

        $this->validateCallbackContext($context);

        if ($this->isCircularReference($object, $context)) {
            return $this->handleCircularReference($object, $format, $context);
        }

        $data = [];
        $stack = [];
        $attributes = $this->getAttributes($object, $format, $context);
        $class = ($this->objectClassResolver)($object);
        $classMetadata = $this->classMetadataFactory?->getMetadataFor($class);
        $attributesMetadata = $this->classMetadataFactory?->getMetadataFor($class)->getAttributesMetadata();
        if (isset($context[self::MAX_DEPTH_HANDLER])) {
            $maxDepthHandler = $context[self::MAX_DEPTH_HANDLER];
            if (!\is_callable($maxDepthHandler)) {
                throw new InvalidArgumentException(sprintf('The "%s" given in the context is not callable.', self::MAX_DEPTH_HANDLER));
            }
        } else {
            $maxDepthHandler = null;
        }

        foreach ($attributes as $attribute) {
            $maxDepthReached = false;
            if (null !== $attributesMetadata && ($maxDepthReached = $this->isMaxDepthReached($attributesMetadata, $class, $attribute, $context)) && !$maxDepthHandler) {
                continue;
            }

            $attributeContext = $this->getAttributeNormalizationContext($object, $attribute, $context);

            try {
                $attributeValue = $attribute === $this->classDiscriminatorResolver?->getMappingForMappedObject($object)?->getTypeProperty()
                    ? $this->classDiscriminatorResolver?->getTypeForMappedObject($object)
                    : $this->getAttributeValue($object, $attribute, $format, $attributeContext);
            } catch (UninitializedPropertyException|\Error $e) {
                if (($context[self::SKIP_UNINITIALIZED_VALUES] ?? $this->defaultContext[self::SKIP_UNINITIALIZED_VALUES] ?? true) && $this->isUninitializedValueError($e)) {
                    continue;
                }
                throw $e;
            }

            if ($maxDepthReached) {
                $attributeValue = $maxDepthHandler($attributeValue, $object, $attribute, $format, $attributeContext);
            }

            $stack[$attribute] = $this->applyCallbacks($attributeValue, $object, $attribute, $format, $attributeContext);
        }

        foreach ($stack as $attribute => $attributeValue) {
            $attributeContext = $this->getAttributeNormalizationContext($object, $attribute, $context);

            if (null === $attributeValue || \is_scalar($attributeValue)) {
                $data = $this->updateData($data, $attribute, $attributeValue, $class, $format, $attributeContext, $attributesMetadata, $classMetadata);
                continue;
            }

            if (!$this->serializer instanceof NormalizerInterface) {
                throw new LogicException(sprintf('Cannot normalize attribute "%s" because the injected serializer is not a normalizer.', $attribute));
            }

            $childContext = $this->createChildContext($attributeContext, $attribute, $format);

            $data = $this->updateData($data, $attribute, $this->serializer->normalize($attributeValue, $format, $childContext), $class, $format, $attributeContext, $attributesMetadata, $classMetadata);
        }

        $preserveEmptyObjects = $context[self::PRESERVE_EMPTY_OBJECTS] ?? $this->defaultContext[self::PRESERVE_EMPTY_OBJECTS] ?? false;
        if ($preserveEmptyObjects && !$data) {
            return new \ArrayObject();
        }

        return $data;
    }

    /**
     * @return object
     */
    protected function instantiateObject(array &$data, string $class, array &$context, \ReflectionClass $reflectionClass, array|bool $allowedAttributes, ?string $format = null)
    {
        if ($class !== $mappedClass = $this->getMappedClass($data, $class, $context)) {
            return $this->instantiateObject($data, $mappedClass, $context, new \ReflectionClass($mappedClass), $allowedAttributes, $format);
        }

        return parent::instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes, $format);
    }

    /**
     * Gets and caches attributes for the given object, format and context.
     *
     * @return string[]
     */
    protected function getAttributes(object $object, ?string $format, array $context): array
    {
        $class = ($this->objectClassResolver)($object);
        $key = $class.'-'.$context['cache_key'];

        if (isset($this->attributesCache[$key])) {
            return $this->attributesCache[$key];
        }

        $allowedAttributes = $this->getAllowedAttributes($object, $context, true);

        if (false !== $allowedAttributes) {
            if ($context['cache_key']) {
                $this->attributesCache[$key] = $allowedAttributes;
            }

            return $allowedAttributes;
        }

        $attributes = $this->extractAttributes($object, $format, $context);

        if ($mapping = $this->classDiscriminatorResolver?->getMappingForMappedObject($object)) {
            array_unshift($attributes, $mapping->getTypeProperty());
        }

        if ($context['cache_key'] && \stdClass::class !== $class) {
            $this->attributesCache[$key] = $attributes;
        }

        return $attributes;
    }

    /**
     * Extracts attributes to normalize from the class of the given object, format and context.
     *
     * @return string[]
     */
    abstract protected function extractAttributes(object $object, ?string $format = null, array $context = []);

    /**
     * Gets the attribute value.
     *
     * @return mixed
     */
    abstract protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []);

    /**
     * @param array $context
     *
     * @return bool
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null /* , array $context = [] */)
    {
        return class_exists($type) || (interface_exists($type, false) && null !== $this->classDiscriminatorResolver?->getMappingForClass($type));
    }

    /**
     * @return mixed
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = [])
    {
        $context['_read_attributes'] = false;

        if (!isset($context['cache_key'])) {
            $context['cache_key'] = $this->getCacheKey($format, $context);
        }

        $this->validateCallbackContext($context);

        if (null === $data && isset($context['value_type']) && $context['value_type'] instanceof Type && $context['value_type']->isNullable()) {
            return null;
        }

        if (XmlEncoder::FORMAT === $format && !\is_array($data)) {
            $data = ['#' => $data];
        }

        $allowedAttributes = $this->getAllowedAttributes($type, $context, true);
        $normalizedData = $this->prepareForDenormalization($data);
        $extraAttributes = [];

        $mappedClass = $this->getMappedClass($normalizedData, $type, $context);

        $nestedAttributes = $this->getNestedAttributes($mappedClass);
        $nestedData = $originalNestedData = [];
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach ($nestedAttributes as $property => $serializedPath) {
            if (null === $value = $propertyAccessor->getValue($normalizedData, $serializedPath)) {
                continue;
            }
            $convertedProperty = $this->nameConverter ? $this->nameConverter->normalize($property, $mappedClass, $format, $context) : $property;
            $nestedData[$convertedProperty] = $value;
            $originalNestedData[$property] = $value;
            $normalizedData = $this->removeNestedValue($serializedPath->getElements(), $normalizedData);
        }

        $normalizedData = $nestedData + $normalizedData;

        $object = $this->instantiateObject($normalizedData, $mappedClass, $context, new \ReflectionClass($mappedClass), $allowedAttributes, $format);
        $resolvedClass = ($this->objectClassResolver)($object);

        foreach ($normalizedData as $attribute => $value) {
            if ($this->nameConverter) {
                $notConverted = $attribute;
                $attribute = $this->nameConverter->denormalize($attribute, $resolvedClass, $format, $context);
                if (isset($nestedData[$notConverted]) && !isset($originalNestedData[$attribute])) {
                    throw new LogicException(sprintf('Duplicate values for key "%s" found. One value is set via the SerializedPath attribute: "%s", the other one is set via the SerializedName attribute: "%s".', $notConverted, implode('->', $nestedAttributes[$notConverted]->getElements()), $attribute));
                }
            }

            $attributeContext = $this->getAttributeDenormalizationContext($resolvedClass, $attribute, $context);

            if ((false !== $allowedAttributes && !\in_array($attribute, $allowedAttributes)) || !$this->isAllowedAttribute($resolvedClass, $attribute, $format, $context)) {
                if (!($context[self::ALLOW_EXTRA_ATTRIBUTES] ?? $this->defaultContext[self::ALLOW_EXTRA_ATTRIBUTES])) {
                    $extraAttributes[] = $attribute;
                }

                continue;
            }

            if ($attributeContext[self::DEEP_OBJECT_TO_POPULATE] ?? $this->defaultContext[self::DEEP_OBJECT_TO_POPULATE] ?? false) {
                $discriminatorMapping = $this->classDiscriminatorResolver?->getMappingForMappedObject($object);

                try {
                    $attributeContext[self::OBJECT_TO_POPULATE] = $attribute === $discriminatorMapping?->getTypeProperty()
                        ? $discriminatorMapping
                        : $this->getAttributeValue($object, $attribute, $format, $attributeContext);
                } catch (NoSuchPropertyException) {
                } catch (UninitializedPropertyException|\Error $e) {
                    if (!(($context[self::SKIP_UNINITIALIZED_VALUES] ?? $this->defaultContext[self::SKIP_UNINITIALIZED_VALUES] ?? true) && $this->isUninitializedValueError($e))) {
                        throw $e;
                    }
                }
            }

            $types = $this->getTypes($resolvedClass, $attribute);

            if (null !== $types) {
                try {
                    $value = $this->validateAndDenormalize($types, $resolvedClass, $attribute, $value, $format, $attributeContext);
                } catch (NotNormalizableValueException $exception) {
                    if (isset($context['not_normalizable_value_exceptions'])) {
                        $context['not_normalizable_value_exceptions'][] = $exception;
                        continue;
                    }
                    throw $exception;
                }
            }

            $value = $this->applyCallbacks($value, $resolvedClass, $attribute, $format, $attributeContext);

            try {
                $this->setAttributeValue($object, $attribute, $value, $format, $attributeContext);
            } catch (PropertyAccessInvalidArgumentException $e) {
                $exception = NotNormalizableValueException::createForUnexpectedDataType(
                    sprintf('Failed to denormalize attribute "%s" value for class "%s": '.$e->getMessage(), $attribute, $type),
                    $data,
                    ['unknown'],
                    $attributeContext['deserialization_path'] ?? null,
                    false,
                    $e->getCode(),
                    $e
                );
                if (isset($context['not_normalizable_value_exceptions'])) {
                    $context['not_normalizable_value_exceptions'][] = $exception;
                    continue;
                }
                throw $exception;
            }
        }

        if ($extraAttributes) {
            throw new ExtraAttributesException($extraAttributes);
        }

        return $object;
    }

    /**
     * @return void
     */
    abstract protected function setAttributeValue(object $object, string $attribute, mixed $value, ?string $format = null, array $context = []);

    /**
     * Validates the submitted data and denormalizes it.
     *
     * @param Type[] $types
     *
     * @throws NotNormalizableValueException
     * @throws ExtraAttributesException
     * @throws MissingConstructorArgumentsException
     * @throws LogicException
     */
    private function validateAndDenormalize(array $types, string $currentClass, string $attribute, mixed $data, ?string $format, array $context): mixed
    {
        $expectedTypes = [];
        $isUnionType = \count($types) > 1;
        $e = null;
        $extraAttributesException = null;
        $missingConstructorArgumentsException = null;
        $isNullable = false;
        foreach ($types as $type) {
            if (null === $data && $type->isNullable()) {
                return null;
            }

            $collectionValueType = $type->isCollection() ? $type->getCollectionValueTypes()[0] ?? null : null;

            // Fix a collection that contains the only one element
            // This is special to xml format only
            if ('xml' === $format && null !== $collectionValueType && (!\is_array($data) || !\is_int(key($data)))) {
                $data = [$data];
            }

            // This try-catch should cover all NotNormalizableValueException (and all return branches after the first
            // exception) so we could try denormalizing all types of an union type. If the target type is not an union
            // type, we will just re-throw the catched exception.
            // In the case of no denormalization succeeds with an union type, it will fall back to the default exception
            // with the acceptable types list.
            try {
                // In XML and CSV all basic datatypes are represented as strings, it is e.g. not possible to determine,
                // if a value is meant to be a string, float, int or a boolean value from the serialized representation.
                // That's why we have to transform the values, if one of these non-string basic datatypes is expected.
                $builtinType = $type->getBuiltinType();
                if (\is_string($data) && (XmlEncoder::FORMAT === $format || CsvEncoder::FORMAT === $format)) {
                    if ('' === $data) {
                        if (Type::BUILTIN_TYPE_ARRAY === $builtinType) {
                            return [];
                        }

                        if (Type::BUILTIN_TYPE_STRING === $builtinType) {
                            return '';
                        }

                        // Don't return null yet because Object-types that come first may accept empty-string too
                        $isNullable = $isNullable ?: $type->isNullable();
                    }

                    switch ($builtinType) {
                        case Type::BUILTIN_TYPE_BOOL:
                            // according to https://www.w3.org/TR/xmlschema-2/#boolean, valid representations are "false", "true", "0" and "1"
                            if ('false' === $data || '0' === $data) {
                                $data = false;
                            } elseif ('true' === $data || '1' === $data) {
                                $data = true;
                            } else {
                                throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('The type of the "%s" attribute for class "%s" must be bool ("%s" given).', $attribute, $currentClass, $data), $data, [Type::BUILTIN_TYPE_BOOL], $context['deserialization_path'] ?? null);
                            }
                            break;
                        case Type::BUILTIN_TYPE_INT:
                            if (ctype_digit(isset($data[0]) && '-' === $data[0] ? substr($data, 1) : $data)) {
                                $data = (int) $data;
                            } else {
                                throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('The type of the "%s" attribute for class "%s" must be int ("%s" given).', $attribute, $currentClass, $data), $data, [Type::BUILTIN_TYPE_INT], $context['deserialization_path'] ?? null);
                            }
                            break;
                        case Type::BUILTIN_TYPE_FLOAT:
                            if (is_numeric($data)) {
                                return (float) $data;
                            }

                            return match ($data) {
                                'NaN' => \NAN,
                                'INF' => \INF,
                                '-INF' => -\INF,
                                default => throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('The type of the "%s" attribute for class "%s" must be float ("%s" given).', $attribute, $currentClass, $data), $data, [Type::BUILTIN_TYPE_FLOAT], $context['deserialization_path'] ?? null),
                            };
                    }
                }

                if (null !== $collectionValueType && Type::BUILTIN_TYPE_OBJECT === $collectionValueType->getBuiltinType()) {
                    $builtinType = Type::BUILTIN_TYPE_OBJECT;
                    $class = $collectionValueType->getClassName().'[]';

                    if (\count($collectionKeyType = $type->getCollectionKeyTypes()) > 0) {
                        $context['key_type'] = \count($collectionKeyType) > 1 ? $collectionKeyType : $collectionKeyType[0];
                    }

                    $context['value_type'] = $collectionValueType;
                } elseif ($type->isCollection() && \count($collectionValueType = $type->getCollectionValueTypes()) > 0 && Type::BUILTIN_TYPE_ARRAY === $collectionValueType[0]->getBuiltinType()) {
                    // get inner type for any nested array
                    [$innerType] = $collectionValueType;

                    // note that it will break for any other builtinType
                    $dimensions = '[]';
                    while (\count($innerType->getCollectionValueTypes()) > 0 && Type::BUILTIN_TYPE_ARRAY === $innerType->getBuiltinType()) {
                        $dimensions .= '[]';
                        [$innerType] = $innerType->getCollectionValueTypes();
                    }

                    if (null !== $innerType->getClassName()) {
                        // the builtinType is the inner one and the class is the class followed by []...[]
                        $builtinType = $innerType->getBuiltinType();
                        $class = $innerType->getClassName().$dimensions;
                    } else {
                        // default fallback (keep it as array)
                        $builtinType = $type->getBuiltinType();
                        $class = $type->getClassName();
                    }
                } else {
                    $builtinType = $type->getBuiltinType();
                    $class = $type->getClassName();
                }

                $expectedTypes[Type::BUILTIN_TYPE_OBJECT === $builtinType && $class ? $class : $builtinType] = true;

                if (Type::BUILTIN_TYPE_OBJECT === $builtinType && null !== $class) {
                    if (!$this->serializer instanceof DenormalizerInterface) {
                        throw new LogicException(sprintf('Cannot denormalize attribute "%s" for class "%s" because injected serializer is not a denormalizer.', $attribute, $class));
                    }

                    $childContext = $this->createChildContext($context, $attribute, $format);
                    if ($this->serializer->supportsDenormalization($data, $class, $format, $childContext)) {
                        return $this->serializer->denormalize($data, $class, $format, $childContext);
                    }
                }

                // JSON only has a Number type corresponding to both int and float PHP types.
                // PHP's json_encode, JavaScript's JSON.stringify, Go's json.Marshal as well as most other JSON encoders convert
                // floating-point numbers like 12.0 to 12 (the decimal part is dropped when possible).
                // PHP's json_decode automatically converts Numbers without a decimal part to integers.
                // To circumvent this behavior, integers are converted to floats when denormalizing JSON based formats and when
                // a float is expected.
                if (Type::BUILTIN_TYPE_FLOAT === $builtinType && \is_int($data) && null !== $format && str_contains($format, JsonEncoder::FORMAT)) {
                    return (float) $data;
                }

                if ((Type::BUILTIN_TYPE_FALSE === $builtinType && false === $data) || (Type::BUILTIN_TYPE_TRUE === $builtinType && true === $data)) {
                    return $data;
                }

                if (('is_'.$builtinType)($data)) {
                    return $data;
                }
            } catch (NotNormalizableValueException|InvalidArgumentException $e) {
                if (!$isUnionType && !$isNullable) {
                    throw $e;
                }
            } catch (ExtraAttributesException $e) {
                if (!$isUnionType && !$isNullable) {
                    throw $e;
                }

                $extraAttributesException ??= $e;
            } catch (MissingConstructorArgumentsException $e) {
                if (!$isUnionType && !$isNullable) {
                    throw $e;
                }

                $missingConstructorArgumentsException ??= $e;
            }
        }

        if ($isNullable) {
            return null;
        }

        if ($extraAttributesException) {
            throw $extraAttributesException;
        }

        if ($missingConstructorArgumentsException) {
            throw $missingConstructorArgumentsException;
        }

        if (!$isUnionType && $e) {
            throw $e;
        }

        if ($context[self::DISABLE_TYPE_ENFORCEMENT] ?? $this->defaultContext[self::DISABLE_TYPE_ENFORCEMENT] ?? false) {
            return $data;
        }

        throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('The type of the "%s" attribute for class "%s" must be one of "%s" ("%s" given).', $attribute, $currentClass, implode('", "', array_keys($expectedTypes)), get_debug_type($data)), $data, array_keys($expectedTypes), $context['deserialization_path'] ?? $attribute);
    }

    /**
     * @internal
     */
    protected function denormalizeParameter(\ReflectionClass $class, \ReflectionParameter $parameter, string $parameterName, mixed $parameterData, array $context, ?string $format = null): mixed
    {
        if ($parameter->isVariadic() || null === $this->propertyTypeExtractor || null === $types = $this->getTypes($class->getName(), $parameterName)) {
            return parent::denormalizeParameter($class, $parameter, $parameterName, $parameterData, $context, $format);
        }

        $parameterData = $this->validateAndDenormalize($types, $class->getName(), $parameterName, $parameterData, $format, $context);

        return $this->applyCallbacks($parameterData, $class->getName(), $parameterName, $format, $context);
    }

    /**
     * @return Type[]|null
     */
    private function getTypes(string $currentClass, string $attribute): ?array
    {
        if (null === $this->propertyTypeExtractor) {
            return null;
        }

        $key = $currentClass.'::'.$attribute;
        if (isset($this->typesCache[$key])) {
            return false === $this->typesCache[$key] ? null : $this->typesCache[$key];
        }

        if (null !== $types = $this->propertyTypeExtractor->getTypes($currentClass, $attribute)) {
            return $this->typesCache[$key] = $types;
        }

        if ($discriminatorMapping = $this->classDiscriminatorResolver?->getMappingForClass($currentClass)) {
            if ($discriminatorMapping->getTypeProperty() === $attribute) {
                return $this->typesCache[$key] = [
                    new Type(Type::BUILTIN_TYPE_STRING),
                ];
            }

            foreach ($discriminatorMapping->getTypesMapping() as $mappedClass) {
                if (null !== $types = $this->propertyTypeExtractor->getTypes($mappedClass, $attribute)) {
                    return $this->typesCache[$key] = $types;
                }
            }
        }

        $this->typesCache[$key] = false;

        return null;
    }

    /**
     * Sets an attribute and apply the name converter if necessary.
     */
    private function updateData(array $data, string $attribute, mixed $attributeValue, string $class, ?string $format, array $context, ?array $attributesMetadata, ?ClassMetadataInterface $classMetadata): array
    {
        if (null === $attributeValue && ($context[self::SKIP_NULL_VALUES] ?? $this->defaultContext[self::SKIP_NULL_VALUES] ?? false)) {
            return $data;
        }

        if (null !== $classMetadata && null !== $serializedPath = ($attributesMetadata[$attribute] ?? null)?->getSerializedPath()) {
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            if ($propertyAccessor->isReadable($data, $serializedPath) && null !== $propertyAccessor->getValue($data, $serializedPath)) {
                throw new LogicException(sprintf('The element you are trying to set is already populated: "%s".', (string) $serializedPath));
            }
            $propertyAccessor->setValue($data, $serializedPath, $attributeValue);

            return $data;
        }

        if ($this->nameConverter) {
            $attribute = $this->nameConverter->normalize($attribute, $class, $format, $context);
        }

        $data[$attribute] = $attributeValue;

        return $data;
    }

    /**
     * Is the max depth reached for the given attribute?
     *
     * @param AttributeMetadataInterface[] $attributesMetadata
     */
    private function isMaxDepthReached(array $attributesMetadata, string $class, string $attribute, array &$context): bool
    {
        if (!($enableMaxDepth = $context[self::ENABLE_MAX_DEPTH] ?? $this->defaultContext[self::ENABLE_MAX_DEPTH] ?? false)
            || !isset($attributesMetadata[$attribute]) || null === $maxDepth = $attributesMetadata[$attribute]?->getMaxDepth()
        ) {
            return false;
        }

        $key = sprintf(self::DEPTH_KEY_PATTERN, $class, $attribute);
        if (!isset($context[$key])) {
            $context[$key] = 1;

            return false;
        }

        if ($context[$key] === $maxDepth) {
            return true;
        }

        ++$context[$key];

        return false;
    }

    /**
     * Overwritten to update the cache key for the child.
     *
     * We must not mix up the attribute cache between parent and children.
     *
     * @internal
     */
    protected function createChildContext(array $parentContext, string $attribute, ?string $format): array
    {
        $context = parent::createChildContext($parentContext, $attribute, $format);
        if ($context['cache_key'] ?? false) {
            $context['cache_key'] .= '-'.$attribute;
        } elseif (false !== ($context['cache_key'] ?? null)) {
            $context['cache_key'] = $this->getCacheKey($format, $context);
        }

        return $context;
    }

    /**
     * Builds the cache key for the attributes cache.
     *
     * The key must be different for every option in the context that could change which attributes should be handled.
     */
    private function getCacheKey(?string $format, array $context): bool|string
    {
        foreach ($context[self::EXCLUDE_FROM_CACHE_KEY] ?? $this->defaultContext[self::EXCLUDE_FROM_CACHE_KEY] as $key) {
            unset($context[$key]);
        }
        unset($context[self::EXCLUDE_FROM_CACHE_KEY]);
        unset($context[self::OBJECT_TO_POPULATE]);
        unset($context['cache_key']); // avoid artificially different keys

        try {
            return hash('xxh128', $format.serialize([
                'context' => $context,
                'ignored' => $context[self::IGNORED_ATTRIBUTES] ?? $this->defaultContext[self::IGNORED_ATTRIBUTES],
            ]));
        } catch (\Exception) {
            // The context cannot be serialized, skip the cache
            return false;
        }
    }

    /**
     * This error may occur when specific object normalizer implementation gets attribute value
     * by accessing a public uninitialized property or by calling a method accessing such property.
     */
    private function isUninitializedValueError(\Error|UninitializedPropertyException $e): bool
    {
        return $e instanceof UninitializedPropertyException
            || str_starts_with($e->getMessage(), 'Typed property')
            && str_ends_with($e->getMessage(), 'must not be accessed before initialization');
    }

    /**
     * Returns all attributes with a SerializedPath attribute and the respective path.
     */
    private function getNestedAttributes(string $class): array
    {
        if (!$this->classMetadataFactory?->hasMetadataFor($class)) {
            return [];
        }

        $properties = [];
        $serializedPaths = [];
        $classMetadata = $this->classMetadataFactory->getMetadataFor($class);
        foreach ($classMetadata->getAttributesMetadata() as $name => $metadata) {
            if (!$serializedPath = $metadata->getSerializedPath()) {
                continue;
            }
            $pathIdentifier = implode(',', $serializedPath->getElements());
            if (isset($serializedPaths[$pathIdentifier])) {
                throw new LogicException(sprintf('Duplicate serialized path: "%s" used for properties "%s" and "%s".', $pathIdentifier, $serializedPaths[$pathIdentifier], $name));
            }
            $serializedPaths[$pathIdentifier] = $name;
            $properties[$name] = $serializedPath;
        }

        return $properties;
    }

    private function removeNestedValue(array $path, array $data): array
    {
        $element = array_shift($path);
        if (!$path || !$data[$element] = $this->removeNestedValue($path, $data[$element])) {
            unset($data[$element]);
        }

        return $data;
    }

    /**
     * @return class-string
     */
    private function getMappedClass(array $data, string $class, array $context): string
    {
        if (null !== $object = $this->extractObjectToPopulate($class, $context, self::OBJECT_TO_POPULATE)) {
            return $object::class;
        }

        if (!$mapping = $this->classDiscriminatorResolver?->getMappingForClass($class)) {
            return $class;
        }

        if (null === $type = $data[$mapping->getTypeProperty()] ?? null) {
            throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('Type property "%s" not found for the abstract object "%s".', $mapping->getTypeProperty(), $class), null, ['string'], isset($context['deserialization_path']) ? $context['deserialization_path'].'.'.$mapping->getTypeProperty() : $mapping->getTypeProperty(), false);
        }

        if (null === $mappedClass = $mapping->getClassForType($type)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('The type "%s" is not a valid value.', $type), $type, ['string'], isset($context['deserialization_path']) ? $context['deserialization_path'].'.'.$mapping->getTypeProperty() : $mapping->getTypeProperty(), true);
        }

        return $mappedClass;
    }
}
