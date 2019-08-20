<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ProxyManager\Generator\Util;

use Composer\Autoload\ClassLoader;
use ProxyManager\Version;

if (class_exists(Version::class) && version_compare(\defined(Version::class.'::VERSION') ? Version::VERSION : Version::getVersion(), '2.5', '<')) {
    /**
     * Utility class to generate return expressions in method, given a method signature.
     *
     * This is required since return expressions may be forbidden by the method signature (void).
     *
     * @author Marco Pivetta <ocramius@gmail.com>
     * @license MIT
     *
     * @see https://github.com/Ocramius/ProxyManager
     */
    final class ProxiedMethodReturnExpression
    {
        public static function generate(string $returnedValueExpression, ?\ReflectionMethod $originalMethod): string
        {
            $originalReturnType = null === $originalMethod ? null : $originalMethod->getReturnType();

            $originalReturnTypeName = null === $originalReturnType ? null : $originalReturnType->getName();

            if ('void' === $originalReturnTypeName) {
                return $returnedValueExpression.";\nreturn;";
            }

            return 'return '.$returnedValueExpression.';';
        }
    }
} else {
    // Fallback to the original class by unregistering this file from composer class loader
    $getComposerClassLoader = static function ($functionLoader) use (&$getComposerClassLoader) {
        if (\is_array($functionLoader)) {
            $functionLoader = $functionLoader[0];
        }
        if (!\is_object($functionLoader)) {
            return null;
        }
        if ($functionLoader instanceof ClassLoader) {
            return $functionLoader;
        }
        if ($functionLoader instanceof \Symfony\Component\Debug\DebugClassLoader) {
            return $getComposerClassLoader($functionLoader->getClassLoader());
        }
        if ($functionLoader instanceof \Symfony\Component\ErrorHandler\DebugClassLoader) {
            return $getComposerClassLoader($functionLoader->getClassLoader());
        }

        return null;
    };

    $classLoader = null;
    $functions = spl_autoload_functions();
    while (null === $classLoader && $functions) {
        $classLoader = $getComposerClassLoader(array_shift($functions));
    }
    $getComposerClassLoader = null;

    if (null !== $classLoader) {
        $classLoader->addClassMap([ProxiedMethodReturnExpression::class => null]);
        $classLoader->loadClass(ProxiedMethodReturnExpression::class);
    }
}
