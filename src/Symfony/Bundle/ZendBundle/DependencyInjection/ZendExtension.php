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
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ZendExtension extends Extension
{
    protected $resources = array(
        'logger' => 'logger.xml',
        'i18n'   => 'i18n.xml',
    );

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
     * Loads the i18n configuration.
     *
     * Usage example:
     *
     *      <zend:i18n locale="en" adapter="xliff" data="/path/to/messages.xml" />
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function i18nLoad($config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('zend.i18n')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load($this->resources['i18n']);
            $container->setAlias('i18n', 'zend.i18n');
        }

        if (isset($config['locale'])) {
            $container->setParameter('zend.translator.locale', $config['locale']);
        }

        if (isset($config['adapter'])) {
            $container->setParameter('zend.translator.adapter', constant($config['adapter']));
        }

        if (isset($config['translations']) && is_array($config['translations'])) {
            foreach ($config['translations'] as $locale => $catalogue) {
                if ($locale == $container->getParameter('zend.translator.locale')) {
                  $container->setParameter('zend.translator.catalogue', $catalogue);
                }
                $container->findDefinition('zend.translator')->addMethodCall('addTranslation', array($catalogue, $locale));
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
