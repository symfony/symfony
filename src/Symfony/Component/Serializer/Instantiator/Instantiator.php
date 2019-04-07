<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Instantiator;

use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\ObjectToPopulateTrait;

/**
 * @author Jérôme Desjardins <jewome62@gmail.com>
 */
class Instantiator implements InstantiatorInterface, DenormalizerAwareInterface
{
    public const DEFAULT_CONSTRUCTOR_ARGUMENTS = AbstractObjectNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS;

    use ObjectToPopulateTrait;
    use DenormalizerAwareTrait;

    private $classDiscriminatorResolver;
    private $propertyListExtractor;
    private $nameConverter;

    public function __construct(PropertyListExtractorInterface $propertyListExtractor = null, NameConverterInterface $nameConverter = null)
    {
        $this->propertyListExtractor = $propertyListExtractor;
        $this->nameConverter = $nameConverter;
    }

    /**
     * {@inheritdoc}
     */
    public function instantiate(string $class, $data, $format = null, array $context = [])
    {
        $allowedAttributes = $this->propertyListExtractor ? $this->propertyListExtractor->getProperties($class, $context) : null;
        $reflectionClass = new \ReflectionClass($class);
        $constructor = $reflectionClass->getConstructor();

        if (null === $constructor) {
            return new $class();
        }

        $constructorParameters = $constructor->getParameters();

        $params = [];
        foreach ($constructorParameters as $constructorParameter) {
            $paramName = $constructorParameter->name;
            $key = $this->nameConverter ? $this->nameConverter->normalize($paramName, $class, $format, $context) : $paramName;
            $allowed = null === $allowedAttributes || \in_array($paramName, $allowedAttributes, true);

            if ($allowed && $constructorParameter->isVariadic() && \array_key_exists($key, $data)) {
                if (!\is_array($data[$paramName])) {
                    throw new RuntimeException(sprintf('Cannot create an instance of %s from serialized data because the variadic parameter %s can only accept an array.', $class, $constructorParameter->name));
                }

                $params = array_merge($params, $data[$paramName]);
            } elseif ($allowed && \array_key_exists($key, $data)) {
                $parameterData = $data[$key];

                if (null === $parameterData && $constructorParameter->allowsNull()) {
                    $params[] = null;

                    continue;
                }

                $params[] = $this->denormalizeParameter($reflectionClass, $constructorParameter, $paramName, $parameterData, $context, $format);
            } elseif (\array_key_exists($key, $context[self::DEFAULT_CONSTRUCTOR_ARGUMENTS][$class] ?? [])) {
                $params[] = $context[self::DEFAULT_CONSTRUCTOR_ARGUMENTS][$class][$key];
            } elseif ($constructorParameter->isDefaultValueAvailable()) {
                $params[] = $constructorParameter->getDefaultValue();
            } else {
                throw new MissingConstructorArgumentsException(sprintf('Cannot create an instance of %s from serialized data because its constructor requires parameter "%s" to be present.', $class, $constructorParameter->name));
            }
        }

        if ($constructor->isConstructor()) {
            return $reflectionClass->newInstanceArgs($params);
        }

        return $constructor->invokeArgs(null, $params);
    }

    private function denormalizeParameter(\ReflectionClass $class, \ReflectionParameter $parameter, $parameterName, $parameterData, array $context, $format = null)
    {
        try {
            if (null !== $parameter->getClass()) {
                $parameterClass = $parameter->getClass()->getName();

                if (null === $this->denormalizer) {
                    throw new MissingConstructorArgumentsException(sprintf('Could not create object of class "%s" of the parameter "%s".', $parameterClass, $parameterName));
                }

                $parameterData = $this->denormalizer->denormalize($parameterData, $parameterClass, $format, $context);
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
}
