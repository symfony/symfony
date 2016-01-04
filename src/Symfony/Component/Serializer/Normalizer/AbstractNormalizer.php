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

use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Normalizer implementation.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
abstract class AbstractNormalizer extends SerializerAwareNormalizer implements NormalizerInterface, DenormalizerInterface
{
    const CIRCULAR_REFERENCE_LIMIT = 'circular_reference_limit';
    const OBJECT_TO_POPULATE = 'object_to_populate';
    const GROUPS = 'groups';
    const ENABLE_MAX_DEPTH = 'enable_max_depth';
    const DEPTH_KEY_PATTERN = 'depth_%s::%s';

    /**
     * @var int
     */
    protected $circularReferenceLimit = 1;
    /**
     * @var callable
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
     * @var array
     */
    protected $callbacks = array();
    /**
     * @var array
     */
    protected $ignoredAttributes = array();
    /**
     * @var array
     */
    protected $camelizedAttributes = array();

    /**
     * @var PropertyInfoExtractorInterface
     */
    protected $propertyInfoExtractor;

    /**
     * Sets the {@link ClassMetadataFactoryInterface} to use.
     *
     * @param ClassMetadataFactoryInterface|null $classMetadataFactory
     * @param NameConverterInterface|null        $nameConverter
     */
    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory = null, NameConverterInterface $nameConverter = null, PropertyInfoExtractorInterface $propertyInfoExtractor = null)
    {
        $this->classMetadataFactory = $classMetadataFactory;
        $this->nameConverter = $nameConverter;
        $this->propertyInfoExtractor = $propertyInfoExtractor;
    }

    /**
     * Set circular reference limit.
     *
     * @param int $circularReferenceLimit limit of iterations for the same object
     *
     * @return self
     */
    public function setCircularReferenceLimit($circularReferenceLimit)
    {
        $this->circularReferenceLimit = $circularReferenceLimit;

        return $this;
    }

    /**
     * Set circular reference handler.
     *
     * @param callable $circularReferenceHandler
     *
     * @return self
     */
    public function setCircularReferenceHandler(callable $circularReferenceHandler)
    {
        $this->circularReferenceHandler = $circularReferenceHandler;

        return $this;
    }

    /**
     * Set normalization callbacks.
     *
     * @param callable[] $callbacks help normalize the result
     *
     * @return self
     *
     * @throws InvalidArgumentException if a non-callable callback is set
     */
    public function setCallbacks(array $callbacks)
    {
        foreach ($callbacks as $attribute => $callback) {
            if (!is_callable($callback)) {
                throw new InvalidArgumentException(sprintf(
                    'The given callback for attribute "%s" is not callable.',
                    $attribute
                ));
            }
        }
        $this->callbacks = $callbacks;

        return $this;
    }

    /**
     * Set ignored attributes for normalization and denormalization.
     *
     * @param array $ignoredAttributes
     *
     * @return self
     */
    public function setIgnoredAttributes(array $ignoredAttributes)
    {
        $this->ignoredAttributes = $ignoredAttributes;

        return $this;
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

        if (isset($context[static::CIRCULAR_REFERENCE_LIMIT][$objectHash])) {
            if ($context[static::CIRCULAR_REFERENCE_LIMIT][$objectHash] >= $this->circularReferenceLimit) {
                unset($context[static::CIRCULAR_REFERENCE_LIMIT][$objectHash]);

                return true;
            }

            ++$context[static::CIRCULAR_REFERENCE_LIMIT][$objectHash];
        } else {
            $context[static::CIRCULAR_REFERENCE_LIMIT][$objectHash] = 1;
        }

        return false;
    }

    /**
     * Handles a circular reference.
     *
     * If a circular reference handler is set, it will be called. Otherwise, a
     * {@class CircularReferenceException} will be thrown.
     *
     * @param object $object
     *
     * @return mixed
     *
     * @throws CircularReferenceException
     */
    protected function handleCircularReference($object)
    {
        if ($this->circularReferenceHandler) {
            return call_user_func($this->circularReferenceHandler, $object);
        }

        throw new CircularReferenceException(sprintf('A circular reference has been detected (configured limit: %d).', $this->circularReferenceLimit));
    }

    /**
     * Gets attributes to normalize using groups.
     *
     * @param string|object $classOrObject
     * @param array         $context
     * @param bool          $attributesAsString If false, return an array of {@link AttributeMetadataInterface}
     *
     * @return string[]|AttributeMetadataInterface[]|bool
     */
    protected function getAllowedAttributes($classOrObject, array $context, $attributesAsString = false)
    {
        if (!$this->classMetadataFactory || !isset($context[static::GROUPS]) || !is_array($context[static::GROUPS])) {
            return false;
        }

        $allowedAttributes = array();
        foreach ($this->classMetadataFactory->getMetadataFor($classOrObject)->getAttributesMetadata() as $attributeMetadata) {
            if (count(array_intersect($attributeMetadata->getGroups(), $context[static::GROUPS]))) {
                $allowedAttributes[] = $attributesAsString ? $attributeMetadata->getName() : $attributeMetadata;
            }
        }

        return $allowedAttributes;
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
     * Instantiates an object using constructor parameters when needed.
     *
     * This method also allows to denormalize data into an existing object if
     * it is present in the context with the object_to_populate key.
     *
     * @param array            $data
     * @param string           $class
     * @param array            $context
     * @param \ReflectionClass $reflectionClass
     * @param array|bool       $allowedAttributes
     *
     * @return object
     *
     * @throws RuntimeException
     */
    protected function instantiateObject(array &$data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes)
    {
        if (
            isset($context[static::OBJECT_TO_POPULATE]) &&
            is_object($context[static::OBJECT_TO_POPULATE]) &&
            $class === get_class($context[static::OBJECT_TO_POPULATE])
        ) {
            return $context[static::OBJECT_TO_POPULATE];
        }

        $format = null;
        if (isset($context['format'])) {
            $format = $context['format'];
        }

        $constructor = $reflectionClass->getConstructor();
        if (!$constructor) {
            return new $class();
        }

        $constructorParameters = $constructor->getParameters();

        $params = array();
        foreach ($constructorParameters as $constructorParameter) {
            $paramName = $constructorParameter->name;
            $key = $this->nameConverter ? $this->nameConverter->normalize($paramName) : $paramName;

            $allowed = $allowedAttributes === false || in_array($paramName, $allowedAttributes);
            $ignored = in_array($paramName, $this->ignoredAttributes);

            if (!$allowed || $ignored) {
                continue;
            }

            $missing = !isset($data[$key]) && !array_key_exists($key, $data);
            $variadic = method_exists($constructorParameter, 'isVariadic') && $constructorParameter->isVariadic();

            if ($variadic && !$missing && !is_array($data[$paramName])) {
                $message = 'Cannot create an instance of %s from serialized data because the variadic parameter %s can only accept an array.';
                throw new RuntimeException(sprintf($message, $class, $constructorParameter->name));
            }

            if ($variadic && !$missing) {
                $params = array_merge($params, $data[$paramName]);

                continue;
            }

            if ($missing && !$variadic && !$constructorParameter->isDefaultValueAvailable()) {
                $message = 'Cannot create an instance of %s from serialized data because its constructor requires parameter "%s" to be present.';
                throw new RuntimeException(sprintf($message, $class, $constructorParameter->name));
            }

            if ($missing && $constructorParameter->isDefaultValueAvailable()) {
                $params[] = $constructorParameter->getDefaultValue();

                continue;
            }

            if (!$missing) {
                $params[] = $this->denormalizeProperty($data[$key], $class, $key, $format, $context);

                unset($data[$key]);
            }
        }

        return $reflectionClass->newInstanceArgs($params);
    }

    /**
     * @param mixed  $data
     * @param string $class
     * @param string $name
     * @param string $format
     * @param array  $context
     *
     * @return mixed|object
     */
    protected function denormalizeProperty($data, $class, $name, $format = null, array $context = array())
    {
        if (!$this->propertyInfoExtractor) {
            return $data;
        }

        $types = $this->propertyInfoExtractor->getTypes($class, $name);

        if (empty($types)) {
            return $data;
        }

        foreach ($types as $type) {
            if ($data === null && $type->isNullable()) {
                return $data;
            }

            if (!$this->serializer instanceof DenormalizerInterface) {
                $message = 'Cannot denormalize attribute "%s" because injected serializer is not a denormalizer';
                throw new RuntimeException(sprintf($message, $name));
            }

            return $this->serializer->denormalize($data, $type->getClassName(), $format, $context);
        }

    }

    /**
     * Should this attribute be normalized?
     *
     * @param mixed  $object
     * @param string $attributeName
     * @param array  $context
     *
     * @return bool
     */
    protected function isAttributeToNormalize($object, $attributeName, &$context)
    {
        return !in_array($attributeName, $this->ignoredAttributes) && !$this->isMaxDepthReached(get_class($object), $attributeName, $context);
    }

    /**
     * Sets an attribute and apply the name converter if necessary.
     *
     * @param array  $data
     * @param string $attribute
     * @param mixed  $attributeValue
     *
     * @return array
     */
    protected function setAttribute(array $data, $attribute, $attributeValue)
    {
        if ($this->nameConverter) {
            $attribute = $this->nameConverter->normalize($attribute);
        }

        $data[$attribute] = $attributeValue;

        return $data;
    }

    /**
     * Normalizes complex types at the end of the process (recursive call).
     *
     * @param array       $data
     * @param array       $stack
     * @param string|null $format
     * @param array       $context
     *
     * @return array
     *
     * @throws LogicException
     */
    protected function normalizeComplexTypes(array $data, array $stack, $format, array &$context)
    {
        foreach ($stack as $attribute => $attributeValue) {
            if (!$this->serializer instanceof NormalizerInterface) {
                throw new LogicException(sprintf('Cannot normalize attribute "%s" because injected serializer is not a normalizer', $attribute));
            }

            $attributeValue = $this->serializer->normalize($attributeValue, $format, $context);

            $data = $this->setAttribute($data, $attribute, $attributeValue);
        }

        return $data;
    }

    /**
     * Is the max depth reached for the given attribute?
     *
     * @param string $class
     * @param string $attribute
     * @param array  $context
     *
     * @return bool
     */
    private function isMaxDepthReached($class, $attribute, array &$context)
    {
        if (!$this->classMetadataFactory || !isset($context[static::ENABLE_MAX_DEPTH])) {
            return false;
        }

        $classMetadata = $this->classMetadataFactory->getMetadataFor($class);
        $attributesMetadata = $classMetadata->getAttributesMetadata();

        if (!isset($attributesMetadata[$attribute])) {
            return false;
        }

        $maxDepth = $attributesMetadata[$attribute]->getMaxDepth();
        if (null === $maxDepth) {
            return false;
        }

        $key = sprintf(static::DEPTH_KEY_PATTERN, $class, $attribute);
        $keyExist = isset($context[$key]);

        if ($keyExist && $context[$key] === $maxDepth) {
            return true;
        }

        if ($keyExist) {
            ++$context[$key];
        } else {
            $context[$key] = 1;
        }

        return false;
    }
}
