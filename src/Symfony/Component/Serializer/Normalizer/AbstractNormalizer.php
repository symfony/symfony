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
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;

/**
 * Normalizer implementation.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
abstract class AbstractNormalizer extends SerializerAwareNormalizer implements NormalizerInterface, DenormalizerInterface
{
    protected $circularReferenceLimit = 1;
    protected $circularReferenceHandler;
    protected $classMetadataFactory;
    protected $callbacks = array();
    protected $ignoredAttributes = array();
    protected $camelizedAttributes = array();

    /**
     * Sets the {@link ClassMetadataFactory} to use.
     *
     * @param ClassMetadataFactory $classMetadataFactory
     */
    public function __construct(ClassMetadataFactory $classMetadataFactory = null)
    {
        $this->classMetadataFactory = $classMetadataFactory;
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
     * @param array $camelizedAttributes
     *
     * @return self
     */
    public function setCamelizedAttributes(array $camelizedAttributes)
    {
        $this->camelizedAttributes = $camelizedAttributes;

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
     * @param string $attributeName
     * @return string
     */
    protected function formatAttribute($attributeName)
    {
        if (in_array($attributeName, $this->camelizedAttributes)) {
            return preg_replace_callback('/(^|_|\.)+(.)/', function ($match) {
                return ('.' === $match[1] ? '_' : '').strtoupper($match[2]);
            }, $attributeName);
        }

        return $attributeName;
    }

    /**
     * Gets attributes to normalize using groups.
     *
     * @param string|object $classOrObject
     * @param array $context
     * @return array|bool
     */
    protected function getAllowedAttributes($classOrObject, array $context)
    {
        if (!$this->classMetadataFactory || !isset($context['groups']) || !is_array($context['groups'])) {
            return false;
        }

        $allowedAttributes = array();
        foreach ($this->classMetadataFactory->getMetadataFor($classOrObject)->getAttributesGroups() as $group => $attributes) {
            if (in_array($group, $context['groups'])) {
                $allowedAttributes = array_merge($allowedAttributes, $attributes);
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
        if (is_array($data) || is_object($data) && $data instanceof \ArrayAccess) {
            $normalizedData = $data;
        } elseif (is_object($data)) {
            $normalizedData = array();

            foreach ($data as $attribute => $value) {
                $normalizedData[$attribute] = $value;
            }
        } else {
            $normalizedData = array();
        }

        return $normalizedData;
    }

    /**
     * Instantiates an object using contructor parameters when needed.
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
    protected function instantiateObject(array $data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes)
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
                $paramName = lcfirst($this->formatAttribute($constructorParameter->name));

                $allowed = $allowedAttributes === false || in_array($paramName, $allowedAttributes);
                $ignored = in_array($paramName, $this->ignoredAttributes);
                if ($allowed && !$ignored && isset($data[$paramName])) {
                    $params[] = $data[$paramName];
                    // don't run set for a parameter passed to the constructor
                    unset($data[$paramName]);
                } elseif ($constructorParameter->isOptional()) {
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
