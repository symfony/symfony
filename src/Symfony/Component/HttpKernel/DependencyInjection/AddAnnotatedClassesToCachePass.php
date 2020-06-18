<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Composer\Autoload\ClassLoader;
use Symfony\Component\Debug\DebugClassLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Sets the classes to compile in the cache for the container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AddAnnotatedClassesToCachePass implements CompilerPassInterface
{
    private $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $classes = [];
        $annotatedClasses = [];
        foreach ($container->getExtensions() as $extension) {
            if ($extension instanceof Extension) {
                if (\PHP_VERSION_ID < 70000) {
                    $classes = array_merge($classes, $extension->getClassesToCompile());
                }
                $annotatedClasses = array_merge($annotatedClasses, $extension->getAnnotatedClassesToCompile());
            }
        }

        $existingClasses = $this->getClassesInComposerClassMaps();

        if (\PHP_VERSION_ID < 70000) {
            $classes = $container->getParameterBag()->resolveValue($classes);
            $this->kernel->setClassCache($this->expandClasses($classes, $existingClasses));
        }
        $annotatedClasses = $container->getParameterBag()->resolveValue($annotatedClasses);
        $this->kernel->setAnnotatedClassCache($this->expandClasses($annotatedClasses, $existingClasses));
    }

    /**
     * Expands the given class patterns using a list of existing classes.
     *
     * @param array $patterns The class patterns to expand
     * @param array $classes  The existing classes to match against the patterns
     *
     * @return array A list of classes derived from the patterns
     */
    private function expandClasses(array $patterns, array $classes)
    {
        $expanded = [];

        // Explicit classes declared in the patterns are returned directly
        foreach ($patterns as $key => $pattern) {
            if ('\\' !== substr($pattern, -1) && false === strpos($pattern, '*')) {
                unset($patterns[$key]);
                $expanded[] = ltrim($pattern, '\\');
            }
        }

        // Match patterns with the classes list
        $regexps = $this->patternsToRegexps($patterns);

        foreach ($classes as $class) {
            $class = ltrim($class, '\\');

            if ($this->matchAnyRegexps($class, $regexps)) {
                $expanded[] = $class;
            }
        }

        return array_unique($expanded);
    }

    private function getClassesInComposerClassMaps()
    {
        $classes = [];

        foreach (spl_autoload_functions() as $function) {
            if (!\is_array($function)) {
                continue;
            }

            if ($function[0] instanceof DebugClassLoader) {
                $function = $function[0]->getClassLoader();
            }

            if (\is_array($function) && $function[0] instanceof ClassLoader) {
                $classes += array_filter($function[0]->getClassMap());
            }
        }

        return array_keys($classes);
    }

    private function patternsToRegexps($patterns)
    {
        $regexps = [];

        foreach ($patterns as $pattern) {
            // Escape user input
            $regex = preg_quote(ltrim($pattern, '\\'));

            // Wildcards * and **
            $regex = strtr($regex, ['\\*\\*' => '.*?', '\\*' => '[^\\\\]*?']);

            // If this class does not end by a slash, anchor the end
            if ('\\' !== substr($regex, -1)) {
                $regex .= '$';
            }

            $regexps[] = '{^\\\\'.$regex.'}';
        }

        return $regexps;
    }

    private function matchAnyRegexps($class, $regexps)
    {
        $isTest = false !== strpos($class, 'Test');

        foreach ($regexps as $regex) {
            if ($isTest && false === strpos($regex, 'Test')) {
                continue;
            }

            if (preg_match($regex, '\\'.$class)) {
                return true;
            }
        }

        return false;
    }
}
