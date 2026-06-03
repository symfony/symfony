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

use Symfony\Component\VarExporter\Exception\ClassNotFoundException;
use Symfony\Component\VarExporter\Exception\ExceptionInterface;
use Symfony\Component\VarExporter\Exception\NotInstantiableTypeException;

trigger_deprecation('symfony/var-exporter', '8.1', 'The "%s" class is deprecated, use "deepclone_hydrate()" from the deepclone extension instead.', Instantiator::class);

/**
 * A utility class to create objects without calling their constructor.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @deprecated since Symfony 8.1, use deepclone_hydrate() from the deepclone extension instead
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
     * @param class-string<T>                           $class       The class of the instance to create
     * @param array<string, mixed>                      $mangledVars The properties to set on the instance
     * @param array<class-string, array<string, mixed>> $scopedVars  The properties to set on the instance,
     *                                                               keyed by their declaring class
     *
     * @return T
     *
     * @throws ExceptionInterface When the instance cannot be created
     */
    public static function instantiate(string $class, array $mangledVars = [], array $scopedVars = []): object
    {
        if ($scopedVars) {
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
        }

        try {
            $instance = deepclone_hydrate($class, $mangledVars, \DEEPCLONE_HYDRATE_PRESERVE_REFS);
        } catch (\DeepClone\ClassNotFoundException $e) {
            throw new ClassNotFoundException($e);
        } catch (\DeepClone\NotInstantiableException $e) {
            throw new NotInstantiableTypeException($e);
        }

        if (!\is_array($splState)) {
            return $instance;
        }

        if ($instance instanceof \SplObjectStorage) {
            $instance->__unserialize([$splState, []]);
        } elseif ($instance instanceof \ArrayObject) {
            $instance->__unserialize([$splState[1] ?? 0, $splState[0] ?? [], [], $splState[2] ?? \ArrayIterator::class]);
        } elseif ($instance instanceof \ArrayIterator) {
            $instance->__unserialize([$splState[1] ?? 0, $splState[0] ?? [], []]);
        }

        return $instance;
    }
}
