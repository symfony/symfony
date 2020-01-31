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

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Context\ChildContextFactoryInterface;
use Symfony\Component\Serializer\Context\ObjectChildContextFactory;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\ObjectToPopulateTrait;

/**
 * Instantiates an object using constructor parameters when needed.
 *
 * This class also allows to denormalize data into an existing object if
 * it is present in the context with the object_to_populate. This object
 * is removed from the context before being returned to avoid side effects
 * when recursively normalizing an object graph.
 *
 * @author Jérôme Desjardins <jewome62@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
class Instantiator implements InstantiatorInterface, DenormalizerAwareInterface
{
    public const ATTRIBUTES = AbstractObjectNormalizer::ATTRIBUTES;
    public const IGNORED_ATTRIBUTES = AbstractObjectNormalizer::IGNORED_ATTRIBUTES;
    public const OBJECT_TO_POPULATE = AbstractObjectNormalizer::OBJECT_TO_POPULATE;
    public const DEFAULT_CONSTRUCTOR_ARGUMENTS = AbstractObjectNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS;

    use ObjectToPopulateTrait;
    use DenormalizerAwareTrait;

    private $classDiscriminatorResolver;
    private $propertyTypeExtractor;
    private $propertyListExtractor;
    private $nameConverter;
    private $propertyAccessor;
    private $childContextFactory;

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory = null, ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null, PropertyTypeExtractorInterface $propertyTypeExtractor = null, PropertyListExtractorInterface $propertyListExtractor = null, NameConverterInterface $nameConverter = null, PropertyAccessorInterface $propertyAccessor = null, ChildContextFactoryInterface $childContextFactory = null)
    {
        if (null === $classDiscriminatorResolver && null !== $classMetadataFactory) {
            $classDiscriminatorResolver = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);
        }
        $this->classDiscriminatorResolver = $classDiscriminatorResolver;

        $this->propertyTypeExtractor = $propertyTypeExtractor;
        if (null === $propertyListExtractor && null !== $classMetadataFactory) {
            $propertyListExtractor = new SerializerExtractor($classMetadataFactory);
        }
        $this->propertyListExtractor = $propertyListExtractor;
        $this->nameConverter = $nameConverter;
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();

        $this->childContextFactory = $childContextFactory ?? new ObjectChildContextFactory();
    }

    /**
     * {@inheritdoc}
     */
    public function instantiate(string $class, array $data, array $context, string $format = null): InstantiatorResult
    {
        if (null !== $this->classDiscriminatorResolver && $mapping = $this->classDiscriminatorResolver->getMappingForClass($class)) {
            $class = $this->handleDiscriminator($class, $data, $mapping);
        }

        if (null !== $object = $this->extractObjectToPopulate($class, $context, self::OBJECT_TO_POPULATE)) {
            unset($context[self::OBJECT_TO_POPULATE]);

            return new InstantiatorResult($object, $data, $context);
        }

        // clean up even if no match
        unset($context[self::OBJECT_TO_POPULATE]);

        $allowedAttributes = $this->propertyListExtractor ? $this->propertyListExtractor->getProperties($class, $context) : null;
        $reflectionClass = new \ReflectionClass($class);
        $constructor = $this->getConstructor($reflectionClass);

        if (null === $constructor || !$constructor->isPublic()) {
            return new InstantiatorResult($reflectionClass->newInstanceWithoutConstructor(), $data, $context);
        }

        $constructorParameters = $constructor->getParameters();

        $params = [];
        foreach ($constructorParameters as $constructorParameter) {
            $paramName = $constructorParameter->name;
            $key = $this->nameConverter ? $this->nameConverter->normalize($paramName, $class, $format, $context) : $paramName;
            $allowed = (null === $allowedAttributes || \in_array($paramName, $allowedAttributes, true)) && $this->isAllowedAttribute($object, $paramName, $format, $context);
            $childContext = $this->childContextFactory->create($context, $paramName, $format);

            if ($allowed && $constructorParameter->isVariadic()) {
                if (!\array_key_exists($paramName, $data)) {
                    $data[$paramName] = [];
                }

                if (!\is_array($data[$paramName])) {
                    throw new RuntimeException(sprintf('Cannot create an instance of "%s" from serialized data because the variadic parameter "%s" can only accept an array.', $class, $constructorParameter->name));
                }

                $variadicParameters = [];
                foreach ($data[$paramName] as $parameterData) {
                    [$currentParameter, $error] = $this->denormalizeParameter($reflectionClass, $constructorParameter, $paramName, $parameterData, $childContext, $format);

                    if (null !== $error) {
                        return new InstantiatorResult(null, $data, $context, $error);
                    } else {
                        $variadicParameters[] = $currentParameter;
                    }
                }

                $params = array_merge($params, $variadicParameters);
                unset($data[$key]);
            } elseif ($allowed && \array_key_exists($key, $data)) {
                $parameterData = $data[$key];

                if (null === $parameterData && $constructorParameter->allowsNull()) {
                    $params[] = null;

                    unset($data[$key]);
                    continue;
                }

                [$currentParameter, $error] = $this->denormalizeParameter($reflectionClass, $constructorParameter, $paramName, $parameterData, $childContext, $format);

                if (null !== $error) {
                    return new InstantiatorResult(null, $data, $context, $error);
                }
                $params[] = $currentParameter;
                unset($data[$key]);
            } elseif (\array_key_exists($key, $context[self::DEFAULT_CONSTRUCTOR_ARGUMENTS][$class] ?? [])) {
                $params[] = $context[self::DEFAULT_CONSTRUCTOR_ARGUMENTS][$class][$key];
            } elseif ($constructorParameter->isDefaultValueAvailable()) {
                $params[] = $constructorParameter->getDefaultValue();
            } else {
                return new InstantiatorResult(null, $data, $context, sprintf('Cannot create an instance of "%s" from serialized data because its constructor requires parameter "%s" to be present.', $class, $constructorParameter->name));
            }
        }

        if ($constructor->isConstructor()) {
            return new InstantiatorResult($reflectionClass->newInstanceArgs($params), $data, $context);
        }

        return new InstantiatorResult($constructor->invokeArgs(null, $params), $data, $context);
    }

    private function handleDiscriminator(string $class, array $data, ClassDiscriminatorMapping $mapping): string
    {
        if (!isset($data[$mapping->getTypeProperty()])) {
            throw new RuntimeException(sprintf('Type property "%s" not found for the abstract object "%s".', $mapping->getTypeProperty(), $class));
        }

        $type = $data[$mapping->getTypeProperty()];
        if (null === ($mappedClass = $mapping->getClassForType($type))) {
            throw new RuntimeException(sprintf('The type "%s" has no mapped class for the abstract object "%s".', $type, $class));
        }

        return $mappedClass;
    }

    private function denormalizeParameter(\ReflectionClass $class, \ReflectionParameter $parameter, $parameterName, $parameterData, array $context, $format = null): array
    {
        try {
            $parameterClass = $parameter->getClass();
            if (null === $parameterClass && null !== $this->propertyTypeExtractor) {
                $types = $this->propertyTypeExtractor->getTypes($class->getName(), $parameterName, $context);

                if (null !== $types) {
                    foreach ($types as $type) {
                        $collectionValueType = $type->isCollection() ? $type->getCollectionValueType() : null;

                        if (null !== $collectionValueType && Type::BUILTIN_TYPE_OBJECT === $collectionValueType->getBuiltinType()) {
                            $builtinType = Type::BUILTIN_TYPE_OBJECT;
                            $class = $collectionValueType->getClassName().'[]';

                            if (null !== $collectionKeyType = $type->getCollectionKeyType()) {
                                $context['key_type'] = $collectionKeyType;
                            }
                        } elseif ($type->isCollection() && null !== $collectionValueType && Type::BUILTIN_TYPE_ARRAY === $collectionValueType->getBuiltinType()) {
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

                        if (Type::BUILTIN_TYPE_OBJECT === $builtinType) {
                            if (null === $this->denormalizer) {
                                throw new MissingConstructorArgumentsException(sprintf('Could not create object of class "%s" of the parameter "%s".', $class, $parameterName));
                            }

                            if ($this->denormalizer->supportsDenormalization($parameterData, $class, $format, $context)) {
                                return [$this->denormalizer->denormalize($parameterData, $class, $format, $context), null];
                            }
                        }
                    }
                }
            }

            if (null !== $parameterClass) {
                $parameterClassName = $parameter->getClass()->getName();

                if (null === $this->denormalizer) {
                    throw new MissingConstructorArgumentsException(sprintf('Could not create object of class "%s" of the parameter "%s".', $parameterClassName, $parameterName));
                }

                $parameterData = $this->denormalizer->denormalize($parameterData, $parameterClassName, $format, $context);
            }
        } catch (\ReflectionException $e) {
            throw new RuntimeException(sprintf('Could not determine the class of the parameter "%s".', $parameterName), 0, $e);
        } catch (MissingConstructorArgumentsException $e) {
            if (!$parameter->getType()->allowsNull()) {
                return [null, $e->getMessage()];
            }
            $parameterData = null;
        }

        return [$parameterData, null];
    }

    protected function getConstructor(\ReflectionClass $reflectionClass): ?\ReflectionMethod
    {
        return $reflectionClass->getConstructor();
    }

    /**
     * @param object|string $classOrObject
     */
    private function isAllowedAttribute($classOrObject, string $attribute, string $format = null, array $context = []): bool
    {
        $ignoredAttributes = $context[self::IGNORED_ATTRIBUTES] ?? $this->defaultContext[self::IGNORED_ATTRIBUTES] ?? [];
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
}
