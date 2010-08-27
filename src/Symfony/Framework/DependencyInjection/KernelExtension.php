<?php

namespace Symfony\Framework\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * KernelExtension.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.org>
 */
class KernelExtension extends Extension
{
    /**
     * Loads the test configuration.
     *
     * @param array            $config    A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function testLoad($config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
        $loader->load('test.xml');
    }

    /**
     * Loads the session configuration.
     *
     * @param array            $config    A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function sessionLoad($config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('session')) {
            $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
            $loader->load('session.xml');
        }

        if (isset($config['default_locale'])) {
            $container->setParameter('session.default_locale', $config['default_locale']);
        }

        if (isset($config['class'])) {
            $container->setParameter('session.class', $config['class']);
        }

        foreach (array('name', 'lifetime', 'path', 'domain', 'secure', 'httponly', 'cache_limiter', 'pdo.db_table') as $name) {
            if (isset($config['session'][$name])) {
                $container->setParameter('session.options.'.$name, $config['session'][$name]);
            }
        }

        if (isset($config['session']['class'])) {
            $class = $config['session']['class'];
            if (in_array($class, array('Native', 'Pdo'))) {
                $class = 'Symfony\\Component\\HttpFoundation\\SessionStorage\\'.$class.'SessionStorage';
            }

            $container->setParameter('session.session', 'session.session.'.strtolower($class));
        }
    }

    /**
     * Loads the config configuration.
     *
     * @param array            $config    A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function configLoad($config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('event_dispatcher')) {
            $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
            $loader->load('services.xml');

            if ($container->getParameter('kernel.debug')) {
                $loader->load('debug.xml');
                $container->setDefinition('event_dispatcher', $container->findDefinition('debug.event_dispatcher'));
                $container->setAlias('debug.event_dispatcher', 'event_dispatcher');
            }
        }

        if (isset($config['charset'])) {
            $container->setParameter('kernel.charset', $config['charset']);
        }

        if (array_key_exists('error_handler', $config)) {
            if (false === $config['error_handler']) {
                $container->getDefinition('error_handler')->setMethodCalls(array());
            } else {
                $container->getDefinition('error_handler')->addMethodCall('register', array());
                $container->setParameter('error_handler.level', $config['error_handler']);
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
        return false;
    }

    public function getNamespace()
    {
        return 'http://www.symfony-project.org/schema/dic/symfony/kernel';
    }

    public function getAlias()
    {
        return 'kernel';
    }
}
