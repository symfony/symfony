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

use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;

/**
 * Normalizer implementation.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
abstract class AbstractNormalizer extends SerializerAwareNormalizer implements NormalizerInterface, DenormalizerInterface
{
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
