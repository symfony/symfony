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
    public static $reflectors = array();
    public static $prototypes = array();

    public function __construct(array $classes)
    {
        foreach ($classes as $i => $class) {
            $this->$i = $class;
        }
    }

    public static function __set_state($classes)
    {
        self::$stack[] = self::$objects;
        self::$objects = $classes;
        foreach (self::$objects as &$class) {
            if (isset(self::$prototypes[$class])) {
                $class = clone self::$prototypes[$class];
            } elseif (':' === ($class[1] ?? null)) {
                $class = \unserialize($class);
            } else {
                $class = (self::$reflectors[$class] ?? self::getClassReflector($class))->newInstanceWithoutConstructor();
            }
        }
    }

    public static function getClassReflector($class)
    {
        $reflector = new \ReflectionClass($class);

        if (!$reflector->hasMethod('__clone')) {
            self::$prototypes[$class] = $reflector->newInstanceWithoutConstructor();
        }

        return self::$reflectors[$class] = $reflector;
    }
}
