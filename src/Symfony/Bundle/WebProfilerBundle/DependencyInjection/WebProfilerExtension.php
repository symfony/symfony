<?php

namespace Symfony\Bundle\WebProfilerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * WebProfilerExtension.
 *
 * Usage:
 *
 *     <webprofiler:config
 *        toolbar="true"
 *        intercept-redirects="true"
 *    />
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class WebProfilerExtension extends Extension
{
    /**
     * Loads the web profiler configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function configLoad($config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');

        if (isset($config['toolbar'])) {
            if ($config['toolbar']) {
                if (!$container->hasDefinition('debug.toolbar')) {
                    $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
                    $loader->load('toolbar.xml');
                    $loader->load('web_profiler.xml');
                }
            } elseif ($container->hasDefinition('debug.toolbar')) {
                $container->getDefinition('debug.toolbar')->clearTags();
            }
        }

        if (isset($config['intercept-redirects'])) {
            $container->setParameter('debug.toolbar.intercept_redirects', (Boolean) $config['intercept-redirects']);
        } elseif (isset($config['intercept_redirects'])) {
            $container->setParameter('debug.toolbar.intercept_redirects', (Boolean) $config['intercept_redirects']);
        }
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://www.symfony-project.org/schema/dic/webprofiler';
    }

    public function getAlias()
    {
        return 'webprofiler';
    }
}
