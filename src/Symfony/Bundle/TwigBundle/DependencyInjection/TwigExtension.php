<?php

namespace Symfony\Bundle\TwigBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * TwigExtension.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TwigExtension extends Extension
{
    /**
     * Loads the Twig configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function configLoad($config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('twig')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load('twig.xml');
        }

        // form resources
        foreach (array('resources', 'resource') as $key) {
            if (isset($config['form'][$key])) {
                $resources = (array) $config['form'][$key];
                $container->setParameter('twig.form.resources', array_merge($container->getParameter('twig.form.resources'), $resources));
                unset($config['form'][$key]);
            }
        }

        // globals
        $def = $container->getDefinition('twig');
        $globals = $this->fixConfig($config, 'global');
        if (isset($globals[0])) {
            foreach ($globals as $global) {
                $def->addMethodCall('addGlobal', array($global['key'], new Reference($global['id'])));
            }
        } else {
            foreach ($globals as $key => $id) {
                $def->addMethodCall('addGlobal', array($key, new Reference($id)));
            }
        }
        unset($config['globals'], $config['global']);

        // convert - to _
        foreach ($config as $key => $value) {
            $config[str_replace('-', '_', $key)] = $value;
        }

        $container->setParameter('twig.options', array_replace($container->getParameter('twig.options'), $config));
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
        return 'http://www.symfony-project.org/schema/dic/twig';
    }

    public function getAlias()
    {
        return 'twig';
    }

    protected function fixConfig($config, $key)
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
