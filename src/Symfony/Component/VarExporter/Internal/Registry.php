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
                if (false === $objects[$k] = unserialize($v)) {
                    throw new \Exception(error_get_last()['message'] ?? 'unserialize(): unknown error');
                }
            }
        } finally {
            ini_set('unserialize_callback_func', $unserializeCallback);
        }

        return $objects;
    }

    public static function p($class)
    {
        self::getClassReflector($class, true, true);

        return self::$prototypes[$class];
    }

    public static function f($class)
    {
        $reflector = self::$reflectors[$class] ?? self::getClassReflector($class, true, false);

        return self::$factories[$class] = \Closure::fromCallable(array($reflector, 'newInstanceWithoutConstructor'));
    }

    public static function getClassReflector($class, $instantiableWithoutConstructor = false, $cloneable = null)
    {
        $reflector = new \ReflectionClass($class);

        if (self::$instantiableWithoutConstructor[$class] = $instantiableWithoutConstructor || !$reflector->isFinal()) {
            $proto = $reflector->newInstanceWithoutConstructor();
        } else {
            try {
                $proto = $reflector->newInstanceWithoutConstructor();
                self::$instantiableWithoutConstructor[$class] = true;
            } catch (\ReflectionException $e) {
                $proto = $reflector->implementsInterface('Serializable') ? 'C:' : 'O:';
                if ('C:' === $proto && !$reflector->getMethod('unserialize')->isInternal()) {
                    $proto = null;
                } elseif (false === $proto = @unserialize($proto.\strlen($class).':"'.$class.'":0:{}')) {
                    throw new \Exception(sprintf("Serialization of '%s' is not allowed", $class));
                }
            }
        }

        if (null === self::$cloneable[$class] = $cloneable) {
            if (($proto instanceof \Reflector || $proto instanceof \ReflectionGenerator || $proto instanceof \ReflectionType || $proto instanceof \IteratorIterator || $proto instanceof \RecursiveIteratorIterator) && (!$proto instanceof \Serializable && !\method_exists($proto, '__wakeup'))) {
                throw new \Exception(sprintf("Serialization of '%s' is not allowed", $class));
            }

            self::$cloneable[$class] = $reflector->isCloneable() && !$reflector->hasMethod('__clone');
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
