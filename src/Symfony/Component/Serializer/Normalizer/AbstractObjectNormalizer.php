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

use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
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

    public const PRESERVE_EMPTY_OBJECTS = 'preserve_empty_objects';

    private $propertyTypeExtractor;
    private $typesCache = [];
    private $attributesCache = [];

    /**
     * @deprecated since Symfony 4.2
     *
     * @var callable|null
     */
    private $maxDepthHandler;
    private $objectClassResolver;

    /**
     * @var ClassDiscriminatorResolverInterface|null
     */
    protected $classDiscriminatorResolver;

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory = null, NameConverterInterface $nameConverter = null, PropertyTypeExtractorInterface $propertyTypeExtractor = null, ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null, callable $objectClassResolver = null, array $defaultContext = [])
    {
        parent::__construct($classMetadataFactory, $nameConverter, $defaultContext);

        if (isset($this->defaultContext[self::MAX_DEPTH_HANDLER]) && !\is_callable($this->defaultContext[self::MAX_DEPTH_HANDLER])) {
            throw new InvalidArgumentException(sprintf('The "%s" given in the default context is not callable.', self::MAX_DEPTH_HANDLER));
        }

        $this->defaultContext[self::EXCLUDE_FROM_CACHE_KEY] = array_merge($this->defaultContext[self::EXCLUDE_FROM_CACHE_KEY] ?? [], [self::CIRCULAR_REFERENCE_LIMIT_COUNTERS]);

        $this->propertyTypeExtractor = $propertyTypeExtractor;

        if (null === $classDiscriminatorResolver && null !== $classMetadataFactory) {
            $classDiscriminatorResolver = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);
        }
        $this->classDiscriminatorResolver = $classDiscriminatorResolver;
        $this->objectClassResolver = $objectClassResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return \is_object($data) && !$data instanceof \Traversable;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = [])
    {
        if (!isset($context['cache_key'])) {
            $context['cache_key'] = $this->getCacheKey($format, $context);
        }

        if (isset($context[self::CALLBACKS])) {
            if (!\is_array($context[self::CALLBACKS])) {
                throw new InvalidArgumentException(sprintf('The "%s" context option must be an array of callables.', self::CALLBACKS));
            }

            foreach ($context[self::CALLBACKS] as $attribute => $callback) {
                if (!\is_callable($callback)) {
                    throw new InvalidArgumentException(sprintf('Invalid callback found for attribute "%s" in the "%s" context option.', $attribute, self::CALLBACKS));
                }
            }
        }

        if ($this->isCircularReference($object, $context)) {
            return $this->handleCircularReference($object, $format, $context);
        }

        $data = [];
        $stack = [];
        $attributes = $this->getAttributes($object, $format, $context);
        $class = $this->objectClassResolver ? ($this->objectClassResolver)($object) : \get_class($object);
        $attributesMetadata = $this->classMetadataFactory ? $this->classMetadataFactory->getMetadataFor($class)->getAttributesMetadata() : null;
        if (isset($context[self::MAX_DEPTH_HANDLER])) {
            $maxDepthHandler = $context[self::MAX_DEPTH_HANDLER];
            if (!\is_callable($maxDepthHandler)) {
                throw new InvalidArgumentException(sprintf('The "%s" given in the context is not callable.', self::MAX_DEPTH_HANDLER));
            }
        } else {
            // already validated in constructor resp by type declaration of setMaxDepthHandler
            $maxDepthHandler = $this->defaultContext[self::MAX_DEPTH_HANDLER] ?? $this->maxDepthHandler;
        }

        foreach ($attributes as $attribute) {
            $maxDepthReached = false;
            if (null !== $attributesMetadata && ($maxDepthReached = $this->isMaxDepthReached($attributesMetadata, $class, $attribute, $context)) && !$maxDepthHandler) {
                continue;
            }

            $attributeValue = $this->getAttributeValue($object, $attribute, $format, $context);
            if ($maxDepthReached) {
                $attributeValue = $maxDepthHandler($attributeValue, $object, $attribute, $format, $context);
            }

            /**
             * @var callable|null
             */
            $callback = $context[self::CALLBACKS][$attribute] ?? $this->defaultContext[self::CALLBACKS][$attribute] ?? $this->callbacks[$attribute] ?? null;
            if ($callback) {
                $attributeValue = $callback($attributeValue, $object, $attribute, $format, $context);
            }

            if (null !== $attributeValue && !is_scalar($attributeValue)) {
                $stack[$attribute] = $attributeValue;
            }

            $data = $this->updateData($data, $attribute, $attributeValue, $class, $format, $context);
        }

        foreach ($stack as $attribute => $attributeValue) {
            if (!$this->serializer instanceof NormalizerInterface) {
                throw new LogicException(sprintf('Cannot normalize attribute "%s" because the injected serializer is not a normalizer.', $attribute));
            }

            $data = $this->updateData($data, $attribute, $this->serializer->normalize($attributeValue, $format, $this->createChildContext($context, $attribute, $format)), $class, $format, $context);
        }

        if (isset($context[self::PRESERVE_EMPTY_OBJECTS]) && !\count($data)) {
            return new \ArrayObject();
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    protected function instantiateObject(array &$data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, string $format = null)
    {
        if ($this->classDiscriminatorResolver && $mapping = $this->classDiscriminatorResolver->getMappingForClass($class)) {
            if (!isset($data[$mapping->getTypeProperty()])) {
                throw new RuntimeException(sprintf('Type property "%s" not found for the abstract object "%s".', $mapping->getTypeProperty(), $class));
            }

            $type = $data[$mapping->getTypeProperty()];
            if (null === ($mappedClass = $mapping->getClassForType($type))) {
                throw new RuntimeException(sprintf('The type "%s" has no mapped class for the abstract object "%s".', $type, $class));
            }

            if ($mappedClass !== $class) {
                return $this->instantiateObject($data, $mappedClass, $context, new \ReflectionClass($mappedClass), $allowedAttributes, $format);
            }
        }

        return parent::instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes, $format);
    }

    /**
     * Gets and caches attributes for the given object, format and context.
     *
     * @param object      $object
     * @param string|null $format
     *
     * @return string[]
     */
    protected function getAttributes($object, $format, array $context)
    {
        $class = $this->objectClassResolver ? ($this->objectClassResolver)($object) : \get_class($object);
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

        if ($this->classDiscriminatorResolver && $mapping = $this->classDiscriminatorResolver->getMappingForMappedObject($object)) {
            array_unshift($attributes, $mapping->getTypeProperty());
        }

        if ($context['cache_key']) {
            $this->attributesCache[$key] = $attributes;
        }

        return $attributes;
    }

    /**
     * Extracts attributes to normalize from the class of the given object, format and context.
     *
     * @param object      $object
     * @param string|null $format
     *
     * @return string[]
     */
    abstract protected function extractAttributes($object, $format = null, array $context = []);

    /**
     * Gets the attribute value.
     *
     * @param object      $object
     * @param string      $attribute
     * @param string|null $format
     *
     * @return mixed
     */
    abstract protected function getAttributeValue($object, $attribute, $format = null, array $context = []);

    /**
     * Sets a handler function that will be called when the max depth is reached.
     *
     * @deprecated since Symfony 4.2
     */
    public function setMaxDepthHandler(?callable $handler): void
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.2, use the "max_depth_handler" key of the context instead.', __METHOD__), \E_USER_DEPRECATED);

        $this->maxDepthHandler = $handler;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return class_exists($type) || (interface_exists($type, false) && $this->classDiscriminatorResolver && null !== $this->classDiscriminatorResolver->getMappingForClass($type));
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type, $format = null, array $context = [])
    {
        if (!isset($context['cache_key'])) {
            $context['cache_key'] = $this->getCacheKey($format, $context);
        }

        $allowedAttributes = $this->getAllowedAttributes($type, $context, true);
        $normalizedData = $this->prepareForDenormalization($data);
        $extraAttributes = [];

        $reflectionClass = new \ReflectionClass($type);
        $object = $this->instantiateObject($normalizedData, $type, $context, $reflectionClass, $allowedAttributes, $format);
        $resolvedClass = $this->objectClassResolver ? ($this->objectClassResolver)($object) : \get_class($object);

        foreach ($normalizedData as $attribute => $value) {
            if ($this->nameConverter) {
                $attribute = $this->nameConverter->denormalize($attribute, $resolvedClass, $format, $context);
            }

            if ((false !== $allowedAttributes && !\in_array($attribute, $allowedAttributes)) || !$this->isAllowedAttribute($resolvedClass, $attribute, $format, $context)) {
                if (!($context[self::ALLOW_EXTRA_ATTRIBUTES] ?? $this->defaultContext[self::ALLOW_EXTRA_ATTRIBUTES])) {
                    $extraAttributes[] = $attribute;
                }

                continue;
            }

            if ($context[self::DEEP_OBJECT_TO_POPULATE] ?? $this->defaultContext[self::DEEP_OBJECT_TO_POPULATE] ?? false) {
                try {
                    $context[self::OBJECT_TO_POPULATE] = $this->getAttributeValue($object, $attribute, $format, $context);
                } catch (NoSuchPropertyException $e) {
                }
            }

            $value = $this->validateAndDenormalize($resolvedClass, $attribute, $value, $format, $context);
            try {
                $this->setAttributeValue($object, $attribute, $value, $format, $context);
            } catch (InvalidArgumentException $e) {
                throw new NotNormalizableValueException(sprintf('Failed to denormalize attribute "%s" value for class "%s": '.$e->getMessage(), $attribute, $type), $e->getCode(), $e);
            }
        }

        if (!empty($extraAttributes)) {
            throw new ExtraAttributesException($extraAttributes);
        }

        return $object;
    }

    /**
     * Sets attribute value.
     *
     * @param object      $object
     * @param string      $attribute
     * @param mixed       $value
     * @param string|null $format
     */
    abstract protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = []);

    /**
     * Validates the submitted data and denormalizes it.
     *
     * @param mixed $data
     *
     * @return mixed
     *
     * @throws NotNormalizableValueException
     * @throws LogicException
     */
    private function validateAndDenormalize(string $currentClass, string $attribute, $data, ?string $format, array $context)
    {
        if (null === $types = $this->getTypes($currentClass, $attribute)) {
            return $data;
        }

        $expectedTypes = [];
        foreach ($types as $type) {
            if (null === $data && $type->isNullable()) {
                return null;
            }

            $collectionValueType = $type->isCollection() ? $type->getCollectionValueType() : null;

            // Fix a collection that contains the only one element
            // This is special to xml format only
            if ('xml' === $format && null !== $collectionValueType && (!\is_array($data) || !\is_int(key($data)))) {
                $data = [$data];
            }

            if (null !== $collectionValueType && Type::BUILTIN_TYPE_OBJECT === $collectionValueType->getBuiltinType()) {
                $builtinType = Type::BUILTIN_TYPE_OBJECT;
                $class = $collectionValueType->getClassName().'[]';

                if (null !== $collectionKeyType = $type->getCollectionKeyType()) {
                    $context['key_type'] = $collectionKeyType;
                }
            } elseif ($type->isCollection() && null !== ($collectionValueType = $type->getCollectionValueType()) && Type::BUILTIN_TYPE_ARRAY === $collectionValueType->getBuiltinType()) {
                // get inner type for any nested array
                $innerType = $collectionValueType;

                // note that it will break for any other builtinType
                $dimensions = '[]';
                while (null !== $innerType->getCollectionValueType() && Type::BUILTIN_TYPE_ARRAY === $innerType->getBuiltinType()) {
                    $dimensions .= '[]';
                    $innerType = $innerType->getCollectionValueType();
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

            if (Type::BUILTIN_TYPE_OBJECT === $builtinType) {
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
            if (Type::BUILTIN_TYPE_FLOAT === $builtinType && \is_int($data) && str_contains($format, JsonEncoder::FORMAT)) {
                return (float) $data;
            }

            if (('is_'.$builtinType)($data)) {
                return $data;
            }
        }

        if ($context[self::DISABLE_TYPE_ENFORCEMENT] ?? $this->defaultContext[self::DISABLE_TYPE_ENFORCEMENT] ?? false) {
            return $data;
        }

        throw new NotNormalizableValueException(sprintf('The type of the "%s" attribute for class "%s" must be one of "%s" ("%s" given).', $attribute, $currentClass, implode('", "', array_keys($expectedTypes)), \gettype($data)));
    }

    /**
     * @internal
     */
    protected function denormalizeParameter(\ReflectionClass $class, \ReflectionParameter $parameter, $parameterName, $parameterData, array $context, $format = null)
    {
        if ($parameter->isVariadic() || null === $this->propertyTypeExtractor || null === $this->propertyTypeExtractor->getTypes($class->getName(), $parameterName)) {
            return parent::denormalizeParameter($class, $parameter, $parameterName, $parameterData, $context, $format);
        }

        return $this->validateAndDenormalize($class->getName(), $parameterName, $parameterData, $format, $context);
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

        if (null !== $this->classDiscriminatorResolver && null !== $discriminatorMapping = $this->classDiscriminatorResolver->getMappingForClass($currentClass)) {
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
     *
     * @param mixed $attributeValue
     */
    private function updateData(array $data, string $attribute, $attributeValue, string $class, ?string $format, array $context): array
    {
        if (null === $attributeValue && ($context[self::SKIP_NULL_VALUES] ?? $this->defaultContext[self::SKIP_NULL_VALUES] ?? false)) {
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
        $enableMaxDepth = $context[self::ENABLE_MAX_DEPTH] ?? $this->defaultContext[self::ENABLE_MAX_DEPTH] ?? false;
        if (
            !$enableMaxDepth ||
            !isset($attributesMetadata[$attribute]) ||
            null === $maxDepth = $attributesMetadata[$attribute]->getMaxDepth()
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
     * {@inheritdoc}
     *
     * @param string|null $format
     *
     * @internal
     */
    protected function createChildContext(array $parentContext, $attribute/*, ?string $format */): array
    {
        if (\func_num_args() >= 3) {
            $format = func_get_arg(2);
        } else {
            @trigger_error(sprintf('Method "%s::%s()" will have a third "?string $format" argument in version 5.0; not defining it is deprecated since Symfony 4.3.', static::class, __FUNCTION__), \E_USER_DEPRECATED);
            $format = null;
        }

        $context = parent::createChildContext($parentContext, $attribute, $format);
        $context['cache_key'] = $this->getCacheKey($format, $context);

        return $context;
    }

    /**
     * Builds the cache key for the attributes cache.
     *
     * The key must be different for every option in the context that could change which attributes should be handled.
     *
     * @return bool|string
     */
    private function getCacheKey(?string $format, array $context)
    {
        foreach ($context[self::EXCLUDE_FROM_CACHE_KEY] ?? $this->defaultContext[self::EXCLUDE_FROM_CACHE_KEY] as $key) {
            unset($context[$key]);
        }
        unset($context[self::EXCLUDE_FROM_CACHE_KEY]);
        unset($context[self::OBJECT_TO_POPULATE]);
        unset($context['cache_key']); // avoid artificially different keys

        try {
            return md5($format.serialize([
                'context' => $context,
                'ignored' => $this->ignoredAttributes,
                'camelized' => $this->camelizedAttributes,
            ]));
        } catch (\Exception $exception) {
            // The context cannot be serialized, skip the cache
            return false;
        }
    }
}
