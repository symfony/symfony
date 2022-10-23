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

use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Base class for a normalizer dealing with objects.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyValueNormalizer
{
    private array $typesCache = [];

    protected SerializerInterface $serializer;

    public function __construct(
        ?SerializerInterface $serializer,
        protected ?PropertyTypeExtractorInterface $propertyTypeExtractor,
        protected ?ClassDiscriminatorResolverInterface $classDiscriminatorResolver,
    )
    {
        if (null !== $serializer) {
            $this->serializer = $serializer;
        }
    }

    /**
     * Validates the submitted data and denormalizes it.
     *
     * @param Type[] $types
     *
     * @throws NotNormalizableValueException
     * @throws ExtraAttributesException
     * @throws MissingConstructorArgumentsException
     * @throws LogicException
     */
    public function validateAndDenormalize(array $types, string $currentClass, string $attribute, mixed $data, ?string $format, array $context, bool $disableTypeEnforcment, callable $createChildContext): mixed
    {
        $expectedTypes = [];
        $isUnionType = \count($types) > 1;
        $extraAttributesException = null;
        $missingConstructorArgumentException = null;
        foreach ($types as $type) {
            if ($this->isNullDenormalization($type, $currentClass, $attribute, $data, $format, $context)) {
                return null;
            }

            $data = $this->fixXmlDataForDenormalization($type, $currentClass, $attribute, $data, $format, $context);

            // This try-catch should cover all NotNormalizableValueException (and all return branches after the first
            // exception) so we could try denormalizing all types of an union type. If the target type is not an union
            // type, we will just re-throw the catched exception.
            // In the case of no denormalization succeeds with an union type, it will fall back to the default exception
            // with the acceptable types list.
            try {
                if ($this->isXmlOrCsvDataDenormalization($type, $currentClass, $attribute, $data, $format, $context)) {
                    [$data, $shouldReturnValue] = $this->handleXmlOrCsvDataDenormalization($type, $currentClass, $attribute, $data, $format, $context);
                    if ($shouldReturnValue) {
                        return $data;
                    }
                }

                if ($this->isCollectionOfObjectsDenormalization($type, $currentClass, $attribute, $data, $format, $context)) {
                    $newType = $this->getTypeFromCollectionOfObjectsForDenormalization($type, $currentClass, $attribute, $data, $format, $context);
                    $builtinType = $newType->getBuiltinType();
                    $class = $newType->getClassName();
                } elseif ($this->isMultidimensionalCollectionDenormalization($type, $currentClass, $attribute, $data, $format, $context)) {
                    $newType = $this->getTypeFromMultidimensionalCollectionForDenormalization($type, $currentClass, $attribute, $data, $format, $context);
                    $builtinType = $newType->getBuiltinType();
                    $class = $newType->getClassName();
                } else {
                    $builtinType = $type->getBuiltinType();
                    $class = $type->getClassName();
                }

                $expectedTypes[Type::BUILTIN_TYPE_OBJECT === $builtinType && $class ? $class : $builtinType] = true;

                if ($this->isObjectDenormalization($type, $builtinType, $class, $currentClass, $attribute, $data, $format, $context)) {
                    [$value, $shouldReturnValue] = $this->handleObjectDenormalization($type, $builtinType, $class, $currentClass, $attribute, $data, $format, $context, $createChildContext);

                    if ($shouldReturnValue) {
                        return $value;
                    }
                }

                if ($this->isFloatDenormalization($type, $builtinType, $class, $currentClass, $attribute, $data, $format, $context)) {
                    return $this->handleFloatDenormalization($type, $builtinType, $class, $currentClass, $attribute, $data, $format, $context, $createChildContext);
                }

                if ($this->isBooleanDenormalization($type, $builtinType, $class, $currentClass, $attribute, $data, $format, $context)) {
                    return $this->handleBooleanDenormalization($type, $builtinType, $class, $currentClass, $attribute, $data, $format, $context, $createChildContext);
                }

                if ($this->isBuiltinTypeDenormalization($type, $builtinType, $class, $currentClass, $attribute, $data, $format, $context)) {
                    return $this->handleBuiltinTypeDenormalization($type, $builtinType, $class, $currentClass, $attribute, $data, $format, $context, $createChildContext);
                }
            } catch (NotNormalizableValueException $e) {
                if (!$isUnionType) {
                    throw $e;
                }
            } catch (ExtraAttributesException $e) {
                if (!$isUnionType) {
                    throw $e;
                }

                $extraAttributesException ??= $e;
            } catch (MissingConstructorArgumentsException $e) {
                if (!$isUnionType) {
                    throw $e;
                }

                $missingConstructorArgumentException ??= $e;
            }
        }

        if ($extraAttributesException) {
            throw $extraAttributesException;
        }

        if ($missingConstructorArgumentException) {
            throw $missingConstructorArgumentException;
        }

        if ($disableTypeEnforcment) {
            return $data;
        }

        throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('The type of the "%s" attribute for class "%s" must be one of "%s" ("%s" given).', $attribute, $currentClass, implode('", "', array_keys($expectedTypes)), get_debug_type($data)), $data, array_keys($expectedTypes), $context['deserialization_path'] ?? $attribute);
    }

    /**
     * @return Type[]|null
     */
    public function getTypes(string $currentClass, string $attribute): ?array
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

    final public function getFirstCollectionValueType(Type $type): ?Type
    {
        return $type->isCollection() ? $type->getCollectionValueTypes()[0] ?? null : null;
    }

    /**
     * This should be done first when denormalizing attribute value.
     *
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     */
    final public function isNullDenormalization(Type $type, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context): bool
    {
        return null === $data && $type->isNullable();
    }

    /**
     * Fix a collection that contains the only one element.
     * This is special to xml format only.
     *
     * This should be done right after nullable check and before any other check when denormalizing attribute value.
     *
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     */
    final public function fixXmlDataForDenormalization(Type $type, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context): mixed
    {
        if ('xml' === $format && null !== $this->getFirstCollectionValueType($type) && (!\is_array($data) || !\is_int(key($data)))) {
            return [$data];
        }
        return $data;
    }

    /**
     * This should be checked first after nullable check when denormalizing attribute value.
     *
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     */
    final public function isXmlOrCsvDataDenormalization(Type $type, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context): bool
    {
        return \is_string($data) && (XmlEncoder::FORMAT === $format || CsvEncoder::FORMAT === $format);
    }

    /**
     * This can be checked anytime after nullable check when denormalizing attribute value.
     *
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     */
    final public function isCollectionOfObjectsDenormalization(Type $type, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context): bool
    {
        $collectionValueType = $this->getFirstCollectionValueType($type);
        return null !== $collectionValueType && Type::BUILTIN_TYPE_OBJECT === $collectionValueType->getBuiltinType();
    }

     /**
     * This should be checked after any other collection check when denormalizing attribute value.
     *
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     */
    final public function isMultidimensionalCollectionDenormalization(Type $type, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context): bool
    {
        $collectionValueType = $this->getFirstCollectionValueType($type);
        return $type->isCollection() && null !== $collectionValueType && Type::BUILTIN_TYPE_ARRAY === $collectionValueType->getBuiltinType();
    }

    /**
     * This should be checked after all other object checks and class modifiers when denormalizing attribute value.
     *
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     */
    final public function isObjectDenormalization(Type $originalType, string $builtinType, ?string $class, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context): bool
    {
        return Type::BUILTIN_TYPE_OBJECT === $builtinType;
    }

    /**
     * JSON only has a Number type corresponding to both int and float PHP types.
     * PHP's json_encode, JavaScript's JSON.stringify, Go's json.Marshal as well as most other JSON encoders convert
     * floating-point numbers like 12.0 to 12 (the decimal part is dropped when possible).
     * PHP's json_decode automatically converts Numbers without a decimal part to integers.
     * To circumvent this behavior, integers are converted to floats when denormalizing JSON based formats and when
     * a float is expected.
     *
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     */
    final public function isFloatDenormalization(Type $type, string $builtinType, ?string $class, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context): bool
    {
        return Type::BUILTIN_TYPE_FLOAT === $builtinType && \is_int($data) && null !== $format && str_contains($format, JsonEncoder::FORMAT);
    }

    /**
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     */
    final public function isBooleanDenormalization(Type $type, string $builtinType, ?string $class, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context): bool
    {
        return (Type::BUILTIN_TYPE_FALSE === $builtinType && false === $data) || (Type::BUILTIN_TYPE_TRUE === $builtinType && true === $data);
    }

    /**
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     */
    final public function isBuiltinTypeDenormalization(Type $type, string $builtinType, ?string $class, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context): bool
    {
        return ('is_'.$builtinType)($data);
    }

    /**
     * In XML and CSV all basic datatypes are represented as strings, it is e.g. not possible to determine,
     * if a value is meant to be a string, float, int or a boolean value from the serialized representation.
     * That's why we have to transform the values, if one of these non-string basic datatypes is expected.
     *
     * Returns the parsed data and whether to return directly the value.
     *
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     *
     * @return array{0: mixed, 1: bool}
     */
    final public function handleXmlOrCsvDataDenormalization(Type $type, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context): array
    {
        if ('' === $data) {
            if (Type::BUILTIN_TYPE_ARRAY === $builtinType = $type->getBuiltinType()) {
                return [[], true];
            }

            if ($type->isNullable() && \in_array($builtinType, [Type::BUILTIN_TYPE_BOOL, Type::BUILTIN_TYPE_INT, Type::BUILTIN_TYPE_FLOAT], true)) {
                return [null, true];
            }
        }

        switch ($builtinType ?? $type->getBuiltinType()) {
            case Type::BUILTIN_TYPE_BOOL:
                // according to https://www.w3.org/TR/xmlschema-2/#boolean, valid representations are "false", "true", "0" and "1"
                if ('false' === $data || '0' === $data) {
                    $data = false;
                } elseif ('true' === $data || '1' === $data) {
                    $data = true;
                } else {
                    throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('The type of the "%s" attribute for class "%s" must be bool ("%s" given).', $attribute, $currentClass, $data), $data, [Type::BUILTIN_TYPE_BOOL], $context['deserialization_path'] ?? null);
                }
                break;
            case Type::BUILTIN_TYPE_INT:
                if (ctype_digit('-' === $data[0] ? substr($data, 1) : $data)) {
                    $data = (int) $data;
                } else {
                    throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('The type of the "%s" attribute for class "%s" must be int ("%s" given).', $attribute, $currentClass, $data), $data, [Type::BUILTIN_TYPE_INT], $context['deserialization_path'] ?? null);
                }
                break;
            case Type::BUILTIN_TYPE_FLOAT:
                if (is_numeric($data)) {
                    return [(float) $data, true];
                }

                return [match ($data) {
                    'NaN' => \NAN,
                    'INF' => \INF,
                    '-INF' => -\INF,
                    default => throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('The type of the "%s" attribute for class "%s" must be float ("%s" given).', $attribute, $currentClass, $data), $data, [Type::BUILTIN_TYPE_FLOAT], $context['deserialization_path'] ?? null),
                }, true];
        }

        return [$data, false];
    }

    /**
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     *
     * @return Type A new type containing the class for ArrayDenormalizer and the builtinType.
     */
    final public function getTypeFromCollectionOfObjectsForDenormalization(Type $type, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context): Type
    {
        $builtinType = Type::BUILTIN_TYPE_OBJECT;
        $collectionValueType = $this->getFirstCollectionValueType($type);
        $class = $collectionValueType->getClassName().'[]';

        if (\count($collectionKeyType = $type->getCollectionKeyTypes()) > 0) {
            [$context['key_type']] = $collectionKeyType;
        }

        $context['value_type'] = $collectionValueType;

        return new Type($builtinType, false, $class);
    }

    /**
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     *
     * @return Type A new type containing the class for ArrayDenormalizer and the builtinType.
     */
    final public function getTypeFromMultidimensionalCollectionForDenormalization(Type $type, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context): Type
    {
        // get inner type for any nested array
        $innerType = $this->getFirstCollectionValueType($type);

        // note that it will break for any other builtinType
        $dimensions = '[]';
        while (\count($innerType->getCollectionValueTypes()) > 0 && Type::BUILTIN_TYPE_ARRAY === $innerType->getBuiltinType()) {
            $dimensions .= '[]';
            [$innerType] = $innerType->getCollectionValueTypes();
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

        return new Type($builtinType, false, $class);
    }

    /**
     * Returns the denormalized data and whether to return directly the value.
     *
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     *
     * @return array{0: mixed, 1: bool}
     */
    final public function handleObjectDenormalization(Type $type, string $builtinType, ?string $class, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context, callable $createChildContext): array
    {
        if (!$this->serializer instanceof DenormalizerInterface) {
            throw new LogicException(sprintf('Cannot denormalize attribute "%s" for class "%s" because injected serializer is not a denormalizer.', $attribute, $class));
        }

        $childContext = \call_user_func($createChildContext, $context, $attribute, $format);
        if ($this->serializer->supportsDenormalization($data, $class, $format, $childContext)) {
            return [$this->serializer->denormalize($data, $class, $format, $childContext), true];
        }

        return [null, false];
    }

    /**
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     */
    final public function handleFloatDenormalization(Type $type, string $builtinType, ?string $class, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context, callable $createChildContext): float
    {
        return (float) $data;
    }

    /**
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     */
    final public function handleBooleanDenormalization(Type $type, string $builtinType, ?string $class, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context, callable $createChildContext): mixed {
        return $data;
    }

    /**
     * This method is not meant to be overriden, only used. You might want to override validateAndDenormalize.
     */
    final public function handleBuiltinTypeDenormalization(Type $type, string $builtinType, ?string $class, string $currentClass, string $attribute, mixed $data, ?string $format, array &$context, callable $createChildContext): mixed {
        return $data;
    }

    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }
}
