<?php

namespace Symfony\Bundle\TwigBundle\DependencyInjection;

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
 * TwigExtension.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
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
