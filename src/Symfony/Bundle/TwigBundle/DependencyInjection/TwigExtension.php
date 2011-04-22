<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * TwigExtension.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class TwigExtension extends Extension
{
    /**
     * Responds to the twig configuration parameter.
     *
     * @param array            $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('twig.xml');

        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);

        $container->setParameter('twig.form.resources', $config['form']['resources']);

        if (!empty($config['globals'])) {
            $def = $container->getDefinition('twig');
            foreach ($config['globals'] as $key => $global) {
                if (isset($global['type']) && 'service' === $global['type']) {
                    $def->addMethodCall('addGlobal', array($key, new Reference($global['id'])));
                } else {
                    $def->addMethodCall('addGlobal', array($key, $global['value']));
                }
            }
        }

        if (!empty($config['extensions'])) {
            foreach ($config['extensions'] as $id) {
                $container->getDefinition($id)->addTag('twig.extension');
            }
        }

        if ($config['cache_warmer']) {
            $container->getDefinition('twig.cache_warmer')->addTag('kernel.cache_warmer');
        }

        unset(
            $config['form'],
            $config['globals'],
            $config['extensions'],
            $config['cache_warmer']
        );

        $container->setParameter('twig.options', $config);

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
        return 'http://symfony.com/schema/dic/twig';
    }
}
