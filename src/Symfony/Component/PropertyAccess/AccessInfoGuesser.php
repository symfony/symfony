<?php

namespace Symfony\Component\PropertyAccess;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Inflector\Inflector;

final class AccessInfoGuesser
{
    public const ACCESS_HAS_PROPERTY = 0;
    public const ACCESS_TYPE = 1;
    public const ACCESS_NAME = 2;
    public const ACCESS_REF = 3;
    public const ACCESS_ADDER = 4;
    public const ACCESS_REMOVER = 5;
    public const ACCESS_TYPE_METHOD = 0;
    public const ACCESS_TYPE_PROPERTY = 1;
    public const ACCESS_TYPE_MAGIC = 2;
    public const ACCESS_TYPE_ADDER_AND_REMOVER = 3;
    public const ACCESS_TYPE_NOT_FOUND = 4;
    public const CACHE_PREFIX_READ = 'r';
    public const CACHE_PREFIX_WRITE = 'w';

    /**
     * @var bool
     */
    private $magicCall;

    private $readPropertyCache = array();
    private $writePropertyCache = array();

    /**
     * @var CacheItemPoolInterface
     */
    private $cacheItemPool;

    public function __construct(bool $magicCall, CacheItemPoolInterface $cacheItemPool = null)
    {
        $this->magicCall = $magicCall;
        $this->cacheItemPool = $cacheItemPool instanceof NullAdapter ? null : $cacheItemPool; // Replace the NullAdapter by the null value
    }

    /**
     * Guesses how to write the property value.
     *
     * @param mixed $value
     */
    public function getWriteAccessInfo(string $class, string $property, $value): array
    {
        $key = str_replace('\\', '.', $class).'..'.$property;

        if (isset($this->writePropertyCache[$key])) {
            return $this->writePropertyCache[$key];
        }

        if ($this->cacheItemPool) {
            $item = $this->cacheItemPool->getItem(self::CACHE_PREFIX_WRITE.rawurlencode($key));
            if ($item->isHit()) {
                return $this->writePropertyCache[$key] = $item->get();
            }
        }

        $access = array();

        $reflClass = new \ReflectionClass($class);
        $access[self::ACCESS_HAS_PROPERTY] = $reflClass->hasProperty($property);
        $camelized = $this->camelize($property);
        $singulars = (array) Inflector::singularize($camelized);

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
            } elseif (null !== $methods = $this->findAdderAndRemover($reflClass, $singulars)) {
                if (\is_array($value) || $value instanceof \Traversable) {
                    $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_ADDER_AND_REMOVER;
                    $access[self::ACCESS_ADDER] = $methods[0];
                    $access[self::ACCESS_REMOVER] = $methods[1];
                } else {
                    $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_NOT_FOUND;
                    $access[self::ACCESS_NAME] = sprintf(
                        'The property "%s" in class "%s" can be defined with the methods "%s()" but '.
                        'the new value must be an array or an instance of \Traversable, '.
                        '"%s" given.',
                        $property,
                        $reflClass->name,
                        implode('()", "', $methods),
                        \is_object($value) ? \get_class($value) : \gettype($value)
                    );
                }
            } else {
                $access[self::ACCESS_TYPE] = self::ACCESS_TYPE_NOT_FOUND;
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

        if (isset($item)) {
            $this->cacheItemPool->save($item->set($access));
        }

        return $this->writePropertyCache[$key] = $access;
    }

    /**
     * Guesses how to read the property value.
     *
     * @param string $class
     * @param string $property
     *
     * @return array
     */
    public function getReadAccessInfo($class, $property)
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

        $access = array();

        $reflClass = new \ReflectionClass($class);
        $access[self::ACCESS_HAS_PROPERTY] = $reflClass->hasProperty($property);
        $camelProp = $this->camelize($property);
        $getter = 'get'.$camelProp;
        $getsetter = lcfirst($camelProp); // jQuery style, e.g. read: last(), write: last($item)
        $isser = 'is'.$camelProp;
        $hasser = 'has'.$camelProp;

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
            $methods = array($getter, $getsetter, $isser, $hasser, '__get');
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
     * Searches for add and remove methods.
     *
     * @param \ReflectionClass $reflClass The reflection class for the given object
     * @param array            $singulars The singular form of the property name or null
     *
     * @return array|null An array containing the adder and remover when found, null otherwise
     */
    private function findAdderAndRemover(\ReflectionClass $reflClass, array $singulars)
    {
        foreach ($singulars as $singular) {
            $addMethod = 'add'.$singular;
            $removeMethod = 'remove'.$singular;

            $addMethodFound = $this->isMethodAccessible($reflClass, $addMethod, 1);
            $removeMethodFound = $this->isMethodAccessible($reflClass, $removeMethod, 1);

            if ($addMethodFound && $removeMethodFound) {
                return array($addMethod, $removeMethod);
            }
        }
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
     * Camelizes a given string.
     */
    private function camelize(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
}
