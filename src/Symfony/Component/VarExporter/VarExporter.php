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
use Symfony\Component\VarExporter\Internal\Exporter;

/**
 * Exports serializable PHP values to PHP code.
 *
 * VarExporter allows serializing PHP data structures to plain PHP code (like var_export())
 * while preserving all the semantics associated with serialize() (unlike var_export()).
 *
 * By leveraging OPcache, the generated PHP code is faster than doing the same with unserialize().
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class VarExporter
{
    /**
     * Exports each array item on its own line instead of inlining short flat arrays.
     */
    public const NO_INLINE_ARRAY = 1;

    /**
     * Exports a serializable PHP value to PHP code.
     *
     * @param bool                              &$isStaticValue Set to true after execution if the provided value is static, false otherwise
     * @param array<class-string, class-string> &$foundClasses  Classes found in the value are added to this list as both keys and values
     * @param int                               $flags          A bitmask of self::* constants to tweak the generated code
     *
     * @throws ExceptionInterface When the provided value cannot be serialized
     */
    public static function export(mixed $value, ?bool &$isStaticValue = null, array &$foundClasses = [], int $flags = 0): string
    {
        $isStaticValue = true;

        if (!\is_object($value) && !(\is_array($value) && $value) && !\is_resource($value) || $value instanceof \UnitEnum) {
            return Exporter::export($value, '', $flags);
        }

        if (\is_resource($value)) {
            throw new NotInstantiableTypeException(get_resource_type($value).' resource');
        }

        try {
            $data = deepclone_to_array($value, null, true);
        } catch (\DeepClone\NotInstantiableException $e) {
            throw new NotInstantiableTypeException($e);
        }

        if (\array_key_exists('value', $data)) {
            return Exporter::export($data['value'], '', $flags);
        }

        $isStaticValue = false;

        $classes = $data['classes'];
        if (!\is_string($classes)) {
            foreach ($classes as $class) {
                $foundClasses[$class] = $class;
            }
        } elseif ('' !== $classes) {
            $foundClasses[$classes] = $classes;
        }

        return '\deepclone_from_array('.Exporter::export($data, '', $flags).', null, true)';
    }
}
