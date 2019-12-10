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
        if (!$class || isset($preloaded[$class]) || \in_array($class, ['self', 'static', 'parent'], true)) {
            return;
        }

        $preloaded[$class] = true;

        try {
            $file = $loader->findFile($class);

            if (!$file) {
                return;
            }

            foreach (self::getClassImports($file) as $import) {
                self::doPreload($import, $preloaded, $loader);
            }

            opcache_compile_file($file);

            if (!class_exists($class, false) && !interface_exists($class, false) && !trait_exists($class, false)) {
                return;
            }

            $r = new \ReflectionClass($class);

            if ($r->isInternal()) {
                return;
            }

            if (\PHP_VERSION_ID >= 70400) {
                foreach ($r->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
                    if (($t = $p->getType()) && !$t->isBuiltin()) {
                        self::doPreload($t->getName(), $preloaded, $loader);
                    }
                }
            }

            foreach ($r->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
                foreach ($m->getParameters() as $p) {
                    if ($p->isDefaultValueAvailable() && $p->isDefaultValueConstant()) {
                        $c = $p->getDefaultValueConstantName();

                        if ($i = strpos($c, '::')) {
                            self::doPreload(substr($c, 0, $i), $preloaded, $loader);
                        }
                    }

                    if (($t = $p->getType()) && !$t->isBuiltin()) {
                        self::doPreload($t->getName(), $preloaded, $loader);
                    }
                }

                if (($t = $m->getReturnType()) && !$t->isBuiltin()) {
                    self::doPreload($t->getName(), $preloaded, $loader);
                }
            }
        } catch (\ReflectionException $e) {
            // ignore missing classes
        }
    }

    private static function getClassImports($file)
    {
        $tokens = token_get_all(file_get_contents($file));

        $namespace = '';

        $use = [];
        $aliases = [];
        for ($i = 0; $i < \count($tokens); ++$i) {
            $token = $tokens[$i];

            if (T_NAMESPACE === $token[0]) {
                ++$i;
                $token = $tokens[++$i];

                while (';' !== $token && '{' !== $token) {
                    $namespace .= $token[1];
                    $token = $tokens[++$i];
                }
            }

            if (T_USE === $token[0]) {
                $c = '';
                ++$i;
                $token = $tokens[++$i];
                if ('function' === $token || 'constant' === $token) {
                    continue;
                }

                while (';' !== $token && \is_array($token)) {
                    $c .= $token[1];
                    $token = $tokens[++$i];

                    if (T_AS === $token[0]) {
                        ++$i;
                        $aliases[$tokens[++$i][1]] = $c;
                        $token = $tokens[++$i];
                    }
                }

                $use[] = trim($c);
            }

            if (T_EXTENDS === $token[0] || T_IMPLEMENTS === $token[0]) {
                extend:
                $i++;
                $token = $tokens[++$i];

                if (T_NS_SEPARATOR === $token[0]) {
                    continue;
                }

                $extends = '';
                while (T_WHITESPACE !== $token[0] && T_CURLY_OPEN !== $token[0] && \is_array($token)) {
                    $extends .= $tokens[$i][1];
                    $token = $tokens[++$i];
                }

                if (isset($aliases[$extends])) {
                    $use[] = $aliases[$extends];
                } else {
                    $hasImport = false;
                    foreach ($use as $class) {
                        if (false !== strpos($class, $extends)) {
                            $hasImport = true;
                            break;
                        }
                    }

                    if (!$hasImport) {
                        $use[] = $namespace.'\\'.$extends;
                    }
                }

                if (',' == $token) {
                    goto extend;
                }
            }
        }

        return $use;
    }
}
