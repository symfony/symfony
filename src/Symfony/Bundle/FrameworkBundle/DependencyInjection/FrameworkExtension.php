<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;

/**
 * FrameworkExtension.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class FrameworkExtension extends Extension
{
    /**
     * Responds to the app.config configuration parameter.
     *
     * @param array            $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $loader->load('web.xml');
        $loader->load('form.xml');
        $loader->load('services.xml');

        // A translator must always be registered (as support is included by
        // default in the Form component). If disabled, an identity translator
        // will be used and everything will still work as expected.
        $loader->load('translation.xml');

        if ($container->getParameter('kernel.debug')) {
            $loader->load('debug.xml');
            $container->setDefinition('event_dispatcher', $container->findDefinition('debug.event_dispatcher'));
            $container->setAlias('debug.event_dispatcher', 'event_dispatcher');
        }

        $processor = new Processor();
        $configuration = new Configuration($container->getParameter('kernel.debug'));
        $config = $processor->processConfiguration($configuration, $configs);

        $container->setParameter('kernel.cache_warmup', $config['cache_warmer']);

        if (isset($config['charset'])) {
            $container->setParameter('kernel.charset', $config['charset']);
        }

        if (isset($config['document_root'])) {
            $container->setParameter('document_root', $config['document_root']);
        }

        if (isset($config['error_handler'])) {
            if (false === $config['error_handler']) {
                $container->getDefinition('error_handler')->setMethodCalls(array());
            } else {
                $container
                    ->getDefinition('error_handler')->addMethodCall('register', array())
                    ->setArgument(0, $config['error_handler'])
                ;
            }
        }

        $container->getDefinition('exception_listener')->setArgument(0, $config['exception_controller']);

        if (!empty($config['test'])) {
            $loader->load('test.xml');
            if (isset($config['session'])) {
                $config['session']['storage_id'] = 'filesystem';
            }
        }

        if (isset($config['csrf_protection'])) {
            $this->registerCsrfProtectionConfiguration($config['csrf_protection'], $container);
        }

        if (isset($config['esi'])) {
            $this->registerEsiConfiguration($config['esi'], $loader);
        }

        if (isset($config['profiler'])) {
            $this->registerProfilerConfiguration($config['profiler'], $container, $loader);
        }

        if (isset($config['router'])) {
            $this->registerRouterConfiguration($config['router'], $container, $loader);
        }

        if (isset($config['session'])) {
            $this->registerSessionConfiguration($config['session'], $container, $loader);
        }

        if (isset($config['templating'])) {
            $this->registerTemplatingConfiguration($config['templating'], $config['ide'], $container, $loader);
        }

        if (isset($config['translator'])) {
            $this->registerTranslatorConfiguration($config['translator'], $container);
        }

        if (isset($config['validation'])) {
            $this->registerValidationConfiguration($config['validation'], $container, $loader);
        }

        $this->addClassesToCompile(array(
            'Symfony\\Component\\HttpFoundation\\ParameterBag',
            'Symfony\\Component\\HttpFoundation\\HeaderBag',
            'Symfony\\Component\\HttpFoundation\\Request',
            'Symfony\\Component\\HttpFoundation\\Response',
            'Symfony\\Component\\HttpFoundation\\ResponseHeaderBag',

            'Symfony\\Component\\EventDispatcher\\EventDispatcherInterface',
            'Symfony\\Component\\EventDispatcher\\EventDispatcher',
            'Symfony\\Component\\EventDispatcher\\Event',
            'Symfony\\Component\\EventDispatcher\\EventSubscriberInterface',

            'Symfony\\Component\\HttpKernel\\HttpKernel',
            'Symfony\\Component\\HttpKernel\\ResponseListener',
            'Symfony\\Component\\HttpKernel\\Controller\\ControllerResolver',
            'Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface',
            'Symfony\\Component\\HttpKernel\\Event\\KernelEvent',
            'Symfony\\Component\\HttpKernel\\Event\\FilterControllerEvent',
            'Symfony\\Component\\HttpKernel\\Event\\FilterResponseEvent',
            'Symfony\\Component\\HttpKernel\\Event\\GetResponseEvent',
            'Symfony\\Component\\HttpKernel\\Event\\GetResponseForControllerResultEvent',
            'Symfony\\Component\\HttpKernel\\Event\\GetResponseForExceptionEvent',
            'Symfony\\Component\\HttpKernel\\Events',

            'Symfony\\Bundle\\FrameworkBundle\\RequestListener',
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\ControllerNameParser',
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\ControllerResolver',
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\Controller',
            'Symfony\\Bundle\\FrameworkBundle\\ContainerAwareEventDispatcher',
        ));
    }

    /**
     * Loads the CSRF protection configuration.
     *
     * @param array            $config    A CSRF protection configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    private function registerCsrfProtectionConfiguration(array $config, ContainerBuilder $container)
    {
        foreach (array('enabled', 'field_name', 'secret') as $key) {
            if (isset($config[$key])) {
                $container->setParameter('form.csrf_protection.'.$key, $config[$key]);
            }
        }
    }

    /**
     * Loads the ESI configuration.
     *
     * @param array            $config    An ESI configuration array
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerEsiConfiguration(array $config, XmlFileLoader $loader)
    {
        if (!empty($config['enabled'])) {
            $loader->load('esi.xml');
        }
    }

    /**
     * Loads the profiler configuration.
     *
     * @param array            $config    A profiler configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerProfilerConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('profiling.xml');
        $loader->load('collectors.xml');

        $container->getDefinition('profiler_listener')
            ->setArgument(2, $config['only_exceptions'])
            ->setArgument(3, $config['only_master_requests'])
        ;

        // Choose storage class based on the DSN
        $supported = array(
            'sqlite' => 'Symfony\Component\HttpKernel\Profiler\SqliteProfilerStorage',
            'mysql'  => 'Symfony\Component\HttpKernel\Profiler\MysqlProfilerStorage',
        );
        list($class, ) = explode(':', $config['dsn']);
        if (!isset($supported[$class])) {
            throw new \LogicException(sprintf('Driver "%s" is not supported for the profiler.', $class));
        }

        $container->getDefinition('profiler.storage')
            ->setArgument(0, $config['dsn'])
            ->setArgument(1, $config['username'])
            ->setArgument(2, $config['password'])
            ->setArgument(3, $config['lifetime'])
            ->setClass($supported[$class])
        ;

        if (isset($config['matcher'])) {
            if (isset($config['matcher']['service'])) {
                $container->setAlias('profiler.request_matcher', $config['matcher']['service']);
            } elseif (isset($config['matcher']['ip']) || isset($config['matcher']['path'])) {
                $definition = $container->register('profiler.request_matcher', 'Symfony\\Component\\HttpFoundation\\RequestMatcher');
                $definition->setPublic(false);

                if (isset($config['matcher']['ip'])) {
                    $definition->addMethodCall('matchIp', array($config['matcher']['ip']));
                }

                if (isset($config['matcher']['path'])) {
                    $definition->addMethodCall('matchPath', array($config['matcher']['path']));
                }
            }
        }
    }

    /**
     * Loads the router configuration.
     *
     * @param array            $config    A router configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerRouterConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('routing.xml');

        $container->setParameter('routing.resource', $config['resource']);

        if (isset($config['type'])) {
            $container->setParameter('router.options.resource_type', $config['type']);
        }

        if ($config['cache_warmer']) {
            $container->getDefinition('router.cache_warmer')->addTag('kernel.cache_warmer');
            $container->setAlias('router', 'router.cached');
        }

        $this->addClassesToCompile(array(
            'Symfony\\Component\\Routing\\RouterInterface',
            'Symfony\\Component\\Routing\\Matcher\\UrlMatcherInterface',
            'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
            'Symfony\\Component\\Routing\\Generator\\UrlGeneratorInterface',
            'Symfony\\Component\\Routing\\Generator\\UrlGenerator',
            $container->findDefinition('router')->getClass(),
        ));
    }

    /**
     * Loads the session configuration.
     *
     * @param array            $config    A session configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerSessionConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('session.xml');

        if (!empty($config['auto_start'])) {
            $container->getDefinition('session')->addMethodCall('start');
        }

        if (isset($config['class'])) {
            $container->setParameter('session.class', $config['class']);
        }

        $container->getDefinition('session')->setArgument(1, $config['default_locale']);

        $container->setAlias('session.storage', 'session.storage.'.$config['storage_id']);

        $options = $container->getParameter('session.storage.'.$config['storage_id'].'.options');
        foreach (array('name', 'lifetime', 'path', 'domain', 'secure', 'httponly', 'db_table', 'db_id_col', 'db_data_col', 'db_time_col') as $key) {
            if (isset($config[$key])) {
                $options[$key] = $config[$key];
            }
        }
        $container->setParameter('session.storage.'.$config['storage_id'].'.options', $options);

        $this->addClassesToCompile(array(
            'Symfony\\Component\\HttpFoundation\\Session',
            'Symfony\\Component\\HttpFoundation\\SessionStorage\\SessionStorageInterface',
            $container->getParameter('session.class'),
        ));
    }

    /**
     * Loads the templating configuration.
     *
     * @param array            $config    A templating configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerTemplatingConfiguration(array $config, $ide, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('templating.xml');
        $loader->load('templating_php.xml');

        $links = array(
            'textmate' => 'txmt://open?url=file://%f&line=%l',
            'macvim'   => 'mvim://open?url=file://%f&line=%l',
        );

        $container
            ->getDefinition('templating.helper.code')
            ->setArgument(0, str_replace('%', '%%', isset($links[$ide]) ? $links[$ide] : $ide))
        ;

        if ($container->getParameter('kernel.debug')) {
            $loader->load('templating_debug.xml');
        }

        $packages = array();
        foreach ($config['packages'] as $name => $package) {
            $packages[$name] = new Definition('%templating.asset_package.class%', array(
                $package['base_urls'],
                $package['version'],
            ));
        }
        $container
            ->getDefinition('templating.helper.assets')
            ->setArgument(1, isset($config['assets_base_urls']) ? $config['assets_base_urls'] : array())
            ->setArgument(2, $config['assets_version'])
            ->setArgument(3, $packages)
        ;

        if (!empty($config['loaders'])) {
            $loaders = array_map(function($loader) { return new Reference($loader); }, $config['loaders']);

            // Use a delegation unless only a single loader was registered
            if (1 === count($loaders)) {
                $container->setAlias('templating.loader', (string) reset($loaders));
            } else {
                $container->getDefinition('templating.loader.chain')->addArgument($loaders);
                $container->setAlias('templating.loader', 'templating.loader.chain');
            }
        }

        if (isset($config['cache'])) {
            // Wrap the existing loader with cache (must happen after loaders are registered)
            $container->setDefinition('templating.loader.wrapped', $container->findDefinition('templating.loader'));
            $loaderCache = $container->getDefinition('templating.loader.cache');
            $loaderCache->setArgument(1, $config['cache']);

            $container->setDefinition('templating.loader', $loaderCache);
        }

        if ($config['cache_warmer']) {
            $container->getDefinition('templating.cache_warmer.template_paths')->addTag('kernel.cache_warmer');
            $container->setAlias('templating.locator', 'templating.locator.cached');
        } else {
            $container->setAlias('templating.locator', 'templating.locator.uncached');
        }

        $this->addClassesToCompile(array(
            'Symfony\\Bundle\\FrameworkBundle\\Templating\\EngineInterface',
            'Symfony\\Component\\Templating\\TemplateNameParserInterface',
            'Symfony\\Component\\Templating\\TemplateNameParser',
            'Symfony\\Component\\Templating\\EngineInterface',
            'Symfony\\Component\\Config\\FileLocatorInterface',
            'Symfony\\Component\\Templating\\TemplateReferenceInterface',
            'Symfony\\Component\\Templating\\TemplateReference',
            'Symfony\\Bundle\\FrameworkBundle\\Templating\\TemplateReference',
            'Symfony\\Bundle\\FrameworkBundle\\Templating\\TemplateNameParser',
            $container->findDefinition('templating.locator')->getClass(),
        ));

        if (in_array('php', $config['engines'], true)) {
            $this->addClassesToCompile(array(
                'Symfony\\Component\\Templating\\PhpEngine',
                'Symfony\\Component\\Templating\\Loader\\LoaderInterface',
                'Symfony\\Component\\Templating\\Storage\\Storage',
                'Symfony\\Component\\Templating\\Storage\\FileStorage',
                'Symfony\\Bundle\\FrameworkBundle\\Templating\\PhpEngine',
                'Symfony\\Bundle\\FrameworkBundle\\Templating\\Loader\\FilesystemLoader',
            ));
        }

        $container->setParameter('templating.engines', $config['engines']);
        $engines = array_map(function($engine) { return new Reference('templating.engine.'.$engine); }, $config['engines']);

        // Use a delegation unless only a single engine was registered
        if (1 === count($engines)) {
            $container->setAlias('templating', (string) reset($engines));
        } else {
            $container->getDefinition('templating.engine.delegating')->setArgument(1, $engines);
            $container->setAlias('templating', 'templating.engine.delegating');
        }
    }

    /**
     * Loads the translator configuration.
     *
     * @param array            $config    A translator configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    private function registerTranslatorConfiguration(array $config, ContainerBuilder $container)
    {
        if (!empty($config['enabled'])) {
            // Use the "real" translator instead of the identity default
            $container->setDefinition('translator', $translator = $container->findDefinition('translator.real'));
            $translator->addMethodCall('setFallbackLocale', array($config['fallback']));

            // Discover translation directories
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

            // Register translation resources
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

    /**
     * Loads the validator configuration.
     *
     * @param array            $config    A validation configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerValidationConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (empty($config['enabled'])) {
            return;
        }

        $loader->load('validator.xml');

        $container
            ->getDefinition('validator.mapping.loader.xml_files_loader')
            ->setArgument(0, $this->getValidatorXmlMappingFiles($container))
        ;

        $container
            ->getDefinition('validator.mapping.loader.yaml_files_loader')
            ->setArgument(0, $this->getValidatorYamlMappingFiles($container))
        ;

        if (isset($config['annotations'])) {
            $namespaces = array('assert' => 'Symfony\\Component\\Validator\\Constraints\\');
            // Register prefixes for constraint namespaces
            if (!empty($config['annotations']['namespaces'])) {
                $namespaces = array_merge($namespaces, $config['annotations']['namespaces']);
            }

            // Register annotation loader
            $container
                ->getDefinition('validator.mapping.loader.annotation_loader')
                ->setArgument(0, $namespaces)
            ;

            $loaderChain = $container->getDefinition('validator.mapping.loader.loader_chain');
            $arguments = $loaderChain->getArguments();
            array_unshift($arguments[0], new Reference('validator.mapping.loader.annotation_loader'));
            $loaderChain->setArguments($arguments);
        }

        if (isset($config['cache'])) {
            $container->getDefinition('validator.mapping.class_metadata_factory')
                ->setArgument(1, new Reference('validator.mapping.cache.'.$config['cache']));
            $container->setParameter(
                'validator.mapping.cache.prefix',
                'validator_'.md5($container->getParameter('kernel.root_dir'))
            );
        }
    }

    private function getValidatorXmlMappingFiles(ContainerBuilder $container)
    {
        $files = array(__DIR__.'/../../../Component/Form/Resources/config/validation.xml');
        $container->addResource(new FileResource($files[0]));

        foreach ($container->getParameter('kernel.bundles') as $bundle) {
            $reflection = new \ReflectionClass($bundle);
            if (file_exists($file = dirname($reflection->getFilename()).'/Resources/config/validation.xml')) {
                $files[] = realpath($file);
                $container->addResource(new FileResource($file));
            }
        }

        return $files;
    }

    private function getValidatorYamlMappingFiles(ContainerBuilder $container)
    {
        $files = array();

        foreach ($container->getParameter('kernel.bundles') as $bundle) {
            $reflection = new \ReflectionClass($bundle);
            if (file_exists($file = dirname($reflection->getFilename()).'/Resources/config/validation.yml')) {
                $files[] = realpath($file);
                $container->addResource(new FileResource($file));
            }
        }

        return $files;
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
        return 'http://symfony.com/schema/dic/symfony';
    }
}
