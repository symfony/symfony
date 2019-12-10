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
    public static function preload(array $classes, $loader)
    {
        set_error_handler(function ($t, $m, $f, $l) {
            if (error_reporting() & $t) {
                if (__FILE__ !== $f) {
                    throw new \ErrorException($m, 0, $t, $f, $l);
                }

                throw new \ReflectionException($m);
            }
        });

        $loader->unregister();
        $prev = [];
        $loadedClasses = array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits());
        $preloaded = array_combine($loadedClasses, array_fill(0, \count($loadedClasses), true));

        try {
            while ($prev !== $classes) {
                $prev = $classes;
                foreach ($classes as $c) {
                    if (!isset($preloaded[$c])) {
                        self::doPreload($c, $preloaded, $loader);
                    }
                }
                $classes = array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits());
            }
        } finally {
            restore_error_handler();
        }
    }

    private static function doPreload(string $class, array &$preloaded, $loader)
    {
        if (isset($preloaded[$class])) {
            return;
        }

        $preloaded[$class] = true;

        $file = $loader->findFile($class);

        if (!$file) {
            return;
        }

        opcache_compile_file($file);
    }
}
