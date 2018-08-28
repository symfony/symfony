<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Internal;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Registry
{
    public static $reflectors = array();
    public static $prototypes = array();
    public static $factories = array();
    public static $cloneable = array();
    public static $instantiableWithoutConstructor = array();

    public function __construct(array $classes)
    {
        foreach ($classes as $i => $class) {
            $this->$i = $class;
        }
    }

    public static function unserialize($objects, $serializables)
    {
        $unserializeCallback = ini_set('unserialize_callback_func', __CLASS__.'::getClassReflector');

        try {
            foreach ($serializables as $k => $v) {
                $objects[$k] = unserialize($v);
            }
        } finally {
            ini_set('unserialize_callback_func', $unserializeCallback);
        }

        return $objects;
    }

    public static function p($class, $instantiableWithoutConstructor)
    {
        self::getClassReflector($class, $instantiableWithoutConstructor, true);

        return self::$prototypes[$class];
    }

    public static function f($class, $instantiableWithoutConstructor)
    {
        $reflector = self::$reflectors[$class] ?? self::getClassReflector($class, $instantiableWithoutConstructor, false);

        return self::$factories[$class] = \Closure::fromCallable(array($reflector, $instantiableWithoutConstructor ? 'newInstanceWithoutConstructor' : 'newInstance'));
    }

    public static function getClassReflector($class, $instantiableWithoutConstructor = null, $cloneable = null)
    {
        $reflector = new \ReflectionClass($class);

        if (self::$instantiableWithoutConstructor[$class] = $instantiableWithoutConstructor ?? (!$reflector->isFinal() || !$reflector->isInternal())) {
            $proto = $reflector->newInstanceWithoutConstructor();
        } else {
            try {
                $proto = $reflector->newInstance();
            } catch (\Throwable $e) {
                throw new \Exception(sprintf("Serialization of '%s' is not allowed", $class), 0, $e);
            }
        }

        if (null !== $cloneable) {
            self::$prototypes[$class] = $proto;
            self::$cloneable[$class] = $cloneable;

            return self::$reflectors[$class] = $reflector;
        }

        if ($proto instanceof \Reflector || $proto instanceof \ReflectionGenerator || $proto instanceof \ReflectionType || $proto instanceof \IteratorIterator || $proto instanceof \RecursiveIteratorIterator) {
            if (!$proto instanceof \Serializable && !\method_exists($proto, '__wakeup')) {
                throw new \Exception(sprintf("Serialization of '%s' is not allowed", $class));
            }
            self::$cloneable[$class] = false;
        } else {
            self::$cloneable[$class] = !$reflector->hasMethod('__clone');
        }

        self::$prototypes[$class] = $proto;

        if ($proto instanceof \Throwable) {
            static $trace;

            if (null === $trace) {
                $trace = array(
                    new \ReflectionProperty(\Error::class, 'trace'),
                    new \ReflectionProperty(\Exception::class, 'trace'),
                );
                $trace[0]->setAccessible(true);
                $trace[1]->setAccessible(true);
            }

            $trace[$proto instanceof \Exception]->setValue($proto, array());
        }

        return self::$reflectors[$class] = $reflector;
    }
}
