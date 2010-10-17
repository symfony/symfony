<?php

namespace Symfony\Bundle\ZendBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
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
 * ZendExtension is an extension for the Zend Framework libraries.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ZendExtension extends Extension
{
    /**
     * Loads the Zend Framework configuration.
     *
     * Usage example:
     *
     *      <zend:config>
     *          <zend:logger priority="info" path="/path/to/some.log" />
     *      </zend:config>
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function configLoad($config, ContainerBuilder $container)
    {
        if (isset($config['logger'])) {
            $this->registerLoggerConfiguration($config, $container);
        }
    }

    /**
     * Loads the logger configuration.
     *
     * Usage example:
     *
     *      <zend:logger priority="info" path="/path/to/some.log" />
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function registerLoggerConfiguration($config, ContainerBuilder $container)
    {
        $config = $config['logger'];

        if (!$container->hasDefinition('zend.logger')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load('logger.xml');
            $container->setAlias('logger', 'zend.logger');
        }

        if (isset($config['priority'])) {
            $container->setParameter('zend.logger.priority', is_int($config['priority']) ? $config['priority'] : constant('\\Zend\\Log\\Logger::'.strtoupper($config['priority'])));
        }

        if (isset($config['path'])) {
            $container->setParameter('zend.logger.path', $config['path']);
        }

        if (isset($config['log_errors'])) {
            $definition = $container->findDefinition('zend.logger');
            if (false === $config['log_errors'] && $definition->hasMethodCall('registerErrorHandler')) {
                $container->findDefinition('zend.logger')->removeMethodCall('registerErrorHandler');
            }
            else {
                $container->findDefinition('zend.logger')->addMethodCall('registerErrorHandler');
            }
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
        return 'http://www.symfony-project.org/schema/dic/zend';
    }

    public function getAlias()
    {
        return 'zend';
    }
}
