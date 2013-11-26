<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Util;

use Symfony\Component\PropertyAccess\StringUtil;

/**
 * @author Andrew Moore <me@andrewmoore.ca>
 */
class ClassReflectionUtil
{
    /**
     * This class should not be instantiated
     */
    private function __construct()
    {
    }

    /**
     * Detects if a property or adder/setter is available for a given
     * property.
     *
     * @param string $class    The class to verify against
     * @param string $property The property to verify
     *
     * @return Boolean
     */
    public static function hasPropertyAvailable($class, $property)
    {
        if (!class_exists($class)) {
            return false;
        }

        $reflClass = new \ReflectionClass($class);

        $setter = 'set' . self::camelize($property);
        $classHasProperty = $reflClass->hasProperty($property);

        if ($reflClass->hasMethod($setter) && $reflClass->getMethod($setter)->isPublic()) {
            return true;
        }
        if ($reflClass->hasMethod('__set') && $reflClass->getMethod('__set')->isPublic()) {
            return true;
        }
        if ($classHasProperty && $reflClass->getProperty($property)->isPublic()) {
            return true;
        }

        $plural = self::camelize($property);
        $singular = StringUtil::singularify($plural);

        $adder = 'add' . $singular;
        $remover = 'remove' . $singular;

        $adderFound = self::isMethodAccessible($reflClass, $adder, 1);
        $removerFound = self::isMethodAccessible($reflClass, $remover, 1);

        if ($adderFound && $removerFound) {
            return true;
        }

        return false;
    }

    /**
     * Camelizes a given string.
     *
     * @param string $string Some string
     *
     * @return string The camelized version of the string
     */
    private static function camelize($string)
    {
        return preg_replace_callback(
            '/(^|_|\.)+(.)/',
            function ($match) {
                return ('.' === $match[1] ? '_' : '') . strtoupper($match[2]);
            },
            $string
        );
    }

    /**
     * Returns whether a method is public and has a specific number of required parameters.
     *
     * @param \ReflectionClass $class      The class of the method
     * @param string           $methodName The method name
     * @param integer          $parameters The number of parameters
     *
     * @return Boolean Whether the method is public and has $parameters
     *                                      required parameters
     */
    private static function isMethodAccessible(\ReflectionClass $class, $methodName, $parameters)
    {
        if ($class->hasMethod($methodName)) {
            $method = $class->getMethod($methodName);

            if ($method->isPublic() && $method->getNumberOfRequiredParameters() === $parameters) {
                return true;
            }
        }

        return false;
    }
} 