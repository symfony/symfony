<?php

namespace Symfony\Framework\DependencyInjection;

use Symfony\Components\DependencyInjection\Loader\LoaderExtension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\BuilderConfiguration;

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
 * @package    Symfony
 * @subpackage Framework
 * @author     Fabien Potencier <fabien.potencier@symfony-project.org>
 */
class KernelExtension extends LoaderExtension
{
    public function testLoad($config)
    {
        $configuration = new BuilderConfiguration();

        $loader = new XmlFileLoader(array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
        $configuration->merge($loader->load('test.xml'));
        $configuration->setParameter('kernel.include_core_classes', false);

        return $configuration;
    }

    /**
     * Loads the session configuration.
     *
     * @param array                $config        A configuration array
     * @param BuilderConfiguration $configuration A BuilderConfiguration instance
     *
     * @return BuilderConfiguration A BuilderConfiguration instance
     */
    public function sessionLoad($config, BuilderConfiguration $configuration)
    {
        if (!$configuration->hasDefinition('session')) {
            $loader = new XmlFileLoader(array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
            $configuration->merge($loader->load('session.xml'));
        }

        if (isset($config['default_locale'])) {
            $configuration->setParameter('session.default_locale', $config['default_locale']);
        }

        if (isset($config['class'])) {
            $configuration->setParameter('session.class', $config['class']);
        }

        foreach (array('name', 'auto_start', 'lifetime', 'path', 'domain', 'secure', 'httponly', 'cache_limiter', 'pdo.db_table') as $name) {
            if (isset($config['session'][$name])) {
                $configuration->setParameter('session.options.'.$name, $config['session'][$name]);
            }
        }

        if (isset($config['session']['class'])) {
            $class = $config['session']['class'];
            if (in_array($class, array('Native', 'Pdo'))) {
                $class = 'Symfony\\Framework\\FrameworkBundle\\SessionStorage\\'.$class.'SessionStorage';
            }

            $configuration->setParameter('session.session', 'session.session.'.strtolower($class));
        }

        return $configuration;
    }

    public function configLoad($config)
    {
        $configuration = new BuilderConfiguration();

        if (isset($config['charset'])) {
            $configuration->setParameter('kernel.charset', $config['charset']);
        }

        if (!array_key_exists('compilation', $config)) {
            $classes = array(
                'Symfony\\Components\\Routing\\Router',
                'Symfony\\Components\\Routing\\RouterInterface',
                'Symfony\\Components\\EventDispatcher\\Event',
                'Symfony\\Components\\Routing\\Matcher\\UrlMatcherInterface',
                'Symfony\\Components\\Routing\\Matcher\\UrlMatcher',
                'Symfony\\Components\\HttpKernel\\HttpKernel',
                'Symfony\\Components\\HttpKernel\\Request',
                'Symfony\\Components\\HttpKernel\\Response',
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
                'Symfony\\Framework\\FrameworkBundle\\RequestListener',
                'Symfony\\Framework\\FrameworkBundle\\Controller',
                'Symfony\\Framework\\FrameworkBundle\\Controller\\ControllerLoaderListener',
                'Symfony\\Framework\\FrameworkBundle\\Templating\\Engine',
            );
        } else {
            $classes = array();
            foreach (explode("\n", $config['compilation']) as $class) {
                if ($class) {
                    $classes[] = trim($class);
                }
            }
        }
        $configuration->setParameter('kernel.compiled_classes', $classes);

        if (array_key_exists('error_handler_level', $config)) {
            $configuration->setParameter('error_handler.level', $config['error_handler_level']);
        }

        return $configuration;
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
