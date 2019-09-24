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

use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;

/**
 * Allow adding classes to the class cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Extension extends BaseExtension
{
    private $annotatedClasses = [];
    private $preloadedClasses = [];

    /**
     * Gets the annotated classes to cache.
     *
     * @return array An array of classes
     */
    public function getAnnotatedClassesToCompile()
    {
        return $this->annotatedClasses;
    }

    /**
     * Adds annotated classes to the class cache.
     *
     * @param array $annotatedClasses An array of class patterns
     */
    public function addAnnotatedClassesToCompile(array $annotatedClasses)
    {
        $this->annotatedClasses = array_merge($this->annotatedClasses, $annotatedClasses);
    }

    /**
     * Gets the classes to list in the preloading script.
     */
    public function getClassesToPreload(): array
    {
        return $this->preloadedClasses;
    }

    /**
     * Adds classes to list in the preloading script.
     *
     * When a class is listed, all its parent classes or interfaces are automatically listed too.
     * Service classes are also automatically preloaded and don't need to be listed explicitly.
     */
    public function addClassesToPreload(array $preloadedClasses): void
    {
        $this->preloadedClasses = array_merge($this->preloadedClasses, $preloadedClasses);
    }
}
