<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Form\FormContext;

/**
 * FrameworkExtension.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FrameworkExtension extends Extension
{
    public function configLoad(array $configs, ContainerBuilder $container)
    {
        foreach ($configs as $config) {
            $this->doConfigLoad($config, $container);
        }
    }

    /**
     * Loads the web configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function doConfigLoad(array $config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');

        if (!$container->hasDefinition('controller_resolver')) {
            $loader->load('web.xml');
        }

        if (!$container->hasDefinition('form.factory')) {
            $loader->load('form.xml');
        }

        if (isset($config['csrf-protection'])) {
            $config['csrf_protection'] = $config['csrf-protection'];
        }

        if (isset($config['csrf_protection'])) {
            foreach (array('enabled', 'field_name', 'field-name', 'secret') as $key) {
                if (isset($config['csrf_protection'][$key])) {
                    $container->setParameter('form.csrf_protection.'.strtr($key, '-', '_'),
                            $config['csrf_protection'][$key]);
                }
            }
        }

        if (isset($config['i18n']) && $config['i18n']) {
            FormContext::setLocale(\Locale::get());
        }

        if (isset($config['ide'])) {
            switch ($config['ide']) {
                case 'textmate':
                    $pattern = 'txmt://open?url=file://%%f&line=%%l';
                    break;

                case 'macvim':
                    $pattern = 'mvim://open?url=file://%%f&line=%%l';
                    break;

                default:
                    // should be the link pattern then
                    $pattern = $config['ide'];
            }

            $container->setParameter('debug.file_link_format', $pattern);
        }

        foreach (array('document_root', 'document-root') as $key) {
            if (isset($config[$key])) {
                $container->setParameter('document_root', $config[$key]);
            }
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

        if (isset($config['router'])) {
            $this->registerRouterConfiguration($config, $container);
        }

        if (isset($config['profiler'])) {
            $this->registerProfilerConfiguration($config, $container);
        }

        if (isset($config['validation']['enabled'])) {
            $this->registerValidationConfiguration($config, $container);
        }

        if (array_key_exists('templating', $config)) {
            $this->registerTemplatingConfiguration($config, $container);
        }

        if (array_key_exists('test', $config)) {
            $this->registerTestConfiguration($config, $container);
        }

        if (array_key_exists('param_converter', $config) || array_key_exists('param-converter', $config)) {
            $this->registerParamConverterConfiguration($config, $container);
        }

        if (array_key_exists('session', $config)) {
            $this->registerSessionConfiguration($config, $container);
        }

        // translator must always be registered (as support is included by default for forms for instance)
        // if you disable it, an identity translator will be used and everything will still work as expected
        $this->registerTranslatorConfiguration($config, $container);

        if (array_key_exists('esi', $config)) {
            $this->registerEsiConfiguration($config, $container);
        }

        if (isset($config['cache-warmer'])) {
            $config['cache_warmer'] = $config['cache-warmer'];
        }

        $warmer = isset($config['cache_warmer']) ? $config['cache_warmer'] : !$container->getParameter('kernel.debug');
        $container->setParameter('kernel.cache_warmup', $warmer);

        $this->addClassesToCompile(array(
            'Symfony\\Component\\HttpFoundation\\ParameterBag',
            'Symfony\\Component\\HttpFoundation\\HeaderBag',
            'Symfony\\Component\\HttpFoundation\\Request',
            'Symfony\\Component\\HttpFoundation\\Response',
            'Symfony\\Component\\HttpFoundation\\ResponseHeaderBag',

            'Symfony\\Component\\HttpKernel\\HttpKernel',
            'Symfony\\Component\\HttpKernel\\ResponseListener',
            'Symfony\\Component\\HttpKernel\\Controller\\ControllerResolver',
            'Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface',

            'Symfony\\Bundle\\FrameworkBundle\\RequestListener',
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\ControllerNameParser',
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\ControllerResolver',
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\Controller',

            'Symfony\\Component\\EventDispatcher\\Event',
            'Symfony\\Component\\EventDispatcher\\EventDispatcher',
            'Symfony\\Bundle\\FrameworkBundle\\EventDispatcher',

            'Symfony\\Component\\Form\\FormContext',
            'Symfony\\Component\\Form\\FormContextInterface',
        ));
    }

    /**
     * Loads the parameter converter configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function registerParamConverterConfiguration(array $config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
        $loader->load('param_converter.xml');
    }

    /**
     * Loads the templating configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function registerTemplatingConfiguration(array $config, ContainerBuilder $container)
    {
        $config = isset($config['templating']) ? $config['templating'] : array();

        if (!$container->hasDefinition('templating.locator')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load('templating.xml');
            $loader->load('templating_php.xml');

            if ($container->getParameter('kernel.debug')) {
                $loader->load('templating_debug.xml');
            }
        }

        if (array_key_exists('assets-version', $config)) {
            $container->setParameter('templating.assets.version', $config['assets-version']);
        }

        if (array_key_exists('assets_version', $config)) {
            $container->setParameter('templating.assets.version', $config['assets_version']);
        }

        if (array_key_exists('assets-base-urls', $config)) {
            $container->setParameter('templating.assets.base_urls', $config['assets-base-urls']);
        }

        if (array_key_exists('assets_base_urls', $config)) {
            $container->setParameter('templating.assets.base_urls', $config['assets_base_urls']);
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

        if (isset($config['cache-warmer'])) {
            $config['cache_warmer'] = $config['cache-warmer'];
        }

        if (isset($config['cache_warmer']) && $config['cache_warmer']) {
            $container->getDefinition('templating.cache_warmer.template_paths')->addTag('kernel.cache_warmer');
            $container->setAlias('templating.locator', 'templating.locator.cached');
        }

        // engines
        if (!$engines = $this->normalizeConfig($config, 'engine')) {
            throw new \LogicException('You must register at least one templating engine.');
        }

        foreach ($engines as $i => $engine) {
            $engines[$i] = new Reference('templating.engine.'.(is_array($engine) ? $engine['id'] : $engine));
        }

        if (1 === count($engines)) {
            $container->setAlias('templating', (string) $engines[0]);
        } else {
            $def = $container->getDefinition('templating.engine.delegating');
            $def->setArgument(1, $engines);

            $container->setAlias('templating', 'templating.engine.delegating');
        }

        $this->addClassesToCompile(array(
            'Symfony\\Bundle\\FrameworkBundle\\Templating\\EngineInterface',
            'Symfony\\Component\\Templating\\EngineInterface',
            'Symfony\\Bundle\\FrameworkBundle\\Templating\\Loader\\TemplateLocatorInterface',
            $container->findDefinition('templating.locator')->getClass(),
        ));
    }

    /**
     * Loads the test configuration.
     *
     * @param array            $config    A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function registerTestConfiguration(array $config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
        $loader->load('test.xml');

        $container->setAlias('session.storage', 'session.storage.array');
    }

    /**
     * Loads the ESI configuration.
     *
     * @param array            $config    A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function registerEsiConfiguration(array $config, ContainerBuilder $container)
    {
        if (isset($config['esi']['enabled']) && $config['esi']['enabled']) {
            if (!$container->hasDefinition('esi')) {
                $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
                $loader->load('esi.xml');
            }
        }
    }

    /**
     * Loads the translator configuration.
     *
     * @param array            $config    A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function registerTranslatorConfiguration(array $config, ContainerBuilder $container)
    {
        $first = false;
        if (!$container->hasDefinition('translator')) {
            $first = true;
            $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
            $loader->load('translation.xml');
        }

        $config = array_key_exists('translator', $config) ? $config['translator'] : array();
        if (!is_array($config)) {
            $config = array();
        }

        if (!isset($config['translator']['enabled']) || $config['translator']['enabled']) {
            // use the "real" translator
            $container->setDefinition('translator', $container->findDefinition('translator.real'));

            if ($first) {
                // translation directories
                $dirs = array();
                foreach ($container->getParameter('kernel.bundles') as $bundle) {
                    $reflection = new \ReflectionClass($bundle);
                    if (is_dir($dir = dirname($reflection->getFilename()).'/Resources/translations')) {
                        $dirs[] = $dir;
                    }
                }
                if (is_dir($dir = $container->getParameter('kernel.root_dir').'/translations')) {
                    $dirs[] = $dir;
                }

                // translation resources
                $resources = array();
                if ($dirs) {
                    $finder = new Finder();
                    $finder->files()->filter(function (\SplFileInfo $file) { return 2 === substr_count($file->getBasename(), '.'); })->in($dirs);
                    foreach ($finder as $file) {
                        // filename is domain.locale.format
                        list($domain, $locale, $format) = explode('.', $file->getBasename());

                        $resources[] = array($format, (string) $file, $locale, $domain);
                    }
                }
                $container->setParameter('translation.resources', $resources);
            }
        }

        if (array_key_exists('fallback', $config)) {
            $container->setParameter('translator.fallback_locale', $config['fallback']);
        }
    }

    /**
     * Loads the session configuration.
     *
     * @param array            $config    A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function registerSessionConfiguration(array $config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('session')) {
            $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
            $loader->load('session.xml');
        }

        $config = isset($config['session']) ? $config['session'] : array();

        foreach (array('default_locale', 'default-locale') as $key) {
            if (isset($config[$key])) {
                $container->setParameter('session.default_locale', $config[$key]);
            }
        }

        if (isset($config['auto-start'])) {
            $config['auto_start'] = $config['auto-start'];
        }

        if (isset($config['auto_start']) && $config['auto_start']) {
            $container->getDefinition('session')->addMethodCall('start');
        }

        if (isset($config['class'])) {
            $container->setParameter('session.class', $config['class']);
        }

        if (isset($config['storage-id'])) {
            $config['storage_id'] = $config['storage-id'];
        }

        if (isset($config['storage_id'])) {
            $container->setAlias('session.storage', 'session.storage.'.$config['storage_id']);
        } else {
            $config['storage_id'] = 'native';
        }

        $options = $container->getParameter('session.storage.'.strtolower($config['storage_id']).'.options');
        foreach (array('name', 'lifetime', 'path', 'domain', 'secure', 'httponly', 'cache_limiter', 'pdo.db_table', 'pdo.db_id_col', 'pdo.db_data_col', 'pdo.db_time_col') as $name) {
            $key = str_replace('pdo.', '', $name);
            if (isset($config[$name])) {
                $options[$key] = $config[$name];
            }

            $nName = str_replace('_', '-', $name);
            if (isset($config[$nName])) {
                $options[$key] = $config[$nName];
            }
        }
        $container->setParameter('session.storage.'.strtolower($config['storage_id']).'.options', $options);

        $this->addClassesToCompile(array(
            'Symfony\\Component\\HttpFoundation\\Session',
            'Symfony\\Component\\HttpFoundation\\SessionStorage\\SessionStorageInterface',
            $container->getParameter('session.class'),
        ));
    }

    /**
     * Loads the router configuration.
     *
     * @param array            $config    A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function registerRouterConfiguration(array $config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('router')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load('routing.xml');
        }

        $container->setParameter('routing.resource', $config['router']['resource']);

        if (isset($config['router']['cache-warmer'])) {
            $config['router']['cache_warmer'] = $config['router']['cache-warmer'];
        }

        if (isset($config['router']['cache_warmer']) && $config['router']['cache_warmer']) {
            $container->getDefinition('router.cache_warmer')->addTag('kernel.cache_warmer');
            $container->setAlias('router', 'router.cached');
        }

        $this->addClassesToCompile(array(
            'Symfony\\Component\\Routing\\RouterInterface',
            'Symfony\\Component\\Routing\\Matcher\\UrlMatcherInterface',
            'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
            'Symfony\\Component\\Routing\\Generator\\UrlGeneratorInterface',
            'Symfony\\Component\\Routing\\Generator\\UrlGenerator',
            $container->findDefinition('router')->getClass()
        ));
    }

    /**
     * Loads the profiler configuration.
     *
     *  <app:profiler only-exceptions="false">
     *      <matcher ip="192.168.0.0/24" path="#/admin/#i" />
     *      <matcher>
     *          <service class="MyMatcher" />
     *      </matcher>
     *      <matcher service="my_matcher" />
     *  </app:profiler>
     *
     * @param array            $config    A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function registerProfilerConfiguration(array $config, ContainerBuilder $container)
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
                    $definition->setPublic(false);

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

    /**
     * Loads the validator configuration.
     *
     * @param array            $config    A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function registerValidationConfiguration(array $config, ContainerBuilder $container)
    {
        if ($config['validation']['enabled']) {
            if (!$container->hasDefinition('validator')) {
                $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
                $loader->load('validator.xml');
            }

            $xmlMappingFiles = array();
            $yamlMappingFiles = array();

            // default entries by the framework
            $xmlMappingFiles[] = __DIR__.'/../../../Component/Form/Resources/config/validation.xml';

            foreach ($container->getParameter('kernel.bundles') as $bundle) {
                $reflection = new \ReflectionClass($bundle);
                if (file_exists($file = dirname($reflection->getFilename()).'/Resources/config/validation.xml')) {
                    $xmlMappingFiles[] = realpath($file);
                }
                if (file_exists($file = dirname($reflection->getFilename()).'/Resources/config/validation.yml')) {
                    $yamlMappingFiles[] = realpath($file);
                }
            }

            $xmlFilesLoader = new Definition(
                $container->getParameter('validator.mapping.loader.xml_files_loader.class'),
                array($xmlMappingFiles)
            );
            $xmlFilesLoader->setPublic(false);

            $yamlFilesLoader = new Definition(
                $container->getParameter('validator.mapping.loader.yaml_files_loader.class'),
                array($yamlMappingFiles)
            );
            $yamlFilesLoader->setPublic(false);

            $container->setDefinition('validator.mapping.loader.xml_files_loader', $xmlFilesLoader);
            $container->setDefinition('validator.mapping.loader.yaml_files_loader', $yamlFilesLoader);

            foreach ($xmlMappingFiles as $file) {
                $container->addResource(new FileResource($file));
            }

            foreach ($yamlMappingFiles as $file) {
                $container->addResource(new FileResource($file));
            }

            if (isset($config['validation']['annotations'])) {
                if (isset($config['validation']['annotations']['namespaces']) && is_array($config['validation']['annotations']['namespaces'])) {
                    $container->setParameter('validator.annotations.namespaces', array_merge(
                        $container->getParameter('validator.annotations.namespaces'),
                        $config['validation']['annotations']['namespaces']
                    ));
                }

                $annotationLoader = new Definition($container->getParameter('validator.mapping.loader.annotation_loader.class'));
                $annotationLoader->setPublic(false);
                $annotationLoader->addArgument(new Parameter('validator.annotations.namespaces'));

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
        return 'app';
    }
}
