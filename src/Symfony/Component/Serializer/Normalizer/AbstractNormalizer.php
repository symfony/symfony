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
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Normalizer implementation.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
abstract class AbstractNormalizer extends SerializerAwareNormalizer implements NormalizerInterface, DenormalizerInterface
{
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
     * Sets the {@link ClassMetadataFactoryInterface} to use.
     *
     * @param ClassMetadataFactoryInterface|null $classMetadataFactory
     * @param NameConverterInterface|null        $nameConverter
     */
    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory = null, NameConverterInterface $nameConverter = null)
    {
        $this->classMetadataFactory = $classMetadataFactory;
        $this->nameConverter = $nameConverter;
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
     *
     * @throws InvalidArgumentException
     */
    public function setCircularReferenceHandler($circularReferenceHandler)
    {
        if (!is_callable($circularReferenceHandler)) {
            throw new InvalidArgumentException('The given circular reference handler is not callable.');
        }

        $this->circularReferenceHandler = $circularReferenceHandler;

        return $this;
    }

    /**
     * Set normalization callbacks.
     *
     * @param array $callbacks help normalize the result
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
     * Set attributes to be camelized on denormalize.
     *
     * @deprecated Deprecated since version 2.7, to be removed in 3.0. Use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter instead.
     *
     * @param array $camelizedAttributes
     *
     * @return self
     *
     * @throws LogicException
     */
    public function setCamelizedAttributes(array $camelizedAttributes)
    {
        @trigger_error(sprintf('%s is deprecated since version 2.7 and will be removed in 3.0. Use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter instead.', __METHOD__), E_USER_DEPRECATED);

        if ($this->nameConverter && !$this->nameConverter instanceof CamelCaseToSnakeCaseNameConverter) {
            throw new LogicException(sprintf('%s cannot be called if a custom Name Converter is defined.', __METHOD__));
        }

        $attributes = array();
        foreach ($camelizedAttributes as $camelizedAttribute) {
            $attributes[] = lcfirst(preg_replace_callback('/(^|_|\.)+(.)/', function ($match) {
                return ('.' === $match[1] ? '_' : '').strtoupper($match[2]);
            }, $camelizedAttribute));
        }

        $this->nameConverter = new CamelCaseToSnakeCaseNameConverter($attributes);

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

        if (isset($context['circular_reference_limit'][$objectHash])) {
            if ($context['circular_reference_limit'][$objectHash] >= $this->circularReferenceLimit) {
                unset($context['circular_reference_limit'][$objectHash]);

                return true;
            }

            $context['circular_reference_limit'][$objectHash]++;
        } else {
            $context['circular_reference_limit'][$objectHash] = 1;
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
     * Format an attribute name, for example to convert a snake_case name to camelCase.
     *
     * @deprecated Deprecated since version 2.7, to be removed in 3.0. Use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter instead.
     *
     * @param string $attributeName
     *
     * @return string
     */
    protected function formatAttribute($attributeName)
    {
        @trigger_error(sprintf('%s is deprecated since version 2.7 and will be removed in 3.0. Use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter instead.', __METHOD__), E_USER_DEPRECATED);

        return $this->nameConverter ? $this->nameConverter->normalize($attributeName) : $attributeName;
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
        if (!$this->classMetadataFactory || !isset($context['groups']) || !is_array($context['groups'])) {
            return false;
        }

        $allowedAttributes = array();
        foreach ($this->classMetadataFactory->getMetadataFor($classOrObject)->getAttributesMetadata() as $attributeMetadata) {
            if (count(array_intersect($attributeMetadata->getGroups(), $context['groups']))) {
                $allowedAttributes[] = $attributesAsString ? $attributeMetadata->getName() : $attributeMetadata;
            }
        }

        return array_unique($allowedAttributes);
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
            isset($context['object_to_populate']) &&
            is_object($context['object_to_populate']) &&
            $class === get_class($context['object_to_populate'])
        ) {
            return $context['object_to_populate'];
        }

        $constructor = $reflectionClass->getConstructor();
        if ($constructor) {
            $constructorParameters = $constructor->getParameters();

            $params = array();
            foreach ($constructorParameters as $constructorParameter) {
                $paramName = $constructorParameter->name;
                $key = $this->nameConverter ? $this->nameConverter->normalize($paramName) : $paramName;

                $allowed = $allowedAttributes === false || in_array($paramName, $allowedAttributes);
                $ignored = in_array($paramName, $this->ignoredAttributes);
                if ($allowed && !$ignored && array_key_exists($key, $data)) {
                    $params[] = $data[$key];
                    // don't run set for a parameter passed to the constructor
                    unset($data[$key]);
                } elseif ($constructorParameter->isDefaultValueAvailable()) {
                    $params[] = $constructorParameter->getDefaultValue();
                } else {
                    throw new RuntimeException(
                        sprintf(
                            'Cannot create an instance of %s from serialized data because its constructor requires parameter "%s" to be present.',
                            $class,
                            $constructorParameter->name
                        )
                    );
                }
            }

            return $reflectionClass->newInstanceArgs($params);
        }

        return new $class();
    }
}
