<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter;

use Symfony\Component\VarExporter\Exception\ExceptionInterface;
use Symfony\Component\VarExporter\Exception\NotInstantiableTypeException;
use Symfony\Component\VarExporter\Internal\Registry;

/**
 * A utility class to create objects without calling their constructor.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Instantiator
{
    /**
     * Creates an object and sets its properties without calling its constructor nor any other methods.
     *
     * @see Hydrator::hydrate() for examples
     *
     * @template T of object
     *
     * @param class-string<T>                           $class            The class of the instance to create
     * @param array<string, mixed>                      $properties       The properties to set on the instance
     * @param array<class-string, array<string, mixed>> $scopedProperties The properties to set on the instance,
     *                                                                    keyed by their declaring class
     *
     * @return T
     *
     * @throws ExceptionInterface When the instance cannot be created
     */
    public static function instantiate(string $class, array $properties = [], array $scopedProperties = []): object
    {
        $reflector = Registry::$reflectors[$class] ??= Registry::getClassReflector($class);

        if (Registry::$cloneable[$class]) {
            $instance = clone Registry::$prototypes[$class];
        } elseif (Registry::$instantiableWithoutConstructor[$class]) {
            $instance = $reflector->newInstanceWithoutConstructor();
        } elseif (null === Registry::$prototypes[$class]) {
            throw new NotInstantiableTypeException($class);
        } elseif ($reflector->implementsInterface('Serializable') && !method_exists($class, '__unserialize')) {
            $instance = unserialize('C:'.\strlen($class).':"'.$class.'":0:{}');
        } else {
            $instance = unserialize('O:'.\strlen($class).':"'.$class.'":0:{}');
        }

        return $properties || $scopedProperties ? Hydrator::hydrate($instance, $properties, $scopedProperties) : $instance;
    }
}
