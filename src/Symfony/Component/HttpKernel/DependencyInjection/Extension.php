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

    /**
     * Normalizes a configuration entry.
     *
     * This method returns a normalize configuration array for a given key
     * to remove the differences due to the original format (YAML and XML mainly).
     *
     * Here is an example.
     *
     * The configuration is XML:
     *
     * <twig:extension id="twig.extension.foo" />
     * <twig:extension id="twig.extension.bar" />
     *
     * And the same configuration in YAML:
     *
     * twig.extensions: ['twig.extension.foo', 'twig.extension.bar']
     *
     * @param array A config array
     * @param key   The key to normalize
     */
    protected function normalizeConfig($config, $key)
    {
        $values = array();
        if (isset($config[$key.'s'])) {
            $values = $config[$key.'s'];
        } elseif (isset($config[$key])) {
            if (is_string($config[$key]) || !is_int(key($config[$key]))) {
                // only one
                $values = array($config[$key]);
            } else {
                $values = $config[$key];
            }
        }

        return $values;
    }
}
