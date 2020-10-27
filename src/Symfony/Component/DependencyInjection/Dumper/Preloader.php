<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Dumper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Preloader
{
    public static function preload(array $classes)
    {
        set_error_handler(function ($t, $m, $f, $l) {
            if (error_reporting() & $t) {
                if (__FILE__ !== $f) {
                    throw new \ErrorException($m, 0, $t, $f, $l);
                }

                throw new \ReflectionException($m);
            }
        });

        $prev = [];
        $preloaded = [];

        try {
            while ($prev !== $classes) {
                $prev = $classes;
                foreach ($classes as $c) {
                    if (!isset($preloaded[$c])) {
                        self::doPreload($c, $preloaded);
                    }
                }
                $classes = array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits());
            }
        } finally {
            restore_error_handler();
        }
    }

    private static function doPreload(string $class, array &$preloaded): void
    {
        if (isset($preloaded[$class]) || \in_array($class, ['self', 'static', 'parent'], true)) {
            return;
        }

        $preloaded[$class] = true;

        try {
            $r = new \ReflectionClass($class);

            if ($r->isInternal()) {
                return;
            }

            $r->getConstants();
            $r->getDefaultProperties();

            if (\PHP_VERSION_ID >= 70400) {
                foreach ($r->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
                    self::preloadType($p->getType(), $preloaded);
                }
            }

            foreach ($r->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
                foreach ($m->getParameters() as $p) {
                    if ($p->isDefaultValueAvailable() && $p->isDefaultValueConstant()) {
                        $c = $p->getDefaultValueConstantName();

                        if ($i = strpos($c, '::')) {
                            self::doPreload(substr($c, 0, $i), $preloaded);
                        }
                    }

                    self::preloadType($p->getType(), $preloaded);
                }

                self::preloadType($m->getReturnType(), $preloaded);
            }
        } catch (\Throwable $e) {
            // ignore missing classes
        }
    }

    private static function preloadType(?\ReflectionType $t, array &$preloaded): void
    {
        if (!$t) {
            return;
        }

        foreach ($t instanceof \ReflectionUnionType ? $t->getTypes() : [$t] as $t) {
            if (!$t->isBuiltin()) {
                self::doPreload($t instanceof \ReflectionNamedType ? $t->getName() : $t, $preloaded);
            }
        }
    }
}
