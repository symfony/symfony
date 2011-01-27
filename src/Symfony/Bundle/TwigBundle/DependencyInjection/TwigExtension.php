<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * TwigExtension.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TwigExtension extends Extension
{
    public function configLoad(array $configs, ContainerBuilder $container)
    {
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
            'Twig_Markup',
            'Twig_TemplateInterface',
            'Twig_Template',
        ));

        foreach ($configs as $config) {
            $this->doConfigLoad($config, $container);
        }
    }

    /**
     * Loads the Twig configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function doConfigLoad(array $config, ContainerBuilder $container)
    {
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
        $globals = $this->normalizeConfig($config, 'global');
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
                if (is_string($value) && '@' === substr($value, 0, 1)) {
                    $def->addMethodCall('addGlobal', array($key, new Reference(substr($value, 1))));
                } else {
                    $def->addMethodCall('addGlobal', array($key, $value));
                }
            }
        }
        unset($config['globals'], $config['global']);

        // extensions
        $extensions = $this->normalizeConfig($config, 'extension');
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

        if (isset($config['cache-warmer'])) {
            $config['cache_warmer'] = $config['cache-warmer'];
        }

        if (isset($config['cache_warmer']) && $config['cache_warmer']) {
            $container->getDefinition('templating.cache_warmer.templates_cache')->addTag('kernel.cache_warmer');
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
}
