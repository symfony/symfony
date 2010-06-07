<?php

namespace Symfony\Framework\ProfilerBundle\DependencyInjection;

use Symfony\Components\DependencyInjection\Loader\LoaderExtension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\BuilderConfiguration;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ProfilerExtension manages the data collectors and the web debug toolbar.
 *
 * @package    Symfony
 * @subpackage Framework_ProfilerBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ProfilerExtension extends LoaderExtension
{
    public function configLoad($config, BuilderConfiguration $configuration)
    {
        if (!$configuration->hasDefinition('data_collector_manager')) {
            $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
            $configuration->merge($loader->load('collectors.xml'));
        }

        if (isset($config['toolbar']) && $config['toolbar'] && !$configuration->hasDefinition('debug.toolbar')) {
            $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
            $configuration->merge($loader->load('toolbar.xml'));
        }

        return $configuration;
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/';
    }

    public function getNamespace()
    {
        return 'http://www.symfony-project.org/schema/dic/symfony/profiler';
    }

    public function getAlias()
    {
        return 'profiler';
    }
}
