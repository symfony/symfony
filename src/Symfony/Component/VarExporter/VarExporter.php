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
     * Exports a serializable PHP value to PHP code.
     *
     * @param bool                              &$isStaticValue Set to true after execution if the provided value is static, false otherwise
     * @param array<class-string, class-string> &$foundClasses  Classes found in the value are added to this list as both keys and values
     *
     * @throws ExceptionInterface When the provided value cannot be serialized
     */
    public static function export(mixed $value, ?bool &$isStaticValue = null, array &$foundClasses = []): string
    {
        $isStaticValue = true;

        if (!\is_object($value) && !(\is_array($value) && $value) && !\is_resource($value) || $value instanceof \UnitEnum) {
            return Exporter::export($value);
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
            return Exporter::export($data['value']);
        }

        $isStaticValue = false;

        $classes = $data['classes'];
        if (!\is_string($classes)) {
            foreach ($classes as $class) {
                $foundClasses[$class] = $class;
            }
        } elseif ('' !== $classes) {
            $foundClasses[$classes] = $classes;
            $classes = [$classes];
        } else {
            $classes = [];
        }

        return self::addUnserializeFallback('\deepclone_from_array('.Exporter::export($data).', null, true)', $value, $data, $classes);
    }

    /**
     * Rebuilds small values with unserialize() when the deepclone extension is not loaded, which is faster than its polyfill.
     *
     * OPcache resolves the extension_loaded() check at compile time and drops the other branch.
     * Values whose classes are missing go to deepclone_from_array(), which reports them.
     */
    private static function addUnserializeFallback(string $code, mixed $value, array $data, array $classes): string
    {
        foreach ($classes as $class) {
            if (':' === ($class[1] ?? null)) {
                return $code;
            }
        }

        $clean = true;
        set_error_handler(static function () use (&$clean) {
            $clean = false;

            return true;
        });

        try {
            $serialized = serialize($value);
        } catch (\Throwable) {
            return $code;
        } finally {
            restore_error_handler();
        }

        if (!$clean || 4096 < \strlen($serialized)) {
            return $code;
        }

        $checks = [];
        array_walk_recursive($data, static function ($v) use (&$checks) {
            if ($v instanceof \UnitEnum) {
                $checks[$v::class] = $v::class;
            }
        });
        foreach ($classes as $class) {
            $checks[$class] = $class;
        }

        $guard = '';
        foreach ($checks as $class) {
            $guard .= ' || !\class_exists('.Exporter::export($class).')';
        }

        return "(\\extension_loaded('deepclone'){$guard} ? {$code} : \\unserialize(".Exporter::export($serialized).'))';
    }
}
