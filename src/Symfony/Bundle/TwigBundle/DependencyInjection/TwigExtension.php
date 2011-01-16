<?php

namespace Symfony\Bundle\TwigBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
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
    public function configLoad(array $config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('twig')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load('twig.xml');

            $this->addClassesToCompile(array(
                'Twig_Environment',
                'Twig_ExtensionInterface',
                'Twig_Extension',
                'Twig_Extension_Core',
                'Twig_Extension_Escaper',
                'Twig_Extension_Optimizer',
                'Twig_LoaderInterface',
                'Twig_Loader_Filesystem',
                'Twig_Markup',
                'Twig_TemplateInterface',
                'Twig_Template',
            ));
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
                if (isset($global['type']) && 'service' === $global['type']) {
                    $def->addMethodCall('addGlobal', array($global['key'], new Reference($global['id'])));
                } elseif (isset($global['value'])) {
                    $def->addMethodCall('addGlobal', array($global['key'], $global['value']));
                } else {
                    throw new \InvalidArgumentException(sprintf('Unable to understand global configuration (%s).', var_export($global, true)));
                }
            }
        } else {
            foreach ($globals as $key => $value) {
                if ('@' === substr($value, 0, 1)) {
                    $def->addMethodCall('addGlobal', array($key, new Reference(substr($value, 1))));
                } else {
                    $def->addMethodCall('addGlobal', array($key, $value));
                }
            }
        }
        unset($config['globals'], $config['global']);

        // extensions
        $extensions = $this->fixConfig($config, 'extension');
        if (isset($extensions[0]) && is_array($extensions[0])) {
            foreach ($extensions as $extension) {
                $container->getDefinition($extension['id'])->addTag('twig.extension');
            }
        } else {
            foreach ($extensions as $id) {
                $container->getDefinition($id)->addTag('twig.extension');
            }
        }
        unset($config['extensions'], $config['extension']);

        // convert - to _
        foreach ($config as $key => $value) {
            if (false !== strpos($key, '-')) {
                unset($config[$key]);
                $config[str_replace('-', '_', $key)] = $value;
            }
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
