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
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * Normalizer implementation.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
abstract class AbstractNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface, CacheableSupportsMethodInterface
{
    use ObjectToPopulateTrait;
    use SerializerAwareTrait;

    /* constants to configure the context */

    /**
     * How many loops of circular reference to allow while normalizing.
     *
     * The default value of 1 means that when we encounter the same object a
     * second time, we consider that a circular reference.
     *
     * You can raise this value for special cases, e.g. in combination with the
     * max depth setting of the object normalizer.
     */
    public const CIRCULAR_REFERENCE_LIMIT = 'circular_reference_limit';

    /**
     * Instead of creating a new instance of an object, update the specified object.
     *
     * If you have a nested structure, child objects will be overwritten with
     * new instances unless you set DEEP_OBJECT_TO_POPULATE to true.
     */
    public const OBJECT_TO_POPULATE = 'object_to_populate';

    /**
     * Only (de)normalize attributes that are in the specified groups.
     */
    public const GROUPS = 'groups';

    /**
     * Limit (de)normalize to the specified names.
     *
     * For nested structures, this list needs to reflect the object tree.
     */
    public const ATTRIBUTES = 'attributes';

    /**
     * If ATTRIBUTES are specified, and the source has fields that are not part of that list,
     * either ignore those attributes (true) or throw an ExtraAttributesException (false).
     */
    public const ALLOW_EXTRA_ATTRIBUTES = 'allow_extra_attributes';

    /**
     * Hashmap of default values for constructor arguments.
     *
     * The names need to match the parameter names in the constructor arguments.
     */
    public const DEFAULT_CONSTRUCTOR_ARGUMENTS = 'default_constructor_arguments';

    /**
     * Hashmap of field name => callable to normalize this field.
     *
     * The callable is called if the field is encountered with the arguments:
     *
     * - mixed  $attributeValue value of this field
     * - object $object         the whole object being normalized
     * - string $attributeName  name of the attribute being normalized
     * - string $format         the requested format
     * - array  $context        the serialization context
     */
    public const CALLBACKS = 'callbacks';

    /**
     * Handler to call when a circular reference has been detected.
     *
     * If you specify no handler, a CircularReferenceException is thrown.
     *
     * The method will be called with ($object, $format, $context) and its
     * return value is returned as the result of the normalize call.
     */
    public const CIRCULAR_REFERENCE_HANDLER = 'circular_reference_handler';

    /**
     * Skip the specified attributes when normalizing an object tree.
     *
     * This list is applied to each element of nested structures.
     *
     * Note: The behaviour for nested structures is different from ATTRIBUTES
     * for historical reason. Aligning the behaviour would be a BC break.
     */
    public const IGNORED_ATTRIBUTES = 'ignored_attributes';

    /**
     * @internal
     */
    protected const CIRCULAR_REFERENCE_LIMIT_COUNTERS = 'circular_reference_limit_counters';

    protected $defaultContext = [
        self::ALLOW_EXTRA_ATTRIBUTES => true,
        self::CIRCULAR_REFERENCE_HANDLER => null,
        self::CIRCULAR_REFERENCE_LIMIT => 1,
        self::IGNORED_ATTRIBUTES => [],
    ];

    /**
     * @var ClassMetadataFactoryInterface|null
     */
    protected $classMetadataFactory;

    /**
     * @var NameConverterInterface|null
     */
    protected $nameConverter;

