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
    private $classes = array();
    private $annotatedClasses = array();

    /**
     * Gets the classes to cache.
     *
     * @return array An array of classes
     *
     * @deprecated since version 3.3, to be removed in 4.0.
     */
    public function getClassesToCompile()
    {
        if (\PHP_VERSION_ID >= 70000) {
            @trigger_error(__METHOD__.'() is deprecated since Symfony 3.3, to be removed in 4.0.', E_USER_DEPRECATED);
        }

        return $this->classes;
    }

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
     * Adds classes to the class cache.
     *
     * @param array $classes An array of class patterns
     *
     * @deprecated since version 3.3, to be removed in 4.0.
     */
    public function addClassesToCompile(array $classes)
    {
        if (\PHP_VERSION_ID >= 70000) {
            @trigger_error(__METHOD__.'() is deprecated since Symfony 3.3, to be removed in 4.0.', E_USER_DEPRECATED);
        }

        $this->classes = array_merge($this->classes, $classes);
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
}
