<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Util;

use Go\ParserReflection\ReflectionClass;

/**
 * ReflectionHelper provides method for native or static reflection of class.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class ReflectionHelper
{
    private static $staticReflection = false;
    private static $reflections = array();
    private $existsGoAopReflection;

    public function __construct()
    {
        $this->existsGoAopReflection = class_exists(ReflectionClass::class);
    }

    public static function preferStaticReflection($use)
    {
        $oldSetup = self::$staticReflection;
        self::$staticReflection = (bool) $use;

        return $oldSetup;
    }

    /**
     * Returns reflection instance for class.
     *
     * @param string $class Full name of the class
     *
     * @return \ReflectionClass|false
     */
    public function getReflectionClass($class)
    {
        if (isset(self::$reflections[$class])) {
            return self::$reflections[$class];
        }

        if (!self::$staticReflection || class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
            return $this->getNativeReflection($class);
        }

        if ($this->existsGoAopReflection) {
            return $this->getGoParserReflection($class);
        }

        return $this->getNativeReflection($class);
    }

    private function getNativeReflection($class)
    {
        try {
            $reflector = self::$reflections[$class] = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            $reflector = false;
        }

        return self::$reflections[$class] = $reflector;
    }

    private function getGoParserReflection($class)
    {
        try {
            $reflectionClass = new ReflectionClass($class);
            $reflectionClass->__toString(); // checks that reflection is complete and valid

            $reflector = self::$reflections[$class] = $reflectionClass;
        } catch (\InvalidArgumentException $e) {
            $reflector = false;
        }

        return self::$reflections[$class] = $reflector;
    }
}
