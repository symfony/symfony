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
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectToPopulateTrait;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * @author Jérôme Desjardins <jewome62@gmail.com>
 */
class Instantiator implements InstantiatorInterface, SerializerAwareInterface
{
    use ObjectToPopulateTrait;
    use SerializerAwareTrait;

    private $classDiscriminatorResolver;
    private $propertyListExtractor;
    private $nameConverter;

    public function __construct(ClassDiscriminatorResolverInterface $classDiscriminatorResolver, PropertyListExtractorInterface $propertyListExtractor, NameConverterInterface $nameConverter)
    {
        $this->classDiscriminatorResolver = $classDiscriminatorResolver;
        $this->propertyListExtractor = $propertyListExtractor;
        $this->nameConverter = $nameConverter;
    }

    public function instantiate(string $class, $data, $format = null, array $context = [])
    {
        if ($this->classDiscriminatorResolver && $mapping = $this->classDiscriminatorResolver->getMappingForClass($class)) {
            if (!isset($data[$mapping->getTypeProperty()])) {
                throw new RuntimeException(sprintf('Type property "%s" not found for the abstract object "%s"', $mapping->getTypeProperty(), $class));
            }

            $type = $data[$mapping->getTypeProperty()];
            if (null === ($mappedClass = $mapping->getClassForType($type))) {
                throw new RuntimeException(sprintf('The type "%s" has no mapped class for the abstract object "%s"', $type, $class));
            }

            $class = $mappedClass;
        }

        $reflectionClass = new \ReflectionClass($class);

        if (null !== $object = $this->extractObjectToPopulate($class, $context, AbstractNormalizer::OBJECT_TO_POPULATE)) {
            unset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);

            return $object;
        }

        $defaultConstructionArgumentKey = $context['defaultConstructionArgumentKey'] ?? AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS
        $allowedAttributes = $this->propertyListExtractor->getProperties($class, $context);
        $constructor = $reflectionClass->getConstructor();
        if ($constructor) {
            $constructorParameters = $constructor->getParameters();

            $params = [];
            foreach ($constructorParameters as $constructorParameter) {
                $paramName = $constructorParameter->name;
                $key = $this->nameConverter ? $this->nameConverter->normalize($paramName, $class, $format, $context) : $paramName;

                $allowed = false === $allowedAttributes || \in_array($paramName, $allowedAttributes);
                if ($constructorParameter->isVariadic()) {
                    if ($allowed && (isset($data[$key]) || \array_key_exists($key, $data))) {
                        if (!\is_array($data[$paramName])) {
                            throw new RuntimeException(sprintf('Cannot create an instance of %s from serialized data because the variadic parameter %s can only accept an array.', $class, $constructorParameter->name));
                        }

                        $params = array_merge($params, $data[$paramName]);
                    }
                } elseif ($allowed && (isset($data[$key]) || \array_key_exists($key, $data))) {
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
                } elseif (\array_key_exists($key, $context[$defaultConstructionArgumentKey][$class] ?? [])) {
                    $params[] = $context[$defaultConstructionArgumentKey][$class][$key];
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

        return new $class();
    }

    public function createChildContext(string $class, string $attribute, $parentData, array $parentContext = [])
    {
        if (isset($parentContext[AbstractNormalizer::ATTRIBUTES][$attribute])) {
            $parentContext[AbstractNormalizer::ATTRIBUTES] = $parentContext[AbstractNormalizer::ATTRIBUTES][$attribute];
        } else {
            unset($parentContext[AbstractNormalizer::ATTRIBUTES]);
        }

        return $parentContext;
    }

    private function denormalizeParameter(\ReflectionClass $class, \ReflectionParameter $parameter, $parameterName, $parameterData, array $context, $format = null)
    {
        try {
            if (null !== $parameter->getClass()) {
                if (!$this->serializer instanceof DenormalizerInterface) {
                    throw new LogicException(sprintf('Cannot create an instance of %s from serialized data because the serializer inject in "%s" is not a denormalizer', $parameter->getClass(), self::class));
                }
                $parameterClass = $parameter->getClass()->getName();
                $parameterData = $this->serializer->denormalize($parameterData, $parameterClass, $format, $this->createContext($context, $parameterName));
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