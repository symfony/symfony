<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Fixtures;

/**
 * Hides installed packages from the autoloaders, like they were not installed: for tests run in a separate process,
 * before any of their classes is loaded.
 */
final class MissingPackages
{
    public static function hide(string ...$namespaces): void
    {
        $loaders = spl_autoload_functions();
        foreach ($loaders as $loader) {
            spl_autoload_unregister($loader);
        }
        spl_autoload_register(static function (string $class) use ($loaders, $namespaces): void {
            foreach ($namespaces as $namespace) {
                if (str_starts_with($class, $namespace)) {
                    return;
                }
            }
            foreach ($loaders as $loader) {
                $loader($class);
                if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false) || enum_exists($class, false)) {
                    return;
                }
            }
        });
    }
}