    /**
     * Sets the {@link ClassMetadataFactoryInterface} to use.
     */
    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory = null, NameConverterInterface $nameConverter = null, array $defaultContext = [])
    {
        $this->classMetadataFactory = $classMetadataFactory;
        $this->nameConverter = $nameConverter;
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);

        if (isset($this->defaultContext[self::CALLBACKS])) {
            if (!\is_array($this->defaultContext[self::CALLBACKS])) {
                throw new InvalidArgumentException(sprintf('The "%s" default context option must be an array of callables.', self::CALLBACKS));
            }

            foreach ($this->defaultContext[self::CALLBACKS] as $attribute => $callback) {
                if (!\is_callable($callback)) {
                    throw new InvalidArgumentException(sprintf('Invalid callback found for attribute "%s" in the "%s" default context option.', $attribute, self::CALLBACKS));
                }
            }
        }

        if (isset($this->defaultContext[self::CIRCULAR_REFERENCE_HANDLER]) && !\is_callable($this->defaultContext[self::CIRCULAR_REFERENCE_HANDLER])) {
            throw new InvalidArgumentException(sprintf('Invalid callback found in the "%s" default context option.', self::CIRCULAR_REFERENCE_HANDLER));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }

    /**
     * Detects if the configured circular reference limit is reached.
     *
     * @return bool
     *
     * @throws CircularReferenceException
     */
    protected function isCircularReference(object $object, array &$context)
    {
        $objectHash = spl_object_hash($object);

        $circularReferenceLimit = $context[self::CIRCULAR_REFERENCE_LIMIT] ?? $this->defaultContext[self::CIRCULAR_REFERENCE_LIMIT];
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

    /**
     * Handles a circular reference.
     *
     * If a circular reference handler is set, it will be called. Otherwise, a
     * {@class CircularReferenceException} will be thrown.
     *
     * @final
     *
     * @return mixed
     *
     * @throws CircularReferenceException
     */
    protected function handleCircularReference(object $object, string $format = null, array $context = [])
    {
        $circularReferenceHandler = $context[self::CIRCULAR_REFERENCE_HANDLER] ?? $this->defaultContext[self::CIRCULAR_REFERENCE_HANDLER];
        if ($circularReferenceHandler) {
            return $circularReferenceHandler($object, $format, $context);
        }

        throw new CircularReferenceException(sprintf('A circular reference has been detected when serializing the object of class "%s" (configured limit: %d).', get_debug_type($object), $context[self::CIRCULAR_REFERENCE_LIMIT] ?? $this->defaultContext[self::CIRCULAR_REFERENCE_LIMIT]));
    }

    /**
     * Gets attributes to normalize using groups.
     *
     * @param string|object $classOrObject
     * @param bool          $attributesAsString If false, return an array of {@link AttributeMetadataInterface}
     *
     * @throws LogicException if the 'allow_extra_attributes' context variable is false and no class metadata factory is provided
     *
     * @return string[]|AttributeMetadataInterface[]|bool
     */
    protected function getAllowedAttributes($classOrObject, array $context, bool $attributesAsString = false)
    {
        $allowExtraAttributes = $context[self::ALLOW_EXTRA_ATTRIBUTES] ?? $this->defaultContext[self::ALLOW_EXTRA_ATTRIBUTES];
        if (!$this->classMetadataFactory) {
            if (!$allowExtraAttributes) {
                throw new LogicException(sprintf('A class metadata factory must be provided in the constructor when setting "%s" to false.', self::ALLOW_EXTRA_ATTRIBUTES));
            }

            return false;
        }

        $tmpGroups = $context[self::GROUPS] ?? $this->defaultContext[self::GROUPS] ?? null;
        $groups = (\is_array($tmpGroups) || is_scalar($tmpGroups)) ? (array) $tmpGroups : false;

        $allowedAttributes = [];
        $ignoreUsed = false;
        foreach ($this->classMetadataFactory->getMetadataFor($classOrObject)->getAttributesMetadata() as $attributeMetadata) {
            if ($ignore = $attributeMetadata->isIgnored()) {
                $ignoreUsed = true;
            }

            // If you update this check, update accordingly the one in Symfony\Component\PropertyInfo\Extractor\SerializerExtractor::getProperties()
            if (
                !$ignore &&
                (false === $groups || array_intersect(array_merge($attributeMetadata->getGroups(), ['*']), $groups)) &&
                $this->isAllowedAttribute($classOrObject, $name = $attributeMetadata->getName(), null, $context)
            ) {
                $allowedAttributes[] = $attributesAsString ? $name : $attributeMetadata;
            }
        }

        if (!$ignoreUsed && false === $groups && $allowExtraAttributes) {
            // Backward Compatibility with the code using this method written before the introduction of @Ignore
            return false;
        }

        return $allowedAttributes;
    }

    /**
     * Is this attribute allowed?
     *
     * @param object|string $classOrObject
     *
     * @return bool
     */
    protected function isAllowedAttribute($classOrObject, string $attribute, string $format = null, array $context = [])
    {
        $ignoredAttributes = $context[self::IGNORED_ATTRIBUTES] ?? $this->defaultContext[self::IGNORED_ATTRIBUTES];
        if (\in_array($attribute, $ignoredAttributes)) {
            return false;
        }

        $attributes = $context[self::ATTRIBUTES] ?? $this->defaultContext[self::ATTRIBUTES] ?? null;
        if (isset($attributes[$attribute])) {
            // Nested attributes
            return true;
        }

        if (\is_array($attributes)) {
            return \in_array($attribute, $attributes, true);
        }

        return true;
    }

    /**
     * Normalizes the given data to an array. It's particularly useful during
     * the denormalization process.
     *
     * @param object|array $data
     *
     * @return array
     */
    protected function prepareForDenormalization($data)
    {
        return (array) $data;
    }

    /**
     * Returns the method to use to construct an object. This method must be either
     * the object constructor or static.
     *
     * @param array|bool $allowedAttributes
     *
     * @return \ReflectionMethod|null
     */
    protected function getConstructor(array &$data, string $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes)
    {
        return $reflectionClass->getConstructor();
    }

    /**
     * Instantiates an object using constructor parameters when needed.
     *
     * This method also allows to denormalize data into an existing object if
     * it is present in the context with the object_to_populate. This object
     * is removed from the context before being returned to avoid side effects
     * when recursively normalizing an object graph.
     *
     * @param array|bool $allowedAttributes
     *
     * @return object
     *
     * @throws RuntimeException
     * @throws MissingConstructorArgumentsException
     */
    protected function instantiateObject(array &$data, string $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, string $format = null)
    {
        if (null !== $object = $this->extractObjectToPopulate($class, $context, self::OBJECT_TO_POPULATE)) {
            unset($context[self::OBJECT_TO_POPULATE]);

            return $object;
        }
        // clean up even if no match
        unset($context[static::OBJECT_TO_POPULATE]);

        $constructor = $this->getConstructor($data, $class, $context, $reflectionClass, $allowedAttributes);
        if ($constructor) {
            if (true !== $constructor->isPublic()) {
                return $reflectionClass->newInstanceWithoutConstructor();
            }

            $constructorParameters = $constructor->getParameters();

            $params = [];
            foreach ($constructorParameters as $constructorParameter) {
                $paramName = $constructorParameter->name;
                $key = $this->nameConverter ? $this->nameConverter->normalize($paramName, $class, $format, $context) : $paramName;

                $allowed = false === $allowedAttributes || \in_array($paramName, $allowedAttributes);
                $ignored = !$this->isAllowedAttribute($class, $paramName, $format, $context);
                if ($constructorParameter->isVariadic()) {
                    if ($allowed && !$ignored && (isset($data[$key]) || \array_key_exists($key, $data))) {
                        if (!\is_array($data[$paramName])) {
                            throw new RuntimeException(sprintf('Cannot create an instance of "%s" from serialized data because the variadic parameter "%s" can only accept an array.', $class, $constructorParameter->name));
                        }

                        $variadicParameters = [];
                        foreach ($data[$paramName] as $parameterData) {
                            $variadicParameters[] = $this->denormalizeParameter($reflectionClass, $constructorParameter, $paramName, $parameterData, $context, $format);
                        }

                        $params = array_merge($params, $variadicParameters);
                        unset($data[$key]);
                    }
                } elseif ($allowed && !$ignored && (isset($data[$key]) || \array_key_exists($key, $data))) {
                    $parameterData = $data[$key];
                    if (null === $parameterData && $constructorParameter->allowsNull()) {
                        $params[] = null;
                        // Don't run set for a parameter passed to the constructor
                        unset($data[$key]);
                        continue;
                    }

                    // Don't run set for a parameter passed to the constructor
                    $params[] = $this->denormalizeParameter($reflectionClass, $constructorParameter, $paramName, $parameterData, $context, $format);
                    unset($data[$key]);
                } elseif (\array_key_exists($key, $context[static::DEFAULT_CONSTRUCTOR_ARGUMENTS][$class] ?? [])) {
                    $params[] = $context[static::DEFAULT_CONSTRUCTOR_ARGUMENTS][$class][$key];
                } elseif (\array_key_exists($key, $this->defaultContext[self::DEFAULT_CONSTRUCTOR_ARGUMENTS][$class] ?? [])) {
                    $params[] = $this->defaultContext[self::DEFAULT_CONSTRUCTOR_ARGUMENTS][$class][$key];
                } elseif ($constructorParameter->isDefaultValueAvailable()) {
                    $params[] = $constructorParameter->getDefaultValue();
                } elseif ($constructorParameter->hasType() && $constructorParameter->getType()->allowsNull()) {
                    $params[] = null;
                } else {
                    throw new MissingConstructorArgumentsException(sprintf('Cannot create an instance of "%s" from serialized data because its constructor requires parameter "%s" to be present.', $class, $constructorParameter->name));
                }
            }

            if ($constructor->isConstructor()) {
                return $reflectionClass->newInstanceArgs($params);
            } else {
                return $constructor->invokeArgs(null, $params);
            }
        }

        return new $class();
    }

    /**
     * @internal
     */
    protected function denormalizeParameter(\ReflectionClass $class, \ReflectionParameter $parameter, string $parameterName, $parameterData, array $context, string $format = null)
    {
        try {
            if (($parameterType = $parameter->getType()) instanceof \ReflectionNamedType && !$parameterType->isBuiltin()) {
                $parameterClass = $parameterType->getName();
                new \ReflectionClass($parameterClass); // throws a \ReflectionException if the class doesn't exist

                if (!$this->serializer instanceof DenormalizerInterface) {
                    throw new LogicException(sprintf('Cannot create an instance of "%s" from serialized data because the serializer inject in "%s" is not a denormalizer.', $parameterClass, static::class));
                }

                return $this->serializer->denormalize($parameterData, $parameterClass, $format, $this->createChildContext($context, $parameterName, $format));
            }
        } catch (\ReflectionException $e) {
            throw new RuntimeException(sprintf('Could not determine the class of the parameter "%s".', $parameterName), 0, $e);
        } catch (MissingConstructorArgumentsException $e) {
            if (!$parameter->getType()->allowsNull()) {
                throw $e;
            }

            return null;
        }

        return $parameterData;
    }

    /**
     * @internal
     */
    protected function createChildContext(array $parentContext, string $attribute, ?string $format): array
    {
        if (isset($parentContext[self::ATTRIBUTES][$attribute])) {
            $parentContext[self::ATTRIBUTES] = $parentContext[self::ATTRIBUTES][$attribute];
        } else {
            unset($parentContext[self::ATTRIBUTES]);
        }

        return $parentContext;
    }
}
