<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Marshaller;

use Symfony\Component\Cache\Marshaller\PhpMarshaller\Configurator;
use Symfony\Component\Cache\Marshaller\PhpMarshaller\Marshaller;
use Symfony\Component\Cache\Marshaller\PhpMarshaller\Reference;
use Symfony\Component\Cache\Marshaller\PhpMarshaller\Registry;
use Symfony\Component\Cache\Marshaller\PhpMarshaller\Values;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * PhpMarshaller allows serializing PHP data structures using var_export()
 * while preserving all the semantics associated to serialize().
 *
 * By leveraging OPcache, the generated PHP code is faster than doing the same with unserialize().
 *
 * @internal
 */
class PhpMarshaller
{
    public static function marshall($value, bool &$isStaticValue = null): string
    {
        $isStaticValue = true;

        if (!\is_object($value) && !(\is_array($value) && $value) && !$value instanceof \__PHP_Incomplete_Class && !\is_resource($value)) {
            return var_export($value, true);
        }

        $objectsPool = new \SplObjectStorage();
        $refsPool = array();
        $objectsCount = 0;

        try {
            $value = Marshaller::marshall(array($value), $objectsPool, $refsPool, $objectsCount, $isStaticValue)[0];
        } finally {
            $references = array();
            foreach ($refsPool as $i => $v) {
                $v[0] = $v[1];
                $references[1 + $i] = $v[2];
            }
        }

        if ($isStaticValue) {
            return var_export($value, true);
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

        $value = new Configurator($classes ? new Registry($classes) : null, $references ? new Values($references) : null, $properties, $value, $wakeups);
        $value = var_export($value, true);

        $regexp = sprintf("{%s::__set_state\(array\(\s++'id' => %%s(\d+),\s++\)\)}", preg_quote(Reference::class));
        $value = preg_replace(sprintf($regexp, ''), Registry::class.'::$objects[$1]', $value);
        $value = preg_replace(sprintf($regexp, '-'), '&'.Registry::class.'::$references[$1]', $value);

        return $value;
    }
}
