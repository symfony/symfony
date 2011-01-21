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

    public function getClassesToCompile()
    {
        return $this->classes;
    }

    /**
     * Adds classes to be compiled when debug mode is not enabled.
     *
     * @param array $classes Classes to be compiled
     */
    protected function addClassesToCompile(array $classes)
    {
        $this->classes = array_merge($this->classes, $classes);
    }
}
