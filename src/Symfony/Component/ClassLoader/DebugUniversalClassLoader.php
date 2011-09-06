<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ClassLoader;

/**
 * Checks that the class is actually declared in the included file.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DebugUniversalClassLoader extends UniversalClassLoader
{
    /**
     * Replaces all regular UniversalClassLoader instances by a DebugUniversalClassLoader ones.
     */
    static public function enable()
    {
        if (!is_array($functions = spl_autoload_functions())) {
            return;
        }

        foreach ($functions as $function) {
            spl_autoload_unregister($function);
        }

        foreach ($functions as $function) {
            if (is_array($function) && $function[0] instanceof UniversalClassLoader) {
                $loader = new static();
                $loader->registerNamespaceFallbacks($function[0]->getNamespaceFallbacks());
                $loader->registerPrefixFallbacks($function[0]->getPrefixFallbacks());
                $loader->registerNamespaces($function[0]->getNamespaces());
                $loader->registerPrefixes($function[0]->getPrefixes());
                $loader->useIncludePath($function[0]->getUseIncludePath());

                $function[0] = $loader;
            }

            spl_autoload_register($function);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function loadClass($class)
    {
        if ($file = $this->findFile($class)) {
            require $file;

            if (!$this->isLoaded($class)) {
                throw new \RuntimeException(sprintf('The autoloader expected class "%s" to be defined in file "%s". The file was found but the class was not in it, the class name or namespace probably has a typo.', $class, $file));
            }
        }
    }

    /**
     * Checks if $class is either an existing class, interface or trait.
     *
     * @param string $class
     * @param bool   $autoload
     *
     * @return bool
     */
    private function isLoaded($class)
    {
        // Check for class or interface
        if (class_exists($class, false) || interface_exists($class, false)) {
            return true;
        }

        // Check for traits only if available (PHP >= 5.4)
        if (function_exists('trait_exists') && trait_exists($class, false)) {
            return true;
        }

        return false;
    }
}
