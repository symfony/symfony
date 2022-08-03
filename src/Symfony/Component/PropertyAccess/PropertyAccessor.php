<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;
use Symfony\Component\PropertyInfo\PropertyWriteInfoExtractorInterface;

/**
 * Default implementation of {@link PropertyAccessorInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class PropertyAccessor implements PropertyAccessorInterface
{
    /** @var int Allow none of the magic methods */
    public const DISALLOW_MAGIC_METHODS = ReflectionExtractor::DISALLOW_MAGIC_METHODS;
    /** @var int Allow magic __get methods */
    public const MAGIC_GET = ReflectionExtractor::ALLOW_MAGIC_GET;
    /** @var int Allow magic __set methods */
    public const MAGIC_SET = ReflectionExtractor::ALLOW_MAGIC_SET;
    /** @var int Allow magic __call methods */
    public const MAGIC_CALL = ReflectionExtractor::ALLOW_MAGIC_CALL;

    public const DO_NOT_THROW = 0;
    public const THROW_ON_INVALID_INDEX = 1;
    public const THROW_ON_INVALID_PROPERTY_PATH = 2;

    private const VALUE = 0;
    private const REF = 1;
    private const IS_REF_CHAINED = 2;
    private const CACHE_PREFIX_READ = 'r';
    private const CACHE_PREFIX_WRITE = 'w';
    private const CACHE_PREFIX_PROPERTY_PATH = 'p';

    private $magicMethodsFlags;
    private $ignoreInvalidIndices;
    private $ignoreInvalidProperty;

    /**
     * @var CacheItemPoolInterface
     */
    private $cacheItemPool;

    private $propertyPathCache = [];

    /**
     * @var PropertyReadInfoExtractorInterface
     */
    private $readInfoExtractor;

    /**
     * @var PropertyWriteInfoExtractorInterface
     */
    private $writeInfoExtractor;
    private $readPropertyCache = [];
    private $writePropertyCache = [];
    private const RESULT_PROTO = [self::VALUE => null];

    /**
     * Should not be used by application code. Use
     * {@link PropertyAccess::createPropertyAccessor()} instead.
     *
     * @param int $magicMethods A bitwise combination of the MAGIC_* constants
     *                          to specify the allowed magic methods (__get, __set, __call)
     *                          or self::DISALLOW_MAGIC_METHODS for none
     * @param int $throw        A bitwise combination of the THROW_* constants
     *                          to specify when exceptions should be thrown
     */
    public function __construct(int $magicMethods = self::MAGIC_GET | self::MAGIC_SET, int $throw = self::THROW_ON_INVALID_PROPERTY_PATH, CacheItemPoolInterface $cacheItemPool = null, PropertyReadInfoExtractorInterface $readInfoExtractor = null, PropertyWriteInfoExtractorInterface $writeInfoExtractor = null)
    {
        $this->magicMethodsFlags = $magicMethods;
        $this->ignoreInvalidIndices = 0 === ($throw & self::THROW_ON_INVALID_INDEX);
        $this->cacheItemPool = $cacheItemPool instanceof NullAdapter ? null : $cacheItemPool; // Replace the NullAdapter by the null value
        $this->ignoreInvalidProperty = 0 === ($throw & self::THROW_ON_INVALID_PROPERTY_PATH);
        $this->readInfoExtractor = $readInfoExtractor ?? new ReflectionExtractor([], null, null, false);
        $this->writeInfoExtractor = $writeInfoExtractor ?? new ReflectionExtractor(['set'], null, null, false);
    }

    /**
     * {@inheritdoc}
     */
    public function getValue(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): mixed
    {
        $zval = [
            self::VALUE => $objectOrArray,
        ];

        if (\is_object($objectOrArray) && false === strpbrk((string) $propertyPath, '.[')) {
            return $this->readProperty($zval, $propertyPath, $this->ignoreInvalidProperty)[self::VALUE];
        }

        $propertyPath = $this->getPropertyPath($propertyPath);

        $propertyValues = $this->readPropertiesUntil($zval, $propertyPath, $propertyPath->getLength(), $this->ignoreInvalidIndices);

        return $propertyValues[\count($propertyValues) - 1][self::VALUE];
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(object|array &$objectOrArray, string|PropertyPathInterface $propertyPath, mixed $value)
    {
        if (\is_object($objectOrArray) && false === strpbrk((string) $propertyPath, '.[')) {
            $zval = [
                self::VALUE => $objectOrArray,
            ];

            try {
                $this->writeProperty($zval, $propertyPath, $value);

                return;
            } catch (\TypeError $e) {
                self::throwInvalidArgumentException($e->getMessage(), $e->getTrace(), 0, $propertyPath, $e);
                // It wasn't thrown in this class so rethrow it
                throw $e;
            }
        }

        $propertyPath = $this->getPropertyPath($propertyPath);

        $zval = [
            self::VALUE => $objectOrArray,
            self::REF => &$objectOrArray,
        ];
        $propertyValues = $this->readPropertiesUntil($zval, $propertyPath, $propertyPath->getLength() - 1);
        $overwrite = true;

        try {
            for ($i = \count($propertyValues) - 1; 0 <= $i; --$i) {
                $zval = $propertyValues[$i];
                unset($propertyValues[$i]);

                // You only need set value for current element if:
                // 1. it's the parent of the last index element
                // OR
                // 2. its child is not passed by reference
                //
                // This may avoid uncessary value setting process for array elements.
                // For example:
                // '[a][b][c]' => 'old-value'
                // If you want to change its value to 'new-value',
                // you only need set value for '[a][b][c]' and it's safe to ignore '[a][b]' and '[a]'
                if ($overwrite) {
                    $property = $propertyPath->getElement($i);

                    if ($propertyPath->isIndex($i)) {
                        if ($overwrite = !isset($zval[self::REF])) {
                            $ref = &$zval[self::REF];
                            $ref = $zval[self::VALUE];
                        }
                        $this->writeIndex($zval, $property, $value);
                        if ($overwrite) {
                            $zval[self::VALUE] = $zval[self::REF];
                        }
                    } else {
                        $this->writeProperty($zval, $property, $value);
                    }

                    // if current element is an object
                    // OR
                    // if current element's reference chain is not broken - current element
                    // as well as all its ancients in the property path are all passed by reference,
                    // then there is no need to continue the value setting process
                    if (\is_object($zval[self::VALUE]) || isset($zval[self::IS_REF_CHAINED])) {
                        break;
                    }
                }

                $value = $zval[self::VALUE];
            }
        } catch (\TypeError $e) {
            self::throwInvalidArgumentException($e->getMessage(), $e->getTrace(), 0, $propertyPath, $e);

            // It wasn't thrown in this class so rethrow it
            throw $e;
        }
    }

    private static function throwInvalidArgumentException(string $message, array $trace, int $i, string $propertyPath, \Throwable $previous = null): void
    {
        if (!isset($trace[$i]['file']) || __FILE__ !== $trace[$i]['file']) {
            return;
        }
        if (preg_match('/^\S+::\S+\(\): Argument #\d+ \(\$\S+\) must be of type (\S+), (\S+) given/', $message, $matches)) {
            [, $expectedType, $actualType] = $matches;

            throw new InvalidArgumentException(sprintf('Expected argument of type "%s", "%s" given at property path "%s".', $expectedType, 'NULL' === $actualType ? 'null' : $actualType, $propertyPath), 0, $previous);
        }
        if (preg_match('/^Cannot assign (\S+) to property \S+::\$\S+ of type (\S+)$/', $message, $matches)) {
            [, $actualType, $expectedType] = $matches;

            throw new InvalidArgumentException(sprintf('Expected argument of type "%s", "%s" given at property path "%s".', $expectedType, 'NULL' === $actualType ? 'null' : $actualType, $propertyPath), 0, $previous);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        if (!$propertyPath instanceof PropertyPathInterface) {
            $propertyPath = new PropertyPath($propertyPath);
        }

        try {
            $zval = [
                self::VALUE => $objectOrArray,
            ];
            $this->readPropertiesUntil($zval, $propertyPath, $propertyPath->getLength(), $this->ignoreInvalidIndices);

            return true;
        } catch (AccessException) {
            return false;
        } catch (UnexpectedTypeException) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        $propertyPath = $this->getPropertyPath($propertyPath);

        try {
            $zval = [
                self::VALUE => $objectOrArray,
            ];
            $propertyValues = $this->readPropertiesUntil($zval, $propertyPath, $propertyPath->getLength() - 1);

            for ($i = \count($propertyValues) - 1; 0 <= $i; --$i) {
                $zval = $propertyValues[$i];
                unset($propertyValues[$i]);

                if ($propertyPath->isIndex($i)) {
                    if (!$zval[self::VALUE] instanceof \ArrayAccess && !\is_array($zval[self::VALUE])) {
                        return false;
                    }
                } elseif (!\is_object($zval[self::VALUE]) || !$this->isPropertyWritable($zval[self::VALUE], $propertyPath->getElement($i))) {
                    return false;
                }

                if (\is_object($zval[self::VALUE])) {
                    return true;
                }
            }

            return true;
        } catch (AccessException) {
            return false;
        } catch (UnexpectedTypeException) {
            return false;
        }
    }

    /**
     * Reads the path from an object up to a given path index.
     *
     * @throws UnexpectedTypeException if a value within the path is neither object nor array
     * @throws NoSuchIndexException    If a non-existing index is accessed
     */
    private function readPropertiesUntil(array $zval, PropertyPathInterface $propertyPath, int $lastIndex, bool $ignoreInvalidIndices = true): array
    {
        if (!\is_object($zval[self::VALUE]) && !\is_array($zval[self::VALUE])) {
            throw new UnexpectedTypeException($zval[self::VALUE], $propertyPath, 0);
        }

        // Add the root object to the list
        $propertyValues = [$zval];

        for ($i = 0; $i < $lastIndex; ++$i) {
            $property = $propertyPath->getElement($i);
            $isIndex = $propertyPath->isIndex($i);

            if ($isIndex) {
                // Create missing nested arrays on demand
                if (($zval[self::VALUE] instanceof \ArrayAccess && !$zval[self::VALUE]->offsetExists($property)) ||
                    (\is_array($zval[self::VALUE]) && !isset($zval[self::VALUE][$property]) && !\array_key_exists($property, $zval[self::VALUE]))
                ) {
                    if (!$ignoreInvalidIndices) {
                        if (!\is_array($zval[self::VALUE])) {
                            if (!$zval[self::VALUE] instanceof \Traversable) {
                                throw new NoSuchIndexException(sprintf('Cannot read index "%s" while trying to traverse path "%s".', $property, (string) $propertyPath));
                            }

                            $zval[self::VALUE] = iterator_to_array($zval[self::VALUE]);
                        }

                        throw new NoSuchIndexException(sprintf('Cannot read index "%s" while trying to traverse path "%s". Available indices are "%s".', $property, (string) $propertyPath, print_r(array_keys($zval[self::VALUE]), true)));
                    }

                    if ($i + 1 < $propertyPath->getLength()) {
                        if (isset($zval[self::REF])) {
                            $zval[self::VALUE][$property] = [];
                            $zval[self::REF] = $zval[self::VALUE];
                        } else {
                            $zval[self::VALUE] = [$property => []];
                        }
                    }
                }

                $zval = $this->readIndex($zval, $property);
            } else {
                $zval = $this->readProperty($zval, $property, $this->ignoreInvalidProperty);
            }

            // the final value of the path must not be validated
            if ($i + 1 < $propertyPath->getLength() && !\is_object($zval[self::VALUE]) && !\is_array($zval[self::VALUE])) {
                throw new UnexpectedTypeException($zval[self::VALUE], $propertyPath, $i + 1);
            }

            if (isset($zval[self::REF]) && (0 === $i || isset($propertyValues[$i - 1][self::IS_REF_CHAINED]))) {
                // Set the IS_REF_CHAINED flag to true if:
                // current property is passed by reference and
                // it is the first element in the property path or
                // the IS_REF_CHAINED flag of its parent element is true
                // Basically, this flag is true only when the reference chain from the top element to current element is not broken
                $zval[self::IS_REF_CHAINED] = true;
            }

            $propertyValues[] = $zval;
        }

        return $propertyValues;
    }

    /**
     * Reads a key from an array-like structure.
     *
     * @throws NoSuchIndexException If the array does not implement \ArrayAccess or it is not an array
     */
    private function readIndex(array $zval, string|int $index): array
    {
        if (!$zval[self::VALUE] instanceof \ArrayAccess && !\is_array($zval[self::VALUE])) {
            throw new NoSuchIndexException(sprintf('Cannot read index "%s" from object of type "%s" because it doesn\'t implement \ArrayAccess.', $index, get_debug_type($zval[self::VALUE])));
        }

        $result = self::RESULT_PROTO;

        if (isset($zval[self::VALUE][$index])) {
            $result[self::VALUE] = $zval[self::VALUE][$index];

            if (!isset($zval[self::REF])) {
                // Save creating references when doing read-only lookups
            } elseif (\is_array($zval[self::VALUE])) {
                $result[self::REF] = &$zval[self::REF][$index];
            } elseif (\is_object($result[self::VALUE])) {
                $result[self::REF] = $result[self::VALUE];
            }
        }

        return $result;
    }

    /**
     * Reads the value of a property from an object.
     *
     * @throws NoSuchPropertyException If $ignoreInvalidProperty is false and the property does not exist or is not public
     */
    private function readProperty(array $zval, string $property, bool $ignoreInvalidProperty = false): array
    {
        if (!\is_object($zval[self::VALUE])) {
            throw new NoSuchPropertyException(sprintf('Cannot read property "%s" from an array. Maybe you intended to write the property path as "[%1$s]" instead.', $property));
        }

        $result = self::RESULT_PROTO;
        $object = $zval[self::VALUE];
        $class = \get_class($object);
        $access = $this->getReadInfo($class, $property);

        if (null !== $access) {
            $name = $access->getName();
            $type = $access->getType();

            try {
                if (PropertyReadInfo::TYPE_METHOD === $type) {
                    try {
                        $result[self::VALUE] = $object->$name();
                    } catch (\TypeError $e) {
                        [$trace] = $e->getTrace();

                        // handle uninitialized properties in PHP >= 7
                        if (__FILE__ === $trace['file']
                            && $name === $trace['function']
                            && $object instanceof $trace['class']
                            && preg_match('/Return value (?:of .*::\w+\(\) )?must be of (?:the )?type (\w+), null returned$/', $e->getMessage(), $matches)
                        ) {
                            throw new UninitializedPropertyException(sprintf('The method "%s::%s()" returned "null", but expected type "%3$s". Did you forget to initialize a property or to make the return type nullable using "?%3$s"?', get_debug_type($object), $name, $matches[1]), 0, $e);
                        }

                        throw $e;
                    }
                } elseif (PropertyReadInfo::TYPE_PROPERTY === $type) {
                    if ($access->canBeReference() && !isset($object->$name) && !\array_key_exists($name, (array) $object) && !(new \ReflectionProperty($class, $name))->hasType()) {
                        throw new UninitializedPropertyException(sprintf('The property "%s::$%s" is not initialized.', $class, $name));
                    }

                    $result[self::VALUE] = $object->$name;

                    if (isset($zval[self::REF]) && $access->canBeReference()) {
                        $result[self::REF] = &$object->$name;
                    }
                }
            } catch (\Error $e) {
                // handle uninitialized properties in PHP >= 7.4
                if (preg_match('/^Typed property ([\w\\\\@]+)::\$(\w+) must not be accessed before initialization$/', $e->getMessage(), $matches)) {
                    $r = new \ReflectionProperty(str_contains($matches[1], '@anonymous') ? $class : $matches[1], $matches[2]);
                    $type = ($type = $r->getType()) instanceof \ReflectionNamedType ? $type->getName() : (string) $type;

                    throw new UninitializedPropertyException(sprintf('The property "%s::$%s" is not readable because it is typed "%s". You should initialize it or declare a default value instead.', $matches[1], $r->getName(), $type), 0, $e);
                }

                throw $e;
            }
        } elseif (property_exists($object, $property) && \array_key_exists($property, (array) $object)) {
            $result[self::VALUE] = $object->$property;
            if (isset($zval[self::REF])) {
                $result[self::REF] = &$object->$property;
            }
        } elseif (!$ignoreInvalidProperty) {
            throw new NoSuchPropertyException(sprintf('Can\'t get a way to read the property "%s" in class "%s".', $property, $class));
        }

        // Objects are always passed around by reference
        if (isset($zval[self::REF]) && \is_object($result[self::VALUE])) {
            $result[self::REF] = $result[self::VALUE];
        }

        return $result;
    }

    /**
     * Guesses how to read the property value.
     */
    private function getReadInfo(string $class, string $property): ?PropertyReadInfo
    {
        $key = str_replace('\\', '.', $class).'..'.$property;

        if (isset($this->readPropertyCache[$key])) {
            return $this->readPropertyCache[$key];
        }

        if ($this->cacheItemPool) {
            $item = $this->cacheItemPool->getItem(self::CACHE_PREFIX_READ.rawurlencode($key));
            if ($item->isHit()) {
                return $this->readPropertyCache[$key] = $item->get();
            }
        }

        $accessor = $this->readInfoExtractor->getReadInfo($class, $property, [
            'enable_getter_setter_extraction' => true,
            'enable_magic_methods_extraction' => $this->magicMethodsFlags,
            'enable_constructor_extraction' => false,
        ]);

        if (isset($item)) {
            $this->cacheItemPool->save($item->set($accessor));
        }

        return $this->readPropertyCache[$key] = $accessor;
    }

    /**
     * Sets the value of an index in a given array-accessible value.
     *
     * @throws NoSuchIndexException If the array does not implement \ArrayAccess or it is not an array
     */
    private function writeIndex(array $zval, string|int $index, mixed $value)
    {
        if (!$zval[self::VALUE] instanceof \ArrayAccess && !\is_array($zval[self::VALUE])) {
            throw new NoSuchIndexException(sprintf('Cannot modify index "%s" in object of type "%s" because it doesn\'t implement \ArrayAccess.', $index, get_debug_type($zval[self::VALUE])));
        }

        $zval[self::REF][$index] = $value;
    }

    /**
     * Sets the value of a property in the given object.
     *
     * @throws NoSuchPropertyException if the property does not exist or is not public
     */
    private function writeProperty(array $zval, string $property, mixed $value)
    {
        if (!\is_object($zval[self::VALUE])) {
            throw new NoSuchPropertyException(sprintf('Cannot write property "%s" to an array. Maybe you should write the property path as "[%1$s]" instead?', $property));
        }

        $object = $zval[self::VALUE];
        $class = \get_class($object);
        $mutator = $this->getWriteInfo($class, $property, $value);

        if (PropertyWriteInfo::TYPE_NONE !== $mutator->getType()) {
            $type = $mutator->getType();

            if (PropertyWriteInfo::TYPE_METHOD === $type) {
                $object->{$mutator->getName()}($value);
            } elseif (PropertyWriteInfo::TYPE_PROPERTY === $type) {
                $object->{$mutator->getName()} = $value;
            } elseif (PropertyWriteInfo::TYPE_ADDER_AND_REMOVER === $type) {
                $this->writeCollection($zval, $property, $value, $mutator->getAdderInfo(), $mutator->getRemoverInfo());
            }
        } elseif ($object instanceof \stdClass && property_exists($object, $property)) {
            $object->$property = $value;
        } elseif (!$this->ignoreInvalidProperty) {
            if ($mutator->hasErrors()) {
                throw new NoSuchPropertyException(implode('. ', $mutator->getErrors()).'.');
            }

            throw new NoSuchPropertyException(sprintf('Could not determine access type for property "%s" in class "%s".', $property, get_debug_type($object)));
        }
    }

    /**
     * Adjusts a collection-valued property by calling add*() and remove*() methods.
     */
    private function writeCollection(array $zval, string $property, iterable $collection, PropertyWriteInfo $addMethod, PropertyWriteInfo $removeMethod)
    {
        // At this point the add and remove methods have been found
        $previousValue = $this->readProperty($zval, $property);
        $previousValue = $previousValue[self::VALUE];

        $removeMethodName = $removeMethod->getName();
        $addMethodName = $addMethod->getName();

        if ($previousValue instanceof \Traversable) {
            $previousValue = iterator_to_array($previousValue);
        }
        if ($previousValue && \is_array($previousValue)) {
            if (\is_object($collection)) {
                $collection = iterator_to_array($collection);
            }
            foreach ($previousValue as $key => $item) {
                if (!\in_array($item, $collection, true)) {
                    unset($previousValue[$key]);
                    $zval[self::VALUE]->$removeMethodName($item);
                }
            }
        } else {
            $previousValue = false;
        }

        foreach ($collection as $item) {
            if (!$previousValue || !\in_array($item, $previousValue, true)) {
                $zval[self::VALUE]->$addMethodName($item);
            }
        }
    }

    private function getWriteInfo(string $class, string $property, mixed $value): PropertyWriteInfo
    {
        $useAdderAndRemover = is_iterable($value);
        $key = str_replace('\\', '.', $class).'..'.$property.'..'.(int) $useAdderAndRemover;

        if (isset($this->writePropertyCache[$key])) {
            return $this->writePropertyCache[$key];
        }

        if ($this->cacheItemPool) {
            $item = $this->cacheItemPool->getItem(self::CACHE_PREFIX_WRITE.rawurlencode($key));
            if ($item->isHit()) {
                return $this->writePropertyCache[$key] = $item->get();
            }
        }

        $mutator = $this->writeInfoExtractor->getWriteInfo($class, $property, [
            'enable_getter_setter_extraction' => true,
            'enable_magic_methods_extraction' => $this->magicMethodsFlags,
            'enable_constructor_extraction' => false,
            'enable_adder_remover_extraction' => $useAdderAndRemover,
        ]);

        if (isset($item)) {
            $this->cacheItemPool->save($item->set($mutator));
        }

        return $this->writePropertyCache[$key] = $mutator;
    }

    /**
     * Returns whether a property is writable in the given object.
     */
    private function isPropertyWritable(object $object, string $property): bool
    {
        $mutatorForArray = $this->getWriteInfo(\get_class($object), $property, []);

        if (PropertyWriteInfo::TYPE_NONE !== $mutatorForArray->getType() || ($object instanceof \stdClass && property_exists($object, $property))) {
            return true;
        }

        $mutator = $this->getWriteInfo(\get_class($object), $property, '');

        return PropertyWriteInfo::TYPE_NONE !== $mutator->getType() || ($object instanceof \stdClass && property_exists($object, $property));
    }

    /**
     * Gets a PropertyPath instance and caches it.
     */
    private function getPropertyPath(string|PropertyPath $propertyPath): PropertyPath
    {
        if ($propertyPath instanceof PropertyPathInterface) {
            // Don't call the copy constructor has it is not needed here
            return $propertyPath;
        }

        if (isset($this->propertyPathCache[$propertyPath])) {
            return $this->propertyPathCache[$propertyPath];
        }

        if ($this->cacheItemPool) {
            $item = $this->cacheItemPool->getItem(self::CACHE_PREFIX_PROPERTY_PATH.rawurlencode($propertyPath));
            if ($item->isHit()) {
                return $this->propertyPathCache[$propertyPath] = $item->get();
            }
        }

        $propertyPathInstance = new PropertyPath($propertyPath);
        if (isset($item)) {
            $item->set($propertyPathInstance);
            $this->cacheItemPool->save($item);
        }

        return $this->propertyPathCache[$propertyPath] = $propertyPathInstance;
    }

    /**
     * Creates the APCu adapter if applicable.
     *
     * @throws \LogicException When the Cache Component isn't available
     */
    public static function createCache(string $namespace, int $defaultLifetime, string $version, LoggerInterface $logger = null): AdapterInterface
    {
        if (!class_exists(ApcuAdapter::class)) {
            throw new \LogicException(sprintf('The Symfony Cache component must be installed to use "%s()".', __METHOD__));
        }

        if (!ApcuAdapter::isSupported()) {
            return new NullAdapter();
        }

        $apcu = new ApcuAdapter($namespace, $defaultLifetime / 5, $version);
        if ('cli' === \PHP_SAPI && !filter_var(\ini_get('apc.enable_cli'), \FILTER_VALIDATE_BOOLEAN)) {
            $apcu->setLogger(new NullLogger());
        } elseif (null !== $logger) {
            $apcu->setLogger($logger);
        }

        return $apcu;
    }
}
