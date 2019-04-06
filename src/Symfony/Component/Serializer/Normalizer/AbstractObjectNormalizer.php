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

use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Base class for a normalizer dealing with objects.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
abstract class AbstractObjectNormalizer extends AbstractNormalizer
{
    const ENABLE_MAX_DEPTH = 'enable_max_depth';
    const DEPTH_KEY_PATTERN = 'depth_%s::%s';
    const DISABLE_TYPE_ENFORCEMENT = 'disable_type_enforcement';

    private $propertyTypeExtractor;
    private $attributesCache = [];
    private $cache = [];

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory = null, NameConverterInterface $nameConverter = null, PropertyTypeExtractorInterface $propertyTypeExtractor = null)
    {
        parent::__construct($classMetadataFactory, $nameConverter);

        $this->propertyTypeExtractor = $propertyTypeExtractor;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return \is_object($data) && !$data instanceof \Traversable;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = [])
    {
        if (!isset($context['cache_key'])) {
            $context['cache_key'] = $this->getCacheKey($format, $context);
        }

        if ($this->isCircularReference($object, $context)) {
            return $this->handleCircularReference($object);
        }

        $data = [];
        $stack = [];
        $attributes = $this->getAttributes($object, $format, $context);
        $class = \get_class($object);
        $attributesMetadata = $this->classMetadataFactory ? $this->classMetadataFactory->getMetadataFor($class)->getAttributesMetadata() : null;

        foreach ($attributes as $attribute) {
            if (null !== $attributesMetadata && $this->isMaxDepthReached($attributesMetadata, $class, $attribute, $context)) {
                continue;
            }

            $attributeValue = $this->getAttributeValue($object, $attribute, $format, $context);

            if (isset($this->callbacks[$attribute])) {
                $attributeValue = \call_user_func($this->callbacks[$attribute], $attributeValue);
            }

            if (null !== $attributeValue && !is_scalar($attributeValue)) {
                $stack[$attribute] = $attributeValue;
            }

            $data = $this->updateData($data, $attribute, $attributeValue);
        }

        foreach ($stack as $attribute => $attributeValue) {
            if (!$this->serializer instanceof NormalizerInterface) {
                throw new LogicException(sprintf('Cannot normalize attribute "%s" because the injected serializer is not a normalizer', $attribute));
            }

            $data = $this->updateData($data, $attribute, $this->serializer->normalize($attributeValue, $format, $this->createChildContext($context, $attribute, $format)));
        }

        return $data;
    }

    /**
     * Gets and caches attributes for the given object, format and context.
     *
     * @param object      $object
     * @param string|null $format
     * @param array       $context
     *
     * @return string[]
     */
    protected function getAttributes($object, $format = null, array $context)
    {
        $class = \get_class($object);
        $key = $class.'-'.$context['cache_key'];

        if (isset($this->attributesCache[$key])) {
            return $this->attributesCache[$key];
        }

        $allowedAttributes = $this->getAllowedAttributes($object, $context, true);

        if (false !== $allowedAttributes) {
            if ($context['cache_key']) {
                $this->attributesCache[$key] = $allowedAttributes;
            }

            return $allowedAttributes;
        }

        $attributes = $this->extractAttributes($object, $format, $context);

        if ($context['cache_key']) {
            $this->attributesCache[$key] = $attributes;
        }

        return $attributes;
    }

    /**
     * Extracts attributes to normalize from the class of the given object, format and context.
     *
     * @param object      $object
     * @param string|null $format
     * @param array       $context
     *
     * @return string[]
     */
    abstract protected function extractAttributes($object, $format = null, array $context = []);

    /**
     * Gets the attribute value.
     *
     * @param object      $object
     * @param string      $attribute
     * @param string|null $format
     * @param array       $context
     *
     * @return mixed
     */
    abstract protected function getAttributeValue($object, $attribute, $format = null, array $context = []);

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return isset($this->cache[$type]) ? $this->cache[$type] : $this->cache[$type] = class_exists($type);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        if (!isset($context['cache_key'])) {
            $context['cache_key'] = $this->getCacheKey($format, $context);
        }

        $allowedAttributes = $this->getAllowedAttributes($class, $context, true);
        $normalizedData = $this->prepareForDenormalization($data);
        $extraAttributes = [];

        $reflectionClass = new \ReflectionClass($class);
        $object = $this->instantiateObject($normalizedData, $class, $context, $reflectionClass, $allowedAttributes, $format);

        foreach ($normalizedData as $attribute => $value) {
            if ($this->nameConverter) {
                $attribute = $this->nameConverter->denormalize($attribute);
            }

            if ((false !== $allowedAttributes && !\in_array($attribute, $allowedAttributes)) || !$this->isAllowedAttribute($class, $attribute, $format, $context)) {
                if (isset($context[self::ALLOW_EXTRA_ATTRIBUTES]) && !$context[self::ALLOW_EXTRA_ATTRIBUTES]) {
                    $extraAttributes[] = $attribute;
                }

                continue;
            }

            $value = $this->validateAndDenormalize($class, $attribute, $value, $format, $context);
            try {
                $this->setAttributeValue($object, $attribute, $value, $format, $context);
            } catch (InvalidArgumentException $e) {
                throw new NotNormalizableValueException($e->getMessage(), $e->getCode(), $e);
            }
        }

        if (!empty($extraAttributes)) {
            throw new ExtraAttributesException($extraAttributes);
        }

        return $object;
    }

    /**
     * Sets attribute value.
     *
     * @param object      $object
     * @param string      $attribute
     * @param mixed       $value
     * @param string|null $format
     * @param array       $context
     */
    abstract protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = []);

    /**
     * Validates the submitted data and denormalizes it.
     *
     * @param string      $currentClass
     * @param string      $attribute
     * @param mixed       $data
     * @param string|null $format
     * @param array       $context
     *
     * @return mixed
     *
     * @throws NotNormalizableValueException
     * @throws LogicException
     */
    private function validateAndDenormalize($currentClass, $attribute, $data, $format, array $context)
    {
        if (null === $this->propertyTypeExtractor || null === $types = $this->propertyTypeExtractor->getTypes($currentClass, $attribute)) {
            return $data;
        }

        $expectedTypes = [];
        foreach ($types as $type) {
            if (null === $data && $type->isNullable()) {
                return;
            }

            if ($type->isCollection() && null !== ($collectionValueType = $type->getCollectionValueType()) && Type::BUILTIN_TYPE_OBJECT === $collectionValueType->getBuiltinType()) {
                $builtinType = Type::BUILTIN_TYPE_OBJECT;
                $class = $collectionValueType->getClassName().'[]';

                // Fix a collection that contains the only one element
                // This is special to xml format only
                if ('xml' === $format && !\is_int(key($data))) {
                    $data = [$data];
                }

                if (null !== $collectionKeyType = $type->getCollectionKeyType()) {
                    $context['key_type'] = $collectionKeyType;
                }
            } else {
                $builtinType = $type->getBuiltinType();
                $class = $type->getClassName();
            }

            $expectedTypes[Type::BUILTIN_TYPE_OBJECT === $builtinType && $class ? $class : $builtinType] = true;

            if (Type::BUILTIN_TYPE_OBJECT === $builtinType) {
                if (!$this->serializer instanceof DenormalizerInterface) {
                    throw new LogicException(sprintf('Cannot denormalize attribute "%s" for class "%s" because injected serializer is not a denormalizer', $attribute, $class));
                }

                $childContext = $this->createChildContext($context, $attribute, $format);
                if ($this->serializer->supportsDenormalization($data, $class, $format, $childContext)) {
                    return $this->serializer->denormalize($data, $class, $format, $childContext);
                }
            }

            // JSON only has a Number type corresponding to both int and float PHP types.
            // PHP's json_encode, JavaScript's JSON.stringify, Go's json.Marshal as well as most other JSON encoders convert
            // floating-point numbers like 12.0 to 12 (the decimal part is dropped when possible).
            // PHP's json_decode automatically converts Numbers without a decimal part to integers.
            // To circumvent this behavior, integers are converted to floats when denormalizing JSON based formats and when
            // a float is expected.
            if (Type::BUILTIN_TYPE_FLOAT === $builtinType && \is_int($data) && false !== strpos($format, JsonEncoder::FORMAT)) {
                return (float) $data;
            }

            if (\call_user_func('is_'.$builtinType, $data)) {
                return $data;
            }
        }

        if (!empty($context[self::DISABLE_TYPE_ENFORCEMENT])) {
            return $data;
        }

        throw new NotNormalizableValueException(sprintf('The type of the "%s" attribute for class "%s" must be one of "%s" ("%s" given).', $attribute, $currentClass, implode('", "', array_keys($expectedTypes)), \gettype($data)));
    }

    /**
     * @internal
     */
    protected function denormalizeParameter(\ReflectionClass $class, \ReflectionParameter $parameter, $parameterName, $parameterData, array $context, $format = null)
    {
        if (null === $this->propertyTypeExtractor || null === $types = $this->propertyTypeExtractor->getTypes($class->getName(), $parameterName)) {
            return parent::denormalizeParameter($class, $parameter, $parameterName, $parameterData, $context, $format);
        }

        return $this->validateAndDenormalize($class->getName(), $parameterName, $parameterData, $format, $context);
    }

    /**
     * Sets an attribute and apply the name converter if necessary.
     *
     * @param string $attribute
     * @param mixed  $attributeValue
     *
     * @return array
     */
    private function updateData(array $data, $attribute, $attributeValue)
    {
        if ($this->nameConverter) {
            $attribute = $this->nameConverter->normalize($attribute);
        }

        $data[$attribute] = $attributeValue;

        return $data;
    }

    /**
     * Is the max depth reached for the given attribute?
     *
     * @param AttributeMetadataInterface[] $attributesMetadata
     * @param string                       $class
     * @param string                       $attribute
     * @param array                        $context
     *
     * @return bool
     */
    private function isMaxDepthReached(array $attributesMetadata, $class, $attribute, array &$context)
    {
        if (
            !isset($context[static::ENABLE_MAX_DEPTH]) ||
            !$context[static::ENABLE_MAX_DEPTH] ||
            !isset($attributesMetadata[$attribute]) ||
            null === $maxDepth = $attributesMetadata[$attribute]->getMaxDepth()
        ) {
            return false;
        }

        $key = sprintf(static::DEPTH_KEY_PATTERN, $class, $attribute);
        if (!isset($context[$key])) {
            $context[$key] = 1;

            return false;
        }

        if ($context[$key] === $maxDepth) {
            return true;
        }

        ++$context[$key];

        return false;
    }

    /**
     * Overwritten to update the cache key for the child.
     *
     * We must not mix up the attribute cache between parent and children.
     *
     * {@inheritdoc}
     */
    protected function createChildContext(array $parentContext, $attribute/*, string $format = null */)
    {
        if (\func_num_args() >= 3) {
            $format = \func_get_arg(2);
        } else {
            // will be deprecated in version 4
            $format = null;
        }

        $context = parent::createChildContext($parentContext, $attribute, $format);
        // format is already included in the cache_key of the parent.
        $context['cache_key'] = $this->getCacheKey($format, $context);

        return $context;
    }

    /**
     * Builds the cache key for the attributes cache.
     *
     * The key must be different for every option in the context that could change which attributes should be handled.
     *
     * @param string|null $format
     * @param array       $context
     *
     * @return bool|string
     */
    private function getCacheKey($format, array $context)
    {
        unset($context['cache_key']); // avoid artificially different keys
        try {
            return md5($format.serialize([
                'context' => $context,
                'ignored' => $this->ignoredAttributes,
                'camelized' => $this->camelizedAttributes,
            ]));
        } catch (\Exception $exception) {
            // The context cannot be serialized, skip the cache
            return false;
        }
    }
}
