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
use Symfony\Component\Inflector\Inflector;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;

/**
 * Default implementation of {@link PropertyAccessorInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class PropertyAccessor implements PropertyAccessorInterface
{
    private const VALUE = 0;
    private const REF = 1;
    private const IS_REF_CHAINED = 2;
    private const ACCESS_HAS_PROPERTY = 0;
    private const ACCESS_TYPE = 1;
    private const ACCESS_NAME = 2;
    private const ACCESS_REF = 3;
    private const ACCESS_ADDER = 4;
    private const ACCESS_REMOVER = 5;
    private const ACCESS_TYPE_METHOD = 0;
    private const ACCESS_TYPE_PROPERTY = 1;
    private const ACCESS_TYPE_MAGIC = 2;
    private const ACCESS_TYPE_ADDER_AND_REMOVER = 3;
    private const ACCESS_TYPE_NOT_FOUND = 4;
    private const CACHE_PREFIX_READ = 'r';
    private const CACHE_PREFIX_WRITE = 'w';
    private const CACHE_PREFIX_PROPERTY_PATH = 'p';

    /**
     * @var bool
     */
    private $magicCall;
    private $ignoreInvalidIndices;
    private $ignoreInvalidProperty;

    /**
     * @var CacheItemPoolInterface
     */
    private $cacheItemPool;

    private $propertyPathCache = [];
    private $readPropertyCache = [];
    private $writePropertyCache = [];
    private static $resultProto = [self::VALUE => null];

    /**
     * Should not be used by application code. Use
     * {@link PropertyAccess::createPropertyAccessor()} instead.
     */
    public function __construct(bool $magicCall = false, bool $throwExceptionOnInvalidIndex = false, CacheItemPoolInterface $cacheItemPool = null, bool $throwExceptionOnInvalidPropertyPath = true)
    {
        $this->magicCall = $magicCall;
        $this->ignoreInvalidIndices = !$throwExceptionOnInvalidIndex;
        $this->cacheItemPool = $cacheItemPool instanceof NullAdapter ? null : $cacheItemPool; // Replace the NullAdapter by the null value
        $this->ignoreInvalidProperty = !$throwExceptionOnInvalidPropertyPath;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($objectOrArray, $propertyPath)
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
    public function setValue(&$objectOrArray, $propertyPath, $value)
    {
        if (\is_object($objectOrArray) && false === strpbrk((string) $propertyPath, '.[')) {
            $zval = [
                self::VALUE => $objectOrArray,
            ];

            try {
                $this->writeProperty($zval, $propertyPath, $value);

                return;
            } catch (\TypeError $e) {
                self::throwInvalidArgumentException($e->getMessage(), $e->getTrace(), 0, $propertyPath);
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
        // the type mismatch is not caused by invalid arguments (but e.g. by an incompatible return type hint of the writer method)
        if (0 !== strpos($message, 'Argument ')) {
            return;
        }

        if (isset($trace[$i]['file']) && __FILE__ === $trace[$i]['file']) {
            $pos = strpos($message, $delim = 'must be of the type ') ?: (strpos($message, $delim = 'must be an instance of ') ?: strpos($message, $delim = 'must implement interface '));
            $pos += \strlen($delim);
            $j = strpos($message, ',', $pos);
            $type = substr($message, 2 + $j, strpos($message, ' given', $j) - $j - 2);
            $message = substr($message, $pos, $j - $pos);

            throw new InvalidArgumentException(sprintf('Expected argument of type "%s", "%s" given at property path "%s".', $message, 'NULL' === $type ? 'null' : $type, $propertyPath), 0, $previous);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($objectOrArray, $propertyPath)
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
        } catch (AccessException $e) {
            return false;
        } catch (UnexpectedTypeException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($objectOrArray, $propertyPath)
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
                } else {
                    if (!$this->isPropertyWritable($zval[self::VALUE], $propertyPath->getElement($i))) {
                        return false;
                    }
                }

                if (\is_object($zval[self::VALUE])) {
                    return true;
                }
            }

            return true;
        } catch (AccessException $e) {
            return false;
        } catch (UnexpectedTypeException $e) {
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
     * @param string|int $index The key to read
     *
     * @throws NoSuchIndexException If the array does not implement \ArrayAccess or it is not an array
     */
    private function readIndex(array $zval, $index): array
    {
        if (!$zval[self::VALUE] instanceof \ArrayAccess && !\is_array($zval[self::VALUE])) {
            throw new NoSuchIndexException(sprintf('Cannot read index "%s" from object of type "%s" because it doesn\'t implement \ArrayAccess.', $index, \get_class($zval[self::VALUE])));
        }

        $result = self::$resultProto;

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
     * Reads the a property from an object.
     *
     * @throws NoSuchPropertyException If $ignoreInvalidProperty is false and the property does not exist or is not public
     */
    private function readProperty(array $zval, string $property, bool $ignoreInvalidProperty = false): array
    {
        if (!\is_object($zval[self::VALUE])) {
            throw new NoSuchPropertyException(sprintf('Cannot read property "%s" from an array. Maybe you intended to write the property path as "[%1$s]" instead.', $property));
        }

        $result = self::$resultProto;
        $object = $zval[self::VALUE];
        $access = $this->getReadAccessInfo(\get_class($object), $property);

        try {
            if (self::ACCESS_TYPE_METHOD === $access[self::ACCESS_TYPE]) {
                try {
                    $result[self::VALUE] = $object->{$access[self::ACCESS_NAME]}();
                } catch (\TypeError $e) {
                    // handle uninitialized properties in PHP >= 7
                    if (preg_match((sprintf('/^Return value of %s::%s\(\) must be of the type (\w+), null returned$/', preg_quote(\get_class($object)), $access[self::ACCESS_NAME])), $e->getMessage(), $matches)) {
                        throw new AccessException(sprintf('The method "%s::%s()" returned "null", but expected type "%3$s". Have you forgotten to initialize a property or to make the return type nullable using "?%3$s" instead?', \get_class($object), $access[self::ACCESS_NAME], $matches[1]), 0, $e);
                    }

                    throw $e;
                }
            } elseif (self::ACCESS_TYPE_PROPERTY === $access[self::ACCESS_TYPE]) {
                $result[self::VALUE] = $object->{$access[self::ACCESS_NAME]};

                if ($access[self::ACCESS_REF] && isset($zval[self::REF])) {
                    $result[self::REF] = &$object->{$access[self::ACCESS_NAME]};
                }
            } elseif (!$access[self::ACCESS_HAS_PROPERTY] && property_exists($object, $property)) {
                // Needed to support \stdClass instances. We need to explicitly
                // exclude $access[self::ACCESS_HAS_PROPERTY], otherwise if
                // a *protected* property was found on the class, property_exists()
                // returns true, consequently the following line will result in a
                // fatal error.

                $result[self::VALUE] = $object->$property;
                if (isset($zval[self::REF])) {
                    $result[self::REF] = &$object->$property;
                }
            } elseif (self::ACCESS_TYPE_MAGIC === $access[self::ACCESS_TYPE]) {
                // we call the getter and hope the __call do the job
                $result[self::VALUE] = $object->{$access[self::ACCESS_NAME]}();
            } elseif (!$ignoreInvalidProperty) {
                throw new NoSuchPropertyException($access[self::ACCESS_NAME]);
            }
        } catch (\Error $e) {
            // handle uninitialized properties in PHP >= 7.4
            if (\PHP_VERSION_ID >= 70400 && preg_match('/^Typed property ([\w\\\]+)::\$(\w+) must not be accessed before initialization$/', $e->getMessage(), $matches)) {
                $r = new \ReflectionProperty($matches[1], $matches[2]);

                throw new AccessException(sprintf('The property "%s::$%s" is not readable because it is typed "%3$s". You should either initialize it or make it nullable using "?%3$s" instead.', $r->getDeclaringClass()->getName(), $r->getName(), $r->getType()->getName()), 0, $e);
            }

            throw $e;
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
    private function getReadAccessInfo(string $class, string $property): array
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

        $access = [];

        $reflClass = new \ReflectionClass($class);
        $access[self::ACCESS_HAS_PROPERTY] = $reflClass->hasProperty($property);
        $camelProp = $this->camelize($property);
        $getter = 'get'.$camelProp;
        $getsetter = lcfirst($camelProp); // jQuery style, e.g. read: last(), write: last($item)
        $isser = 'is'.$camelProp;
        $hasser = 'has'.$camelProp;
        $canAccessor = 'can'.$camelProp;

        if ($reflClass->hasMethod($getter) && $reflClass->getMethod($getter)->isPublic()) {
            $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_METHOD;
            $access[self::ACCESS_NAME] = $getter;
        } elseif ($reflClass->hasMethod($getsetter) && $reflClass->getMethod($getsetter)->isPublic()) {
            $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_METHOD;
            $access[self::ACCESS_NAME] = $getsetter;
        } elseif ($reflClass->hasMethod($isser) && $reflClass->getMethod($isser)->isPublic()) {
            $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_METHOD;
            $access[self::ACCESS_NAME] = $isser;
        } elseif ($reflClass->hasMethod($hasser) && $reflClass->getMethod($hasser)->isPublic()) {
            $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_METHOD;
            $access[self::ACCESS_NAME] = $hasser;
        } elseif ($reflClass->hasMethod($canAccessor) && $reflClass->getMethod($canAccessor)->isPublic()) {
            $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_METHOD;
            $access[self::ACCESS_NAME] = $canAccessor;
        } elseif ($reflClass->hasMethod('__get') && $reflClass->getMethod('__get')->isPublic()) {
            $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_PROPERTY;
            $access[self::ACCESS_NAME] = $property;
            $access[self::ACCESS_REF] = false;
        } elseif ($access[self::ACCESS_HAS_PROPERTY] && $reflClass->getProperty($property)->isPublic()) {
            $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_PROPERTY;
            $access[self::ACCESS_NAME] = $property;
            $access[self::ACCESS_REF] = true;
        } elseif ($this->magicCall && $reflClass->hasMethod('__call') && $reflClass->getMethod('__call')->isPublic()) {
            // we call the getter and hope the __call do the job
            $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_MAGIC;
            $access[self::ACCESS_NAME] = $getter;
        } else {
            $methods = [$getter, $getsetter, $isser, $hasser, '__get'];
            if ($this->magicCall) {
                $methods[] = '__call';
            }

            $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_NOT_FOUND;
            $access[self::ACCESS_NAME] = sprintf(
                'Neither the property "%s" nor one of the methods "%s()" '.
                'exist and have public access in class "%s".',
                $property,
                implode('()", "', $methods),
                $reflClass->name
            );
        }

        if (isset($item)) {
            $this->cacheItemPool->save($item->set($access));
        }

        return $this->readPropertyCache[$key] = $access;
    }

    /**
     * Sets the value of an index in a given array-accessible value.
     *
     * @param string|int $index The index to write at
     * @param mixed      $value The value to write
     *
     * @throws NoSuchIndexException If the array does not implement \ArrayAccess or it is not an array
     */
    private function writeIndex(array $zval, $index, $value)
    {
        if (!$zval[self::VALUE] instanceof \ArrayAccess && !\is_array($zval[self::VALUE])) {
            throw new NoSuchIndexException(sprintf('Cannot modify index "%s" in object of type "%s" because it doesn\'t implement \ArrayAccess.', $index, \get_class($zval[self::VALUE])));
        }

        $zval[self::REF][$index] = $value;
    }

    /**
     * Sets the value of a property in the given object.
     *
     * @param mixed $value The value to write
     *
     * @throws NoSuchPropertyException if the property does not exist or is not public
     */
    private function writeProperty(array $zval, string $property, $value)
    {
        if (!\is_object($zval[self::VALUE])) {
            throw new NoSuchPropertyException(sprintf('Cannot write property "%s" to an array. Maybe you should write the property path as "[%1$s]" instead?', $property));
        }

        $object = $zval[self::VALUE];
        $access = $this->getWriteAccessInfo(\get_class($object), $property, $value);

        if (self::ACCESS_TYPE_METHOD === $access[self::ACCESS_TYPE]) {
            $object->{$access[self::ACCESS_NAME]}($value);
        } elseif (self::ACCESS_TYPE_PROPERTY === $access[self::ACCESS_TYPE]) {
            $object->{$access[self::ACCESS_NAME]} = $value;
        } elseif (self::ACCESS_TYPE_ADDER_AND_REMOVER === $access[self::ACCESS_TYPE]) {
            $this->writeCollection($zval, $property, $value, $access[self::ACCESS_ADDER], $access[self::ACCESS_REMOVER]);
        } elseif (!$access[self::ACCESS_HAS_PROPERTY] && property_exists($object, $property)) {
            // Needed to support \stdClass instances. We need to explicitly
            // exclude $access[self::ACCESS_HAS_PROPERTY], otherwise if
            // a *protected* property was found on the class, property_exists()
            // returns true, consequently the following line will result in a
            // fatal error.

            $object->$property = $value;
        } elseif (self::ACCESS_TYPE_MAGIC === $access[self::ACCESS_TYPE]) {
            $object->{$access[self::ACCESS_NAME]}($value);
        } elseif (self::ACCESS_TYPE_NOT_FOUND === $access[self::ACCESS_TYPE]) {
            throw new NoSuchPropertyException(sprintf('Could not determine access type for property "%s" in class "%s"%s.', $property, \get_class($object), isset($access[self::ACCESS_NAME]) ? ': '.$access[self::ACCESS_NAME] : '.'));
        } else {
            throw new NoSuchPropertyException($access[self::ACCESS_NAME]);
        }
    }

    /**
     * Adjusts a collection-valued property by calling add*() and remove*() methods.
     */
    private function writeCollection(array $zval, string $property, iterable $collection, string $addMethod, string $removeMethod)
    {
        // At this point the add and remove methods have been found
        $previousValue = $this->readProperty($zval, $property);
        $previousValue = $previousValue[self::VALUE];

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
                    $zval[self::VALUE]->{$removeMethod}($item);
                }
            }
        } else {
            $previousValue = false;
        }

        foreach ($collection as $item) {
            if (!$previousValue || !\in_array($item, $previousValue, true)) {
                $zval[self::VALUE]->{$addMethod}($item);
            }
        }
    }

    /**
     * Guesses how to write the property value.
     *
     * @param mixed $value
     */
    private function getWriteAccessInfo(string $class, string $property, $value): array
    {
        $useAdderAndRemover = \is_array($value) || $value instanceof \Traversable;
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

        $access = [];

        $reflClass = new \ReflectionClass($class);
        $access[self::ACCESS_HAS_PROPERTY] = $reflClass->hasProperty($property);
        $camelized = $this->camelize($property);
        $singulars = (array) Inflector::singularize($camelized);
        $errors = [];

        if ($useAdderAndRemover) {
            foreach ($this->findAdderAndRemover($reflClass, $singulars) as $methods) {
                if (3 === \count($methods)) {
                    $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_ADDER_AND_REMOVER;
                    $access[self::ACCESS_ADDER] = $methods[self::ACCESS_ADDER];
                    $access[self::ACCESS_REMOVER] = $methods[self::ACCESS_REMOVER];
                    break;
                }

                if (isset($methods[self::ACCESS_ADDER])) {
                    $errors[] = sprintf('The add method "%s" in class "%s" was found, but the corresponding remove method "%s" was not found', $methods['methods'][self::ACCESS_ADDER], $reflClass->name, $methods['methods'][self::ACCESS_REMOVER]);
                }

                if (isset($methods[self::ACCESS_REMOVER])) {
                    $errors[] = sprintf('The remove method "%s" in class "%s" was found, but the corresponding add method "%s" was not found', $methods['methods'][self::ACCESS_REMOVER], $reflClass->name, $methods['methods'][self::ACCESS_ADDER]);
                }
            }
        }

        if (!isset($access[self::ACCESS_TYPE])) {
            $setter = 'set'.$camelized;
            $getsetter = lcfirst($camelized); // jQuery style, e.g. read: last(), write: last($item)

            if ($this->isMethodAccessible($reflClass, $setter, 1)) {
                $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_METHOD;
                $access[self::ACCESS_NAME] = $setter;
            } elseif ($this->isMethodAccessible($reflClass, $getsetter, 1)) {
                $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_METHOD;
                $access[self::ACCESS_NAME] = $getsetter;
            } elseif ($this->isMethodAccessible($reflClass, '__set', 2)) {
                $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_PROPERTY;
                $access[self::ACCESS_NAME] = $property;
            } elseif ($access[self::ACCESS_HAS_PROPERTY] && $reflClass->getProperty($property)->isPublic()) {
                $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_PROPERTY;
                $access[self::ACCESS_NAME] = $property;
            } elseif ($this->magicCall && $this->isMethodAccessible($reflClass, '__call', 2)) {
                // we call the getter and hope the __call do the job
                $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_MAGIC;
                $access[self::ACCESS_NAME] = $setter;
            } else {
                foreach ($this->findAdderAndRemover($reflClass, $singulars) as $methods) {
                    if (3 === \count($methods)) {
                        $errors[] = sprintf(
                            'The property "%s" in class "%s" can be defined with the methods "%s()" but '.
                            'the new value must be an array or an instance of \Traversable, '.
                            '"%s" given.',
                            $property,
                            $reflClass->name,
                            implode('()", "', [$methods[self::ACCESS_ADDER], $methods[self::ACCESS_REMOVER]]),
                            \is_object($value) ? \get_class($value) : \gettype($value)
                        );
                    }
                }

                if (!isset($access[self::ACCESS_NAME])) {
                    $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_NOT_FOUND;

                    $triedMethods = [
                        $setter => 1,
                        $getsetter => 1,
                        '__set' => 2,
                        '__call' => 2,
                    ];

                    foreach ($singulars as $singular) {
                        $triedMethods['add'.$singular] = 1;
                        $triedMethods['remove'.$singular] = 1;
                    }

                    foreach ($triedMethods as $methodName => $parameters) {
                        if (!$reflClass->hasMethod($methodName)) {
                            continue;
                        }

                        $method = $reflClass->getMethod($methodName);

                        if (!$method->isPublic()) {
                            $errors[] = sprintf('The method "%s" in class "%s" was found but does not have public access', $methodName, $reflClass->name);
                            continue;
                        }

                        if ($method->getNumberOfRequiredParameters() > $parameters || $method->getNumberOfParameters() < $parameters) {
                            $errors[] = sprintf('The method "%s" in class "%s" requires %d arguments, but should accept only %d', $methodName, $reflClass->name, $method->getNumberOfRequiredParameters(), $parameters);
                        }
                    }

                    if (\count($errors)) {
                        $access[self::ACCESS_NAME] = implode('. ', $errors).'.';
                    } else {
                        $access[self::ACCESS_NAME] = sprintf(
                            'Neither the property "%s" nor one of the methods %s"%s()", "%s()", '.
                            '"__set()" or "__call()" exist and have public access in class "%s".',
                            $property,
                            implode('', array_map(function ($singular) {
                                return '"add'.$singular.'()"/"remove'.$singular.'()", ';
                            }, $singulars)),
                            $setter,
                            $getsetter,
                            $reflClass->name
                        );
                    }
                }
            }
        }

        if (isset($item)) {
            $this->cacheItemPool->save($item->set($access));
        }

        return $this->writePropertyCache[$key] = $access;
    }

    /**
     * Returns whether a property is writable in the given object.
     *
     * @param object $object The object to write to
     */
    private function isPropertyWritable($object, string $property): bool
    {
        if (!\is_object($object)) {
            return false;
        }

        $access = $this->getWriteAccessInfo(\get_class($object), $property, []);

        $isWritable = self::ACCESS_TYPE_METHOD === $access[self::ACCESS_TYPE]
            || self::ACCESS_TYPE_PROPERTY === $access[self::ACCESS_TYPE]
            || self::ACCESS_TYPE_ADDER_AND_REMOVER === $access[self::ACCESS_TYPE]
            || (!$access[self::ACCESS_HAS_PROPERTY] && property_exists($object, $property))
            || self::ACCESS_TYPE_MAGIC === $access[self::ACCESS_TYPE];

        if ($isWritable) {
            return true;
        }

        $access = $this->getWriteAccessInfo(\get_class($object), $property, '');

        return self::ACCESS_TYPE_METHOD === $access[self::ACCESS_TYPE]
            || self::ACCESS_TYPE_PROPERTY === $access[self::ACCESS_TYPE]
            || self::ACCESS_TYPE_ADDER_AND_REMOVER === $access[self::ACCESS_TYPE]
            || (!$access[self::ACCESS_HAS_PROPERTY] && property_exists($object, $property))
            || self::ACCESS_TYPE_MAGIC === $access[self::ACCESS_TYPE];
    }

    /**
     * Camelizes a given string.
     */
    private function camelize(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    /**
     * Searches for add and remove methods.
     */
    private function findAdderAndRemover(\ReflectionClass $reflClass, array $singulars): iterable
    {
        foreach ($singulars as $singular) {
            $addMethod = 'add'.$singular;
            $removeMethod = 'remove'.$singular;
            $result = ['methods' => [self::ACCESS_ADDER => $addMethod, self::ACCESS_REMOVER => $removeMethod]];

            $addMethodFound = $this->isMethodAccessible($reflClass, $addMethod, 1);

            if ($addMethodFound) {
                $result[self::ACCESS_ADDER] = $addMethod;
            }

            $removeMethodFound = $this->isMethodAccessible($reflClass, $removeMethod, 1);

            if ($removeMethodFound) {
                $result[self::ACCESS_REMOVER] = $removeMethod;
            }

            yield $result;
        }

        return null;
    }

    /**
     * Returns whether a method is public and has the number of required parameters.
     */
    private function isMethodAccessible(\ReflectionClass $class, string $methodName, int $parameters): bool
    {
        if ($class->hasMethod($methodName)) {
            $method = $class->getMethod($methodName);

            if ($method->isPublic()
                && $method->getNumberOfRequiredParameters() <= $parameters
                && $method->getNumberOfParameters() >= $parameters) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets a PropertyPath instance and caches it.
     *
     * @param string|PropertyPath $propertyPath
     */
    private function getPropertyPath($propertyPath): PropertyPath
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
     * @param string $namespace
     * @param int    $defaultLifetime
     * @param string $version
     *
     * @return AdapterInterface
     *
     * @throws \LogicException When the Cache Component isn't available
     */
    public static function createCache($namespace, $defaultLifetime, $version, LoggerInterface $logger = null)
    {
        if (null === $defaultLifetime) {
            @trigger_error(sprintf('Passing null as "$defaultLifetime" 2nd argument of the "%s()" method is deprecated since Symfony 4.4, pass 0 instead.', __METHOD__), E_USER_DEPRECATED);
        }

        if (!class_exists('Symfony\Component\Cache\Adapter\ApcuAdapter')) {
            throw new \LogicException(sprintf('The Symfony Cache component must be installed to use "%s()".', __METHOD__));
        }

        if (!ApcuAdapter::isSupported()) {
            return new NullAdapter();
        }

        $apcu = new ApcuAdapter($namespace, $defaultLifetime / 5, $version);
        if ('cli' === \PHP_SAPI && !filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN)) {
            $apcu->setLogger(new NullLogger());
        } elseif (null !== $logger) {
            $apcu->setLogger($logger);
        }

        return $apcu;
    }
}
