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

    const CIRCULAR_REFERENCE_LIMIT = 'circular_reference_limit';
    const OBJECT_TO_POPULATE = 'object_to_populate';
    const GROUPS = 'groups';
    const ATTRIBUTES = 'attributes';
    const ALLOW_EXTRA_ATTRIBUTES = 'allow_extra_attributes';
    const DEFAULT_CONSTRUCTOR_ARGUMENTS = 'default_constructor_arguments';
    const CALLBACKS = 'callbacks';
    const CIRCULAR_REFERENCE_HANDLER = 'circular_reference_handler';
    const IGNORED_ATTRIBUTES = 'ignored_attributes';

    /**
     * @internal
     */
    const CIRCULAR_REFERENCE_LIMIT_COUNTERS = 'circular_reference_limit_counters';

    protected $defaultContext = [
        self::ALLOW_EXTRA_ATTRIBUTES => true,
        self::CIRCULAR_REFERENCE_LIMIT => 1,
        self::IGNORED_ATTRIBUTES => [],
    ];

    /**
     * @deprecated since Symfony 4.2
     */
    protected $circularReferenceLimit = 1;

    /**
     * @deprecated since Symfony 4.2
     *
     * @var callable|null
     */
    protected $circularReferenceHandler;

    /**
     * @var ClassMetadataFactoryInterface|null
     */
    protected $classMetadataFactory;

    /**
     * @var NameConverterInterface|null
     */
    protected $nameConverter;

    /**
     * @deprecated since Symfony 4.2
     */
    protected $callbacks = [];

    /**
     * @deprecated since Symfony 4.2
     */
    protected $ignoredAttributes = [];

    /**
     * @deprecated since Symfony 4.2
     */
    protected $camelizedAttributes = [];

    /**
     * Sets the {@link ClassMetadataFactoryInterface} to use.
     */
    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory = null, NameConverterInterface $nameConverter = null, array $defaultContext = [])
    {
        $this->classMetadataFactory = $classMetadataFactory;
        $this->nameConverter = $nameConverter;
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);

        if (\is_array($this->defaultContext[self::CALLBACKS] ?? null)) {
            foreach ($this->defaultContext[self::CALLBACKS] as $attribute => $callback) {
                if (!\is_callable($callback)) {
                    throw new InvalidArgumentException(sprintf('The given callback for attribute "%s" is not callable.', $attribute));
                }
            }
        }
    }

    /**
     * Sets circular reference limit.
     *
     * @deprecated since Symfony 4.2
     *
     * @param int $circularReferenceLimit Limit of iterations for the same object
     *
     * @return self
     */
    public function setCircularReferenceLimit($circularReferenceLimit)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.2, use the "circular_reference_limit" key of the context instead.', __METHOD__), E_USER_DEPRECATED);

        $this->defaultContext[self::CIRCULAR_REFERENCE_LIMIT] = $this->circularReferenceLimit = $circularReferenceLimit;

        return $this;
    }

    /**
     * Sets circular reference handler.
     *
     * @deprecated since Symfony 4.2
     *
     * @param callable $circularReferenceHandler
     *
     * @return self
     */
    public function setCircularReferenceHandler(callable $circularReferenceHandler)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.2, use the "circular_reference_handler" key of the context instead.', __METHOD__), E_USER_DEPRECATED);

        $this->defaultContext[self::CIRCULAR_REFERENCE_HANDLER] = $this->circularReferenceHandler = $circularReferenceHandler;

        return $this;
    }

    /**
     * Sets normalization callbacks.
     *
     * @deprecated since Symfony 4.2
     *
     * @param callable[] $callbacks Help normalize the result
     *
     * @return self
     *
     * @throws InvalidArgumentException if a non-callable callback is set
     */
    public function setCallbacks(array $callbacks)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.2, use the "callbacks" key of the context instead.', __METHOD__), E_USER_DEPRECATED);

        foreach ($callbacks as $attribute => $callback) {
            if (!\is_callable($callback)) {
                throw new InvalidArgumentException(sprintf('The given callback for attribute "%s" is not callable.', $attribute));
            }
        }
        $this->defaultContext[self::CALLBACKS] = $this->callbacks = $callbacks;

        return $this;
    }

    /**
     * Sets ignored attributes for normalization and denormalization.
     *
     * @deprecated since Symfony 4.2
     *
     * @return self
     */
    public function setIgnoredAttributes(array $ignoredAttributes)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.2, use the "ignored_attributes" key of the context instead.', __METHOD__), E_USER_DEPRECATED);

        $this->defaultContext[self::IGNORED_ATTRIBUTES] = $this->ignoredAttributes = $ignoredAttributes;

        return $this;
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
     * @param object $object
     * @param array  $context
     *
     * @return bool
     *
     * @throws CircularReferenceException
     */
    protected function isCircularReference($object, &$context)
    {
        $objectHash = spl_object_hash($object);

        $circularReferenceLimit = $context[self::CIRCULAR_REFERENCE_LIMIT] ?? $this->defaultContext[self::CIRCULAR_REFERENCE_LIMIT] ?? $this->circularReferenceLimit;
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
     * @final since Symfony 4.2
     *
     * @param object      $object
     * @param string|null $format
     * @param array       $context
     *
     * @return mixed
     *
     * @throws CircularReferenceException
     */
    protected function handleCircularReference($object/*, string $format = null, array $context = []*/)
    {
        if (\func_num_args() < 2 && __CLASS__ !== \get_class($this) && __CLASS__ !== (new \ReflectionMethod($this, __FUNCTION__))->getDeclaringClass()->getName() && !$this instanceof \PHPUnit\Framework\MockObject\MockObject && !$this instanceof \Prophecy\Prophecy\ProphecySubjectInterface) {
            @trigger_error(sprintf('The "%s()" method will have two new "string $format = null" and "array $context = []" arguments in version 5.0, not defining it is deprecated since Symfony 4.2.', __METHOD__), E_USER_DEPRECATED);
        }
        $format = \func_num_args() > 1 ? func_get_arg(1) : null;
        $context = \func_num_args() > 2 ? func_get_arg(2) : [];

        $circularReferenceHandler = $context[self::CIRCULAR_REFERENCE_HANDLER] ?? $this->defaultContext[self::CIRCULAR_REFERENCE_HANDLER] ?? $this->circularReferenceHandler;
        if ($circularReferenceHandler) {
            return $circularReferenceHandler($object, $format, $context);
        }

        throw new CircularReferenceException(sprintf('A circular reference has been detected when serializing the object of class "%s" (configured limit: %d)', \get_class($object), $this->circularReferenceLimit));
    }

    /**
     * Gets attributes to normalize using groups.
     *
     * @param string|object $classOrObject
     * @param array         $context
     * @param bool          $attributesAsString If false, return an array of {@link AttributeMetadataInterface}
     *
     * @throws LogicException if the 'allow_extra_attributes' context variable is false and no class metadata factory is provided
     *
     * @return string[]|AttributeMetadataInterface[]|bool
     */
    protected function getAllowedAttributes($classOrObject, array $context, $attributesAsString = false)
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
        if (false === $groups && $allowExtraAttributes) {
            return false;
        }

        $allowedAttributes = [];
        foreach ($this->classMetadataFactory->getMetadataFor($classOrObject)->getAttributesMetadata() as $attributeMetadata) {
            $name = $attributeMetadata->getName();

            if (
                (false === $groups || array_intersect($attributeMetadata->getGroups(), $groups)) &&
                $this->isAllowedAttribute($classOrObject, $name, null, $context)
            ) {
                $allowedAttributes[] = $attributesAsString ? $name : $attributeMetadata;
            }
        }

        return $allowedAttributes;
    }

    /**
     * Is this attribute allowed?
     *
     * @param object|string $classOrObject
     * @param string        $attribute
     * @param string|null   $format
     * @param array         $context
     *
     * @return bool
     */
    protected function isAllowedAttribute($classOrObject, $attribute, $format = null, array $context = [])
    {
        $ignoredAttributes = $context[self::IGNORED_ATTRIBUTES] ?? $this->defaultContext[self::IGNORED_ATTRIBUTES] ?? $this->ignoredAttributes;
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
     * @param array            $data
     * @param string           $class
     * @param array            $context
     * @param \ReflectionClass $reflectionClass
     * @param array|bool       $allowedAttributes
     *
     * @return \ReflectionMethod|null
     */
    protected function getConstructor(array &$data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes)
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
     * @param array            $data
     * @param string           $class
     * @param array            $context
     * @param \ReflectionClass $reflectionClass
     * @param array|bool       $allowedAttributes
     * @param string|null      $format
     *
     * @return object
     *
     * @throws RuntimeException
     * @throws MissingConstructorArgumentsException
     */
    protected function instantiateObject(array &$data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, string $format = null)
    {
        if (null !== $object = $this->extractObjectToPopulate($class, $context, self::OBJECT_TO_POPULATE)) {
            unset($context[self::OBJECT_TO_POPULATE]);

            return $object;
        }

        $constructor = $this->getConstructor($data, $class, $context, $reflectionClass, $allowedAttributes);
        if ($constructor) {
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
                            throw new RuntimeException(sprintf('Cannot create an instance of %s from serialized data because the variadic parameter %s can only accept an array.', $class, $constructorParameter->name));
                        }

                        $params = array_merge($params, $data[$paramName]);
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
                } else {
                    throw new MissingConstructorArgumentsException(sprintf('Cannot create an instance of %s from serialized data because its constructor requires parameter "%s" to be present.', $class, $constructorParameter->name));
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
    protected function denormalizeParameter(\ReflectionClass $class, \ReflectionParameter $parameter, $parameterName, $parameterData, array $context, $format = null)
    {
        try {
            if (null !== $parameter->getClass()) {
                if (!$this->serializer instanceof DenormalizerInterface) {
                    throw new LogicException(sprintf('Cannot create an instance of %s from serialized data because the serializer inject in "%s" is not a denormalizer', $parameter->getClass(), self::class));
                }
                $parameterClass = $parameter->getClass()->getName();
                $parameterData = $this->serializer->denormalize($parameterData, $parameterClass, $format, $this->createChildContext($context, $parameterName));
            }
        } catch (\ReflectionException $e) {
            throw new RuntimeException(sprintf('Could not determine the class of the parameter "%s".', $parameterName), 0, $e);
        } catch (MissingConstructorArgumentsException $e) {
            if (!$parameter->getType()->allowsNull()) {
                throw $e;
            }
            $parameterData = null;
        }

        return $parameterData;
    }

    /**
     * @param array  $parentContext
     * @param string $attribute
     *
     * @return array
     *
     * @internal
     */
    protected function createChildContext(array $parentContext, $attribute)
    {
        if (isset($parentContext[self::ATTRIBUTES][$attribute])) {
            $parentContext[self::ATTRIBUTES] = $parentContext[self::ATTRIBUTES][$attribute];
        } else {
            unset($parentContext[self::ATTRIBUTES]);
        }

        return $parentContext;
    }
}
