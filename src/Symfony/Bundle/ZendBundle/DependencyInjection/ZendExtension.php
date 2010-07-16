<?php

namespace Symfony\Bundle\ZendBundle\DependencyInjection;

use Symfony\Components\DependencyInjection\Extension\Extension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\ContainerBuilder;

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
 * @package    Symfony
 * @subpackage Bundle_ZendBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ZendExtension extends Extension
{
    protected $resources = array(
        'logger' => 'logger.xml',
    );

    /**
     * Loads the logger configuration.
     *
     * Usage example:
     *
     *      <zend:logger priority="info" path="/path/to/some.log" />
     *
     * @param array                                                        $config        An array of configuration settings
     * @param \Symfony\Components\DependencyInjection\ContainerBuilder $container A ContainerBuilder instance
     */
    public function loggerLoad($config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('zend.logger')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load($this->resources['logger']);
            $container->setAlias('logger', 'zend.logger');
        }

        if (isset($config['priority'])) {
            $container->setParameter('zend.logger.priority', is_int($config['priority']) ? $config['priority'] : constant('\\Zend\\Log\\Logger::'.strtoupper($config['priority'])));
        }

        if (isset($config['path'])) {
            $container->setParameter('zend.logger.path', $config['path']);
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
