<?php

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Provides useful features shared by many extensions.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Extension extends BaseExtension
{
    protected $classes = array();
    protected $classMap = array();

    /**
     * Gets the classes to cache.
     *
     * @return array An array of classes
     */
    public function getClassesToCompile()
    {
        return $this->classes;
    }

    /**
     * Adds classes to the class cache.
     *
     * @param array $classes An array of classes
     */
    protected function addClassesToCompile(array $classes)
    {
        $this->classes = array_merge($this->classes, $classes);
    }

    /**
     * Gets the autoload class map.
     *
     * @return array An array of classes
     */
    public function getAutoloadClassMap()
    {
        return $this->classMap;
    }

    /**
     * Adds classes to the autoload class map.
     *
     * @param array $classes An array of classes
     */
    public function addClassesToAutoloadMap(array $classes)
    {
        $this->classMap = array_merge($this->classMap, $classes);
    }
}
