<?php

namespace Symfony\Framework\DependencyInjection;

use Symfony\Components\DependencyInjection\Extension\Extension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\ContainerBuilder;

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
    public function testLoad($config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
        $loader->load('test.xml');
        $container->setParameter('kernel.include_core_classes', false);

        return $container;
    }

    /**
     * Loads the session configuration.
     *
     * @param array                $config        A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @return ContainerBuilder A ContainerBuilder instance
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
                $class = 'Symfony\\Components\\HttpFoundation\\SessionStorage\\'.$class.'SessionStorage';
            }

            $container->setParameter('session.session', 'session.session.'.strtolower($class));
        }

        return $container;
    }

    public function configLoad($config, ContainerBuilder $container)
    {
        if (isset($config['charset'])) {
            $container->setParameter('kernel.charset', $config['charset']);
        }

        if (!array_key_exists('compilation', $config)) {
            $classes = array(
                'Symfony\\Components\\Routing\\RouterInterface',
                'Symfony\\Components\\Routing\\Router',
                'Symfony\\Components\\EventDispatcher\\Event',
                'Symfony\\Components\\Routing\\Matcher\\UrlMatcherInterface',
                'Symfony\\Components\\Routing\\Matcher\\UrlMatcher',
                'Symfony\\Components\\HttpKernel\\HttpKernel',
                'Symfony\\Components\\HttpFoundation\\Request',
                'Symfony\\Components\\HttpFoundation\\Response',
                'Symfony\\Components\\HttpKernel\\ResponseListener',
                'Symfony\\Components\\Templating\\Loader\\LoaderInterface',
                'Symfony\\Components\\Templating\\Loader\\Loader',
                'Symfony\\Components\\Templating\\Loader\\FilesystemLoader',
                'Symfony\\Components\\Templating\\Engine',
                'Symfony\\Components\\Templating\\Renderer\\RendererInterface',
                'Symfony\\Components\\Templating\\Renderer\\Renderer',
                'Symfony\\Components\\Templating\\Renderer\\PhpRenderer',
                'Symfony\\Components\\Templating\\Storage\\Storage',
                'Symfony\\Components\\Templating\\Storage\\FileStorage',
                'Symfony\\Bundle\\FrameworkBundle\\RequestListener',
                'Symfony\\Bundle\\FrameworkBundle\\Controller',
                'Symfony\\Bundle\\FrameworkBundle\\Templating\\Engine',
            );
        } else {
            $classes = array();
            foreach (explode("\n", $config['compilation']) as $class) {
                if ($class) {
                    $classes[] = trim($class);
                }
            }
        }
        $container->setParameter('kernel.compiled_classes', $classes);

        if (array_key_exists('error_handler_level', $config)) {
            $container->setParameter('error_handler.level', $config['error_handler_level']);
        }

        return $container;
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
