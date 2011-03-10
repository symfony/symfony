<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\ZendBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;

/**
 * ZendExtension is an extension for the Zend Framework libraries.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->process($configuration->getConfigTree(), $configs);

        if (isset($config['logger'])) {
            $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
            $loader->load('logger.xml');
            $container->setAlias('logger', 'zend.logger');

            $config = $config['logger'];

            $container->setParameter('zend.logger.priority', is_int($config['priority']) ? $config['priority'] : constant('\\Zend\\Log\\Logger::'.strtoupper($config['priority'])));
            $container->setParameter('zend.logger.path', $config['path']);

            $definition = $container->findDefinition('zend.logger');
            if ($config['log_errors']) {
                $container->findDefinition('zend.logger')->addMethodCall('registerErrorHandler');
            } else {
                if ($definition->hasMethodCall('registerErrorHandler')) {
                    $container->findDefinition('zend.logger')->removeMethodCall('registerErrorHandler');
                }
            }

            $this->addClassesToCompile(array(
                'Zend\\Log\\Factory',
                'Zend\\Log\\Filter',
                'Zend\\Log\\Filter\\AbstractFilter',
                'Zend\\Log\\Filter\\Priority',
                'Zend\\Log\\Formatter',
                'Zend\\Log\\Formatter\\Simple',
                'Zend\\Log\\Logger',
                'Zend\\Log\\Writer',
                'Zend\\Log\\Writer\\AbstractWriter',
                'Zend\\Log\\Writer\\Stream',
                'Symfony\\Bundle\\ZendBundle\\Logger\\DebugLogger',
                'Symfony\\Bundle\\ZendBundle\\Logger\\Logger',
            ));
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
        return 'http://symfony.com/schema/dic/zend';
    }
}
