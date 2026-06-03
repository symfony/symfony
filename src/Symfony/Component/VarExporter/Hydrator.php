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

trigger_deprecation('symfony/var-exporter', '8.1', 'The "%s" class is deprecated, use "deepclone_hydrate()" from the deepclone extension instead.', Hydrator::class);

/**
 * Utility class to hydrate the properties of an object.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @deprecated since Symfony 8.1, use deepclone_hydrate() from the deepclone extension instead
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
     * @param T                                         $instance    The object to hydrate
     * @param array<string, mixed>                      $mangledVars The properties to set on the instance
     * @param array<class-string, array<string, mixed>> $scopedVars  The properties to set on the instance,
     *                                                               keyed by their declaring class
     *
     * @return T
     */
    public static function hydrate(object $instance, array $mangledVars = [], array $scopedVars = []): object
    {
        if ($scopedVars) {
            $class = $instance::class;
            foreach ($scopedVars as $scope => $props) {
                $isOwnScope = $scope === $class || 'stdClass' === $scope;
                foreach ($props as $name => &$value) {
                    $mangledVars[$isOwnScope ? $name : "\0$scope\0$name"] = &$value;
                }
            }
            unset($value);
        }

        if (\is_array($splState = $mangledVars["\0"] ?? null)) {
            unset($mangledVars["\0"]);
            if ($instance instanceof \SplObjectStorage) {
                $instance->__unserialize([$splState, []]);
            } elseif ($instance instanceof \ArrayObject) {
                $instance->__unserialize([$splState[1] ?? 0, $splState[0] ?? [], [], $splState[2] ?? \ArrayIterator::class]);
            } elseif ($instance instanceof \ArrayIterator) {
                $instance->__unserialize([$splState[1] ?? 0, $splState[0] ?? [], []]);
            }
        }

        if ($mangledVars) {
            deepclone_hydrate($instance, $mangledVars, \DEEPCLONE_HYDRATE_PRESERVE_REFS);
        }

        return $instance;
    }
}
