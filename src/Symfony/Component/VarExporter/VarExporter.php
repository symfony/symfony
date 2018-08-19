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

use Symfony\Component\VarExporter\Internal\Configurator;
use Symfony\Component\VarExporter\Internal\Exporter;
use Symfony\Component\VarExporter\Internal\Reference;
use Symfony\Component\VarExporter\Internal\Registry;
use Symfony\Component\VarExporter\Internal\Values;

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
     * @param mixed $value          The value to export
     * @param bool  &$isStaticValue Set to true after execution if the provided value is static, false otherwise
     *
     * @return string The value exported as PHP code
     *
     * @throws \Exception When the provided value cannot be serialized
     */
    public static function export($value, bool &$isStaticValue = null): string
    {
        $isStaticValue = true;

        if (!\is_object($value) && !(\is_array($value) && $value) && !$value instanceof \__PHP_Incomplete_Class && !\is_resource($value)) {
            return Exporter::export($value);
        }

        $objectsPool = new \SplObjectStorage();
        $refsPool = array();
        $objectsCount = 0;

        try {
            $value = Exporter::prepare(array($value), $objectsPool, $refsPool, $objectsCount, $isStaticValue)[0];
        } finally {
            $references = array();
            foreach ($refsPool as $i => $v) {
                $v[0] = $v[1];
                $references[1 + $i] = $v[2];
            }
        }

        if ($isStaticValue) {
            return Exporter::export($value);
        }

        $classes = array();
        $values = array();
        $wakeups = array();
        foreach ($objectsPool as $i => $v) {
            list(, $classes[], $values[], $wakeup) = $objectsPool[$v];
            if ($wakeup) {
                $wakeups[$wakeup] = $i;
            }
        }
        ksort($wakeups);

        $properties = array();
        foreach ($values as $i => $vars) {
            foreach ($vars as $class => $values) {
                foreach ($values as $name => $v) {
                    $properties[$class][$name][$i] = $v;
                }
            }
        }

        $value = new Configurator(new Registry($classes), $references ? new Values($references) : null, $properties, $value, $wakeups);

        return Exporter::export($value);
    }
}
