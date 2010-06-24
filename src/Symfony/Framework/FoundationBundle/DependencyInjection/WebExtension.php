<?php

namespace Symfony\Framework\FoundationBundle\DependencyInjection;

use Symfony\Components\DependencyInjection\Loader\LoaderExtension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\FileResource;

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
 * @subpackage Framework_FoundationBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class WebExtension extends LoaderExtension
{
    protected $resources = array(
        'templating' => 'templating.xml',
        'web'        => 'web.xml',
        'user'       => 'user.xml',
        // validation.xml conflicts with the naming convention for XML
        // validation mapping files, so call it validator.xml
        'validation' => 'validator.xml',
    );

    protected $bundleDirs = array();
    protected $bundles = array();

    public function __construct(array $bundleDirs, array $bundles)
    {
        $this->bundleDirs = $bundleDirs;
        $this->bundles = $bundles;
    }

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

        if (isset($config['validation'])) {
            if ($config['validation']) {
                if (!$configuration->hasDefinition('validator')) {
                    $loader = new XmlFileLoader(__DIR__.'/../Resources/config');
                    $configuration->merge($loader->load($this->resources['validation']));
                }

                $xmlMappingFiles = array();
                $yamlMappingFiles = array();
                $messageFiles = array();

                // default entries by the framework
                $xmlMappingFiles[] = __DIR__.'/../../../Components/Form/Resources/config/validation.xml';
                $messageFiles[] = __DIR__ . '/../../../Components/Validator/Resources/i18n/messages.en.xml';
                $messageFiles[] = __DIR__ . '/../../../Components/Form/Resources/i18n/messages.en.xml';

                foreach ($this->bundles as $className) {
                    $tmp = dirname(str_replace('\\', '/', $className));
                    $namespace = str_replace('/', '\\', dirname($tmp));
                    $bundle = basename($tmp);

                    foreach ($this->bundleDirs as $dir) {
                        if (file_exists($file = $dir.'/'.$bundle.'/Resources/config/validation.xml')) {
                            $xmlMappingFiles[] = realpath($file);
                        }
                        if (file_exists($file = $dir.'/'.$bundle.'/Resources/config/validation.yml')) {
                            $yamlMappingFiles[] = realpath($file);
                        }

                        // TODO do we really want the message files of all cultures?
                        foreach (glob($dir.'/'.$bundle.'/Resources/i18n/messages.*.xml') as $file) {
                            $messageFiles[] = realpath($file);
                        }
                    }
                }

                $xmlFilesLoader = new Definition(
                    $configuration->getParameter('validator.mapping.loader.xml_files_loader.class'),
                    array($xmlMappingFiles)
                );

                $yamlFilesLoader = new Definition(
                    $configuration->getParameter('validator.mapping.loader.yaml_files_loader.class'),
                    array($yamlMappingFiles)
                );

                $configuration->setDefinition('validator.mapping.loader.xml_files_loader', $xmlFilesLoader);
                $configuration->setDefinition('validator.mapping.loader.yaml_files_loader', $yamlFilesLoader);
                $configuration->setParameter('validator.message_interpolator.files', $messageFiles);

                foreach ($xmlMappingFiles as $file) {
                    $configuration->addResource(new FileResource($file));
                }

                foreach ($yamlMappingFiles as $file) {
                    $configuration->addResource(new FileResource($file));
                }

                foreach ($messageFiles as $file) {
                    $configuration->addResource(new FileResource($file));
                }

                if (isset($config['validation']['annotations']) && $config['validation']['annotations'] === true) {
                    $annotationLoader = new Definition($configuration->getParameter('validator.mapping.loader.annotation_loader.class'));
                    $configuration->setDefinition('validator.mapping.loader.annotation_loader', $annotationLoader);

                    $loader = $configuration->getDefinition('validator.mapping.loader.loader_chain');
                    $arguments = $loader->getArguments();
                    array_unshift($arguments, new Reference('validator.mapping.loader.annotation_loader'));
                    $loader->setArguments($arguments);
                }
            } elseif ($configuration->hasDefinition('validator')) {
                $configuration->getDefinition('validator')->clearAnnotations();
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
                $class = 'Symfony\\Framework\\FoundationBundle\\Session\\'.$class.'Session';
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
