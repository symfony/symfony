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

use Symfony\Component\VarExporter\Internal\Hydrator as InternalHydrator;

/**
 * Utility class to hydrate the properties of an object.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Hydrator
{
    /**
     * Sets the properties of an object, including private and protected ones.
     *
     * For example:
     *
     *     // Sets the public or protected $object->propertyName property
     *     Hydrator::hydrate($object, ['propertyName' => $propertyValue]);
     *
     *     // Sets a private property defined on its parent Bar class:
     *     Hydrator::hydrate($object, ["\0Bar\0privateBarProperty" => $propertyValue]);
     *
     *     // Alternative way to set the private $object->privateBarProperty property
     *     Hydrator::hydrate($object, [], [
     *         Bar::class => ['privateBarProperty' => $propertyValue],
     *     ]);
     *
     * Instances of ArrayObject, ArrayIterator and SplObjectStorage can be hydrated
     * by using the special "\0" property name to define their internal value:
     *
     *     // Hydrates an SplObjectStorage where $info1 is attached to $obj1, etc.
     *     Hydrator::hydrate($object, ["\0" => [$obj1, $info1, $obj2, $info2...]]);
     *
     *     // Hydrates an ArrayObject populated with $inputArray
     *     Hydrator::hydrate($object, ["\0" => [$inputArray]]);
     *
     * @template T of object
     *
     * @param T                                         $instance         The object to hydrate
     * @param array<string, mixed>                      $properties       The properties to set on the instance
     * @param array<class-string, array<string, mixed>> $scopedProperties The properties to set on the instance,
     *                                                                    keyed by their declaring class
     *
     * @return T
     */
    public static function hydrate(object $instance, array $properties = [], array $scopedProperties = []): object
    {
        if ($properties) {
            $class = $instance::class;
            $propertyScopes = InternalHydrator::$propertyScopes[$class] ??= InternalHydrator::getPropertyScopes($class);

            foreach ($properties as $name => &$value) {
                [$scope, $name, $readonlyScope] = $propertyScopes[$name] ?? [$class, $name, $class];
                $scopedProperties[$readonlyScope ?? $scope][$name] = &$value;
            }
            unset($value);
        }

        foreach ($scopedProperties as $scope => $properties) {
            if ($properties) {
                (InternalHydrator::$simpleHydrators[$scope] ??= InternalHydrator::getSimpleHydrator($scope))($properties, $instance);
            }
        }

        return $instance;
    }
}
