<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;

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
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class WebExtension extends Extension
{
    protected $resources = array(
        'templating' => 'templating.xml',
        'web'        => 'web.xml',
        'routing'    => 'routing.xml',
        // validation.xml conflicts with the naming convention for XML
        // validation mapping files, so call it validator.xml
        'validation' => 'validator.xml',
    );

    /**
     * Loads the web configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function configLoad($config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');

        if (!$container->hasDefinition('controller_manager')) {
            $loader->load($this->resources['web']);
        }

        if (isset($config['ide']) && 'textmate' === $config['ide']) {
            $container->setParameter('debug.file_link_format', 'txmt://open?url=file://%%f&line=%%l');
        }

        foreach (array('csrf_secret', 'csrf-secret') as $key) {
            if (isset($config[$key])) {
                $container->setParameter('csrf_secret', $config[$key]);
            }
        }

        if (isset($config['router'])) {
            if (!$container->hasDefinition('router')) {
                $loader->load($this->resources['routing']);
            }

            $container->setParameter('routing.resource', $config['router']['resource']);

            $this->addCompiledClasses($container, array(
                'Symfony\\Component\\Routing\\RouterInterface',
                'Symfony\\Component\\Routing\\Router',
                'Symfony\\Component\\Routing\\Matcher\\UrlMatcherInterface',
                'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
                'Symfony\\Component\\Routing\\Generator\\UrlGeneratorInterface',
                'Symfony\\Component\\Routing\\Generator\\UrlGenerator',
                'Symfony\\Component\\Routing\\Loader\\Loader',
                'Symfony\\Component\\Routing\\Loader\\DelegatingLoader',
                'Symfony\\Component\\Routing\\Loader\\LoaderResolver',
                'Symfony\\Bundle\\FrameworkBundle\\Routing\\LoaderResolver',
                'Symfony\\Bundle\\FrameworkBundle\\Routing\\DelegatingLoader',
            ));
        }

        if (isset($config['profiler'])) {
            $this->registerProfilerConfiguration($config, $container);
        }

        if (isset($config['validation']['enabled'])) {
            $this->registerValidationConfiguration($config, $container);
        }

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

        foreach (array('error_handler', 'error-handler') as $key) {
            if (array_key_exists($key, $config)) {
                if (false === $config[$key]) {
                    $container->getDefinition('error_handler')->setMethodCalls(array());
                } else {
                    $container->getDefinition('error_handler')->addMethodCall('register', array());
                    $container->setParameter('error_handler.level', $config[$key]);
                }
            }
        }

        $this->addCompiledClasses($container, array(
            'Symfony\\Component\\HttpFoundation\\ParameterBag',
            'Symfony\\Component\\HttpFoundation\\HeaderBag',
            'Symfony\\Component\\HttpFoundation\\Request',
            'Symfony\\Component\\HttpFoundation\\Response',

            'Symfony\\Component\\HttpKernel\\HttpKernel',
            'Symfony\\Component\\HttpKernel\\ResponseListener',
            'Symfony\\Component\\HttpKernel\\Controller\\ControllerResolver',

            'Symfony\\Bundle\\FrameworkBundle\\RequestListener',
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\ControllerNameConverter',
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\ControllerResolver',

            'Symfony\\Component\\EventDispatcher\\Event',

            'Symfony\\Bundle\\FrameworkBundle\\Controller\\ControllerInterface',
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\BaseController',
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\Controller',
        ));
    }

    /**
     * Loads the templating configuration.
     *
     * @param array            $config        An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function templatingLoad($config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('templating')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load($this->resources['templating']);

            if ($container->getParameter('kernel.debug')) {
                $loader->load('templating_debug.xml');
            }
        }

        if (array_key_exists('escaping', $config)) {
            $container->setParameter('templating.output_escaper', $config['escaping']);
        }

        if (array_key_exists('assets_version', $config)) {
            $container->setParameter('templating.assets.version', $config['assets_version']);
        }

        if (array_key_exists('assets_base_urls', $config)) {
            $container->setParameter('templating.assets.base_urls', $config['assets_base_urls']);
        }

        // template paths
        $dirs = array('%kernel.root_dir%/views/%%bundle%%/%%controller%%/%%name%%%%format%%.%%renderer%%');
        foreach ($container->getParameter('kernel.bundle_dirs') as $dir) {
            $dirs[] = $dir.'/%%bundle%%/Resources/views/%%controller%%/%%name%%%%format%%.%%renderer%%';
        }
        $container->setParameter('templating.loader.filesystem.path', $dirs);

        // path for the filesystem loader
        if (isset($config['path'])) {
            $container->setParameter('templating.loader.filesystem.path', $config['path']);
        }

        // loaders
        if (isset($config['loader'])) {
            $loaders = array();
            $ids = is_array($config['loader']) ? $config['loader'] : array($config['loader']);
            foreach ($ids as $id) {
                $loaders[] = new Reference($id);
            }

            if (1 === count($loaders)) {
                $container->setAlias('templating.loader', (string) $loaders[0]);
            } else {
                $container->getDefinition('templating.loader.chain')->addArgument($loaders);
                $container->setAlias('templating.loader', 'templating.loader.chain');
            }
        }

        // cache?
        $container->setParameter('templating.loader.cache.path', null);
        if (isset($config['cache'])) {
            // wrap the loader with some cache
            $container->setDefinition('templating.loader.wrapped', $container->findDefinition('templating.loader'));
            $container->setDefinition('templating.loader', $container->getDefinition('templating.loader.cache'));
            $container->setParameter('templating.loader.cache.path', $config['cache']);
        }

        // compilation
        $this->addCompiledClasses($container, array(
            'Symfony\\Component\\Templating\\Loader\\LoaderInterface',
            'Symfony\\Component\\Templating\\Loader\\Loader',
            'Symfony\\Component\\Templating\\Loader\\FilesystemLoader',
            'Symfony\\Component\\Templating\\Engine',
            'Symfony\\Component\\Templating\\Renderer\\RendererInterface',
            'Symfony\\Component\\Templating\\Renderer\\Renderer',
            'Symfony\\Component\\Templating\\Renderer\\PhpRenderer',
            'Symfony\\Component\\Templating\\Storage\\Storage',
            'Symfony\\Component\\Templating\\Storage\\FileStorage',
            'Symfony\\Bundle\\FrameworkBundle\\Templating\\Engine',
            'Symfony\\Component\\Templating\\Helper\\Helper',
            'Symfony\\Component\\Templating\\Helper\\SlotsHelper',
            'Symfony\\Bundle\\FrameworkBundle\\Templating\\Helper\\ActionsHelper',
            'Symfony\\Bundle\\FrameworkBundle\\Templating\\Helper\\RouterHelper',
            'Symfony\\Bundle\\FrameworkBundle\\Templating\\Helper\\RouterHelper',
        ));
    }

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

    /*
        <profiler only-exceptions="false">
            <matcher ip="192.168.0.0/24" path="#/admin/#i" />
            <matcher>
                <service class="MyMatcher" />
            </matcher>
            <matcher service="my_matcher" />
        </profiler>
    */
    protected function registerProfilerConfiguration($config, ContainerBuilder $container)
    {
        if ($config['profiler']) {
            if (!$container->hasDefinition('profiler')) {
                $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
                $loader->load('profiling.xml');
                $loader->load('collectors.xml');
            }

            if (isset($config['profiler']['only-exceptions'])) {
                $container->setParameter('profiler_listener.only_exceptions', $config['profiler']['only-exceptions']);
            } elseif (isset($config['profiler']['only_exceptions'])) {
                $container->setParameter('profiler_listener.only_exceptions', $config['profiler']['only_exceptions']);
            }

            if (isset($config['profiler']['matcher'])) {
                if (isset($config['profiler']['matcher']['service'])) {
                    $container->setAlias('profiler.request_matcher', $config['profiler']['matcher']['service']);
                } elseif (isset($config['profiler']['matcher']['_services'])) {
                    $container->setAlias('profiler.request_matcher', (string) $config['profiler']['matcher']['_services'][0]);
                } else {
                    $definition = $container->register('profiler.request_matcher', 'Symfony\\Component\\HttpFoundation\\RequestMatcher');

                    if (isset($config['profiler']['matcher']['ip'])) {
                        $definition->addMethodCall('matchIp', array($config['profiler']['matcher']['ip']));
                    }

                    if (isset($config['profiler']['matcher']['path'])) {
                        $definition->addMethodCall('matchPath', array($config['profiler']['matcher']['path']));
                    }
                }
            } else {
                $container->removeAlias('profiler.request_matcher');
            }
        } elseif ($container->hasDefinition('profiler')) {
            $container->getDefinition('profiling')->clearTags();
        }
    }

    protected function registerValidationConfiguration($config, ContainerBuilder $container)
    {
        if ($config['validation']['enabled']) {
            if (!$container->hasDefinition('validator')) {
                $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
                $loader->load($this->resources['validation']);
            }

            $xmlMappingFiles = array();
            $yamlMappingFiles = array();
            $messageFiles = array();

            // default entries by the framework
            $xmlMappingFiles[] = __DIR__.'/../../../Component/Form/Resources/config/validation.xml';
            $messageFiles[] = __DIR__ . '/../../../Component/Validator/Resources/i18n/messages.en.xml';
            $messageFiles[] = __DIR__ . '/../../../Component/Form/Resources/i18n/messages.en.xml';

            foreach ($container->getParameter('kernel.bundles') as $className) {
                $tmp = dirname(str_replace('\\', '/', $className));
                $namespace = str_replace('/', '\\', dirname($tmp));
                $bundle = basename($tmp);

                foreach ($container->getParameter('kernel.bundle_dirs') as $dir) {
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
                $container->getParameter('validator.mapping.loader.xml_files_loader.class'),
                array($xmlMappingFiles)
            );

            $yamlFilesLoader = new Definition(
                $container->getParameter('validator.mapping.loader.yaml_files_loader.class'),
                array($yamlMappingFiles)
            );

            $container->setDefinition('validator.mapping.loader.xml_files_loader', $xmlFilesLoader);
            $container->setDefinition('validator.mapping.loader.yaml_files_loader', $yamlFilesLoader);
            $container->setParameter('validator.message_interpolator.files', $messageFiles);

            foreach ($xmlMappingFiles as $file) {
                $container->addResource(new FileResource($file));
            }

            foreach ($yamlMappingFiles as $file) {
                $container->addResource(new FileResource($file));
            }

            foreach ($messageFiles as $file) {
                $container->addResource(new FileResource($file));
            }

            if (isset($config['validation']['annotations']) && $config['validation']['annotations'] === true) {
                $annotationLoader = new Definition($container->getParameter('validator.mapping.loader.annotation_loader.class'));
                $container->setDefinition('validator.mapping.loader.annotation_loader', $annotationLoader);

                $loader = $container->getDefinition('validator.mapping.loader.loader_chain');
                $arguments = $loader->getArguments();
                array_unshift($arguments[0], new Reference('validator.mapping.loader.annotation_loader'));
                $loader->setArguments($arguments);
            }
        } elseif ($container->hasDefinition('validator')) {
            $container->getDefinition('validator')->clearTags();
        }
    }

    protected function addCompiledClasses($container, array $classes)
    {
        $container->setParameter('kernel.compiled_classes', array_merge($container->getParameter('kernel.compiled_classes'), $classes));
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
        return 'http://www.symfony-project.org/schema/dic/symfony';
    }

    public function getAlias()
    {
        return 'web';
    }
}
