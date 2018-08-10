<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Marshaller\PhpMarshaller;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Registry
{
    public static $stack = array();
    public static $objects = array();
    public static $references = array();
    public static $reflectors = array();
    public static $prototypes = array();
    public static $cloneable = array();
    public static $instantiableWithoutConstructor = array();

    public function __construct(array $classes)
    {
        foreach ($classes as $i => $class) {
            $this->$i = $class;
        }
    }

    public static function __set_state($classes)
    {
        $unserializeCallback = null;
        self::$stack[] = array(self::$objects, self::$references);
        self::$objects = $classes;
        self::$references = array();
        try {
            foreach (self::$objects as &$class) {
                if (':' === ($class[1] ?? null)) {
                    if (null === $unserializeCallback) {
                        $unserializeCallback = ini_set('unserialize_callback_func', __CLASS__.'::getClassReflector');
                    }
                    $class = \unserialize($class);
                    continue;
                }
                $r = self::$reflectors[$class] ?? self::getClassReflector($class);

                if (self::$cloneable[$class]) {
                    $class = clone self::$prototypes[$class];
                } else {
                    $class = self::$instantiableWithoutConstructor[$class] ? $r->newInstanceWithoutConstructor() : $r->newInstance();
                }
            }
        } catch (\Throwable $e) {
            list(self::$objects, self::$references) = \array_pop(self::$stack);
            throw $e;
        } finally {
            if (null !== $unserializeCallback) {
                ini_set('unserialize_callback_func', $unserializeCallback);
            }
        }
    }

    public static function getClassReflector($class)
    {
        $reflector = new \ReflectionClass($class);

        if (self::$instantiableWithoutConstructor[$class] = !$reflector->isFinal() || !$reflector->isInternal()) {
            $proto = $reflector->newInstanceWithoutConstructor();
        } else {
            try {
                $proto = $reflector->newInstance();
            } catch (\Throwable $e) {
                throw new \Exception(sprintf("Serialization of '%s' is not allowed", $class), 0, $e);
            }
        }

        if ($proto instanceof \Reflector || $proto instanceof \ReflectionGenerator || $proto instanceof \ReflectionType) {
            if (!$proto instanceof \Serializable && !\method_exists($proto, '__wakeup')) {
                throw new \Exception(sprintf("Serialization of '%s' is not allowed", $class));
            }
        }

        self::$prototypes[$class] = $proto;
        self::$cloneable[$class] = !$reflector->hasMethod('__clone');

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
