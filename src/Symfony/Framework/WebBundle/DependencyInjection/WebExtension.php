<?php

namespace Symfony\Framework\WebBundle\DependencyInjection;

use Symfony\Components\DependencyInjection\Loader\LoaderExtension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Reference;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * WebExtension.
 *
 * @package    Symfony
 * @subpackage Framework_WebBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class WebExtension extends LoaderExtension
{
    protected $resources = array(
        'templating' => 'templating.xml',
        'web'        => 'web.xml',
        'user'       => 'user.xml',
    );

    /**
     * Loads the web configuration.
     *
     * @param array                $config        A configuration array
     * @param BuilderConfiguration $configuration A BuilderConfiguration instance
     *
     * @return BuilderConfiguration A BuilderConfiguration instance
     */
    public function configLoad($config, BuilderConfiguration $configuration)
    {
        if (!$configuration->hasDefinition('controller_manager')) {
            $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
            $configuration->merge($loader->load($this->resources['web']));
        }

        if (isset($config['ide']) && 'textmate' === $config['ide']) {
            $configuration->setParameter('debug.file_link_format', 'txmt://open?url=file://%%f&line=%%l');
        }

        if (isset($config['toolbar']) && $config['toolbar']) {
            $config['profiler'] = true;
        }

        if (isset($config['profiler'])) {
            if ($config['profiler']) {
                if (!$configuration->hasDefinition('profiler')) {
                    $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
                    $configuration->merge($loader->load('profiling.xml'));
                }
            } elseif ($configuration->hasDefinition('profiler')) {
                $configuration->getDefinition('profiling')->clearAnnotations();
            }
        }

        // toolbar need to be registered after the profiler
        if (isset($config['toolbar'])) {
            if ($config['toolbar']) {
                if (!$configuration->hasDefinition('debug.toolbar')) {
                    $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
                    $configuration->merge($loader->load('toolbar.xml'));
                }
            } elseif ($configuration->hasDefinition('debug.toolbar')) {
                $configuration->getDefinition('debug.toolbar')->clearAnnotations();
            }
        }

        return $configuration;
    }

    /**
     * Loads the user configuration.
     *
     * @param array                $config        A configuration array
     * @param BuilderConfiguration $configuration A BuilderConfiguration instance
     *
     * @return BuilderConfiguration A BuilderConfiguration instance
     */
    public function userLoad($config, BuilderConfiguration $configuration)
    {
        if (!$configuration->hasDefinition('user')) {
            $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
            $configuration->merge($loader->load($this->resources['user']));
        }

        if (isset($config['default_locale'])) {
            $configuration->setParameter('user.default_locale', $config['default_locale']);
        }

        if (isset($config['class'])) {
            $configuration->setParameter('user.class', $config['class']);
        }

        foreach (array('name', 'auto_start', 'lifetime', 'path', 'domain', 'secure', 'httponly', 'cache_limiter', 'pdo.db_table') as $name) {
            if (isset($config['session'][$name])) {
                $configuration->setParameter('session.options.'.$name, $config['session'][$name]);
            }
        }

        if (isset($config['session']['class'])) {
            $class = $config['session']['class'];
            if (in_array($class, array('Native', 'Pdo'))) {
                $class = 'Symfony\\Framework\\WebBundle\\Session\\'.$class.'Session';
            }

            $configuration->setParameter('user.session', 'user.session.'.strtolower($class));
        }

        return $configuration;
    }

    /**
     * Loads the templating configuration.
     *
     * @param array $config A configuration array
     *
     * @return BuilderConfiguration A BuilderConfiguration instance
     */
    public function templatingLoad($config, BuilderConfiguration $configuration)
    {
        if (!$configuration->hasDefinition('templating')) {
            $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
            $configuration->merge($loader->load($this->resources['templating']));
        }

        if (array_key_exists('escaping', $config)) {
            $configuration->setParameter('templating.output_escaper', $config['escaping']);
        }

        if (array_key_exists('assets_version', $config)) {
            $configuration->setParameter('templating.assets.version', $config['assets_version']);
        }

        // path for the filesystem loader
        if (isset($config['path'])) {
            $configuration->setParameter('templating.loader.filesystem.path', $config['path']);
        }

        // loaders
        if (isset($config['loader'])) {
            $loaders = array();
            $ids = is_array($config['loader']) ? $config['loader'] : array($config['loader']);
            foreach ($ids as $id) {
                $loaders[] = new Reference($id);
            }

            if (1 === count($loaders)) {
                $configuration->setAlias('templating.loader', (string) $loaders[0]);
            } else {
                $configuration->getDefinition('templating.loader.chain')->addArgument($loaders);
                $configuration->setAlias('templating.loader', 'templating.loader.chain');
            }
        }

        // cache?
        if (isset($config['cache'])) {
            // wrap the loader with some cache
            $configuration->setDefinition('templating.loader.wrapped', $configuration->findDefinition('templating.loader'));
            $configuration->setDefinition('templating.loader', $configuration->getDefinition('templating.loader.cache'));
            $configuration->setParameter('templating.loader.cache.path', $config['cache']);
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
        return __DIR__.'/../Resources/config/';
    }

    public function getNamespace()
    {
        return 'http://www.symfony-project.org/schema/dic/symfony';
    }

    public function getAlias()
    {
        return 'web';
    }
}
