<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;

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
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class WebProfilerExtension extends Extension
{
    public function configLoad(array $configs, ContainerBuilder $container)
    {
        foreach ($configs as $config) {
            $this->doConfigLoad($config, $container);
        }
    }

    /**
     * Loads the web profiler configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function doConfigLoad(array $config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');

        if (isset($config['toolbar'])) {
            if ($config['toolbar']) {
                if (!$container->hasDefinition('debug.toolbar')) {
                    $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
                    $loader->load('toolbar.xml');
                }
            } elseif ($container->hasDefinition('debug.toolbar')) {
                $container->getDefinition('debug.toolbar')->clearTags();
            }
        }

        foreach (array('intercept-redirects', 'intercept_redirects') as $key) {
            if (isset($config[$key])) {
                $container->setParameter('debug.toolbar.intercept_redirects', (Boolean) $config[$key]);
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
        return 'http://www.symfony-project.org/schema/dic/webprofiler';
    }

    public function getAlias()
    {
        return 'webprofiler';
    }
}
