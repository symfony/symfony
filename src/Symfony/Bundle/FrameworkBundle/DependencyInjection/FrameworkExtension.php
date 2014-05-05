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
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;

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
        $loader->load('services.xml');
        $loader->load('fragment_renderer.xml');

        // A translator must always be registered (as support is included by
        // default in the Form component). If disabled, an identity translator
        // will be used and everything will still work as expected.
        $loader->load('translation.xml');

        $loader->load('debug_prod.xml');

        if ($container->getParameter('kernel.debug')) {
            $loader->load('debug.xml');

            // only HttpKernel needs the debug event dispatcher
            $definition = $container->findDefinition('http_kernel');
            $arguments = $definition->getArguments();
            $arguments[0] = new Reference('debug.event_dispatcher');
            $arguments[2] = new Reference('debug.controller_resolver');
            $definition->setArguments($arguments);
        }

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        if (isset($config['secret'])) {
            $container->setParameter('kernel.secret', $config['secret']);
        }

        $container->setParameter('kernel.http_method_override', $config['http_method_override']);
        $container->setParameter('kernel.trusted_hosts', $config['trusted_hosts']);
        $container->setParameter('kernel.trusted_proxies', $config['trusted_proxies']);
        $container->setParameter('kernel.default_locale', $config['default_locale']);

        if (!empty($config['test'])) {
            $loader->load('test.xml');
        }

        if (isset($config['session'])) {
            $this->registerSessionConfiguration($config['session'], $container, $loader);
        }

        if ($this->isConfigEnabled($container, $config['form'])) {
            $this->registerFormConfiguration($config, $container, $loader);
            $config['validation']['enabled'] = true;
        }

        if (isset($config['templating'])) {
            $this->registerTemplatingConfiguration($config['templating'], $config['ide'], $container, $loader);
        }

        $this->registerValidationConfiguration($config['validation'], $container, $loader);
        $this->registerEsiConfiguration($config['esi'], $container, $loader);
        $this->registerFragmentsConfiguration($config['fragments'], $container, $loader);
        $this->registerProfilerConfiguration($config['profiler'], $container, $loader);
        $this->registerTranslatorConfiguration($config['translator'], $container);

        if (isset($config['router'])) {
            $this->registerRouterConfiguration($config['router'], $container, $loader);
        }

        $this->registerAnnotationsConfiguration($config['annotations'], $container, $loader);

        if (isset($config['serializer']) && $config['serializer']['enabled']) {
            $loader->load('serializer.xml');
        }

        $this->addClassesToCompile(array(
            'Symfony\\Component\\Config\\FileLocator',

            'Symfony\\Component\\EventDispatcher\\Event',
            'Symfony\\Component\\EventDispatcher\\ContainerAwareEventDispatcher',

            'Symfony\\Component\\HttpKernel\\EventListener\\ResponseListener',
            'Symfony\\Component\\HttpKernel\\EventListener\\RouterListener',
            'Symfony\\Component\\HttpKernel\\Controller\\ControllerResolver',
            'Symfony\\Component\\HttpKernel\\Event\\KernelEvent',
            'Symfony\\Component\\HttpKernel\\Event\\FilterControllerEvent',
            'Symfony\\Component\\HttpKernel\\Event\\FilterResponseEvent',
            'Symfony\\Component\\HttpKernel\\Event\\GetResponseEvent',
            'Symfony\\Component\\HttpKernel\\Event\\GetResponseForControllerResultEvent',
            'Symfony\\Component\\HttpKernel\\Event\\GetResponseForExceptionEvent',
            'Symfony\\Component\\HttpKernel\\KernelEvents',
            'Symfony\\Component\\HttpKernel\\Config\\FileLocator',

            'Symfony\\Bundle\\FrameworkBundle\\Controller\\ControllerNameParser',
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\ControllerResolver',
            // Cannot be included because annotations will parse the big compiled class file
            // 'Symfony\\Bundle\\FrameworkBundle\\Controller\\Controller',
        ));
    }

    /**
     * Loads Form configuration.
     *
     * @param array            $config    A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     *
     * @throws \LogicException
     */
    private function registerFormConfiguration($config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('form.xml');
        if ($this->isConfigEnabled($container, $config['csrf_protection'])) {
            if (!isset($config['session'])) {
                throw new \LogicException('CSRF protection needs that sessions are enabled.');
            }
            if (!isset($config['secret'])) {
                throw new \LogicException('CSRF protection needs a secret to be set.');
            }
            $loader->load('form_csrf.xml');

            $container->setParameter('form.type_extension.csrf.enabled', true);
            $container->setParameter('form.type_extension.csrf.field_name', $config['csrf_protection']['field_name']);
        } else {
            $container->setParameter('form.type_extension.csrf.enabled', false);
        }
    }

    /**
     * Loads the ESI configuration.
     *
     * @param array            $config    An ESI configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerEsiConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('esi.xml');
    }

    /**
     * Loads the fragments configuration.
     *
     * @param array            $config    A fragments configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerFragmentsConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('fragment_listener.xml');
        $container->setParameter('fragment.path', $config['path']);
    }

    /**
     * Loads the profiler configuration.
     *
     * @param array            $config    A profiler configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     *
     * @throws \LogicException
     */
    private function registerProfilerConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            // this is needed for the WebProfiler to work even if the profiler is disabled
            $container->setParameter('data_collector.templates', array());

            return;
        }

        $loader->load('profiling.xml');
        $loader->load('collectors.xml');

        $container->setParameter('profiler_listener.only_exceptions', $config['only_exceptions']);
        $container->setParameter('profiler_listener.only_master_requests', $config['only_master_requests']);

        // Choose storage class based on the DSN
        $supported = array(
            'sqlite'    => 'Symfony\Component\HttpKernel\Profiler\SqliteProfilerStorage',
            'mysql'     => 'Symfony\Component\HttpKernel\Profiler\MysqlProfilerStorage',
            'file'      => 'Symfony\Component\HttpKernel\Profiler\FileProfilerStorage',
            'mongodb'   => 'Symfony\Component\HttpKernel\Profiler\MongoDbProfilerStorage',
            'memcache'  => 'Symfony\Component\HttpKernel\Profiler\MemcacheProfilerStorage',
            'memcached' => 'Symfony\Component\HttpKernel\Profiler\MemcachedProfilerStorage',
            'redis'     => 'Symfony\Component\HttpKernel\Profiler\RedisProfilerStorage',
        );
        list($class, ) = explode(':', $config['dsn'], 2);
        if (!isset($supported[$class])) {
            throw new \LogicException(sprintf('Driver "%s" is not supported for the profiler.', $class));
        }

        $container->setParameter('profiler.storage.dsn', $config['dsn']);
        $container->setParameter('profiler.storage.username', $config['username']);
        $container->setParameter('profiler.storage.password', $config['password']);
        $container->setParameter('profiler.storage.lifetime', $config['lifetime']);

        $container->getDefinition('profiler.storage')->setClass($supported[$class]);

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

        if (!$config['collect']) {
            $container->getDefinition('profiler')->addMethodCall('disable', array());
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

        $container->setParameter('router.resource', $config['resource']);
        $container->setParameter('router.cache_class_prefix', $container->getParameter('kernel.name').ucfirst($container->getParameter('kernel.environment')));
        $router = $container->findDefinition('router.default');
        $argument = $router->getArgument(2);
        $argument['strict_requirements'] = $config['strict_requirements'];
        if (isset($config['type'])) {
            $argument['resource_type'] = $config['type'];
        }
        $router->replaceArgument(2, $argument);

        $container->setParameter('request_listener.http_port', $config['http_port']);
        $container->setParameter('request_listener.https_port', $config['https_port']);

        $this->addClassesToCompile(array(
            'Symfony\\Component\\Routing\\Generator\\UrlGenerator',
            'Symfony\\Component\\Routing\\RequestContext',
            'Symfony\\Component\\Routing\\Router',
            'Symfony\\Bundle\\FrameworkBundle\\Routing\\RedirectableUrlMatcher',
            $container->findDefinition('router.default')->getClass(),
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

        // session storage
        $container->setAlias('session.storage', $config['storage_id']);
        $options = array();
        foreach (array('name', 'cookie_lifetime', 'cookie_path', 'cookie_domain', 'cookie_secure', 'cookie_httponly', 'gc_maxlifetime', 'gc_probability', 'gc_divisor') as $key) {
            if (isset($config[$key])) {
                $options[$key] = $config[$key];
            }
        }

        $container->setParameter('session.storage.options', $options);

        // session handler (the internal callback registered with PHP session management)
        if (null === $config['handler_id']) {
            // Set the handler class to be null
            $container->getDefinition('session.storage.native')->replaceArgument(1, null);
            $container->getDefinition('session.storage.php_bridge')->replaceArgument(0, null);
        } else {
            $container->setAlias('session.handler', $config['handler_id']);
        }

        $container->setParameter('session.save_path', $config['save_path']);

        $this->addClassesToCompile(array(
            'Symfony\\Bundle\\FrameworkBundle\\EventListener\\SessionListener',
            'Symfony\\Component\\HttpFoundation\\Session\\Storage\\NativeSessionStorage',
            'Symfony\\Component\\HttpFoundation\\Session\\Storage\\PhpBridgeSessionStorage',
            'Symfony\\Component\\HttpFoundation\\Session\\Storage\\Handler\\NativeFileSessionHandler',
            'Symfony\\Component\\HttpFoundation\\Session\\Storage\\Proxy\\AbstractProxy',
            'Symfony\\Component\\HttpFoundation\\Session\\Storage\\Proxy\\SessionHandlerProxy',
            $container->getDefinition('session')->getClass(),
        ));

        if ($container->hasDefinition($config['storage_id'])) {
            $this->addClassesToCompile(array(
                $container->findDefinition('session.storage')->getClass(),
            ));
        }
    }

    /**
     * Loads the templating configuration.
     *
     * @param array            $config    A templating configuration array
     * @param string           $ide
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerTemplatingConfiguration(array $config, $ide, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('templating.xml');
        $loader->load('templating_php.xml');

        $links = array(
            'textmate' => 'txmt://open?url=file://%%f&line=%%l',
            'macvim'   => 'mvim://open?url=file://%%f&line=%%l',
            'emacs'    => 'emacs://open?url=file://%file&line=%line',
            'sublime'  => 'subl://open?url=file://%file&line=%line',
        );

        $container->setParameter('templating.helper.code.file_link_format', isset($links[$ide]) ? $links[$ide] : $ide);
        $container->setParameter('templating.helper.form.resources', $config['form']['resources']);
        $container->setParameter('fragment.renderer.hinclude.global_template', $config['hinclude_default_template']);

        if ($container->getParameter('kernel.debug')) {
            $loader->load('templating_debug.xml');

            $container->setDefinition('templating.engine.php', $container->findDefinition('debug.templating.engine.php'));
            $container->setAlias('debug.templating.engine.php', 'templating.engine.php');
        }

        // create package definitions and add them to the assets helper
        $defaultPackage = $this->createPackageDefinition($container, $config['assets_base_urls']['http'], $config['assets_base_urls']['ssl'], $config['assets_version'], $config['assets_version_format']);
        $container->setDefinition('templating.asset.default_package', $defaultPackage);
        $namedPackages = array();
        foreach ($config['packages'] as $name => $package) {
            $namedPackage = $this->createPackageDefinition($container, $package['base_urls']['http'], $package['base_urls']['ssl'], $package['version'], $package['version_format'], $name);
            $container->setDefinition('templating.asset.package.'.$name, $namedPackage);
            $namedPackages[$name] = new Reference('templating.asset.package.'.$name);
        }
        $container->getDefinition('templating.helper.assets')->setArguments(array(
            new Reference('templating.asset.default_package'),
            $namedPackages,
        ));

        // Apply request scope to assets helper if one or more packages are request-scoped
        $requireRequestScope = array_reduce(
            $namedPackages,
            function ($v, Reference $ref) use ($container) {
                return $v || 'request' === $container->getDefinition($ref)->getScope();
            },
            'request' === $defaultPackage->getScope()
        );

        if ($requireRequestScope) {
            $container->getDefinition('templating.helper.assets')->setScope('request');
        }

        if (!empty($config['loaders'])) {
            $loaders = array_map(function ($loader) { return new Reference($loader); }, $config['loaders']);

            // Use a delegation unless only a single loader was registered
            if (1 === count($loaders)) {
                $container->setAlias('templating.loader', (string) reset($loaders));
            } else {
                $container->getDefinition('templating.loader.chain')->addArgument($loaders);
                $container->setAlias('templating.loader', 'templating.loader.chain');
            }
        }

        $container->setParameter('templating.loader.cache.path', null);
        if (isset($config['cache'])) {
            // Wrap the existing loader with cache (must happen after loaders are registered)
            $container->setDefinition('templating.loader.wrapped', $container->findDefinition('templating.loader'));
            $loaderCache = $container->getDefinition('templating.loader.cache');
            $container->setParameter('templating.loader.cache.path', $config['cache']);

            $container->setDefinition('templating.loader', $loaderCache);
        }

        $this->addClassesToCompile(array(
            'Symfony\\Bundle\\FrameworkBundle\\Templating\\GlobalVariables',
            'Symfony\\Bundle\\FrameworkBundle\\Templating\\TemplateReference',
            'Symfony\\Bundle\\FrameworkBundle\\Templating\\TemplateNameParser',
            $container->findDefinition('templating.locator')->getClass(),
        ));

        if (in_array('php', $config['engines'], true)) {
            $this->addClassesToCompile(array(
                'Symfony\\Component\\Templating\\Storage\\FileStorage',
                'Symfony\\Bundle\\FrameworkBundle\\Templating\\PhpEngine',
                'Symfony\\Bundle\\FrameworkBundle\\Templating\\Loader\\FilesystemLoader',
            ));
        }

        $container->setParameter('templating.engines', $config['engines']);
        $engines = array_map(function ($engine) { return new Reference('templating.engine.'.$engine); }, $config['engines']);

        // Use a delegation unless only a single engine was registered
        if (1 === count($engines)) {
            $container->setAlias('templating', (string) reset($engines));
        } else {
            foreach ($engines as $engine) {
                $container->getDefinition('templating.engine.delegating')->addMethodCall('addEngine', array($engine));
            }
            $container->setAlias('templating', 'templating.engine.delegating');
        }
    }

    /**
     * Returns a definition for an asset package.
     */
    private function createPackageDefinition(ContainerBuilder $container, array $httpUrls, array $sslUrls, $version, $format, $name = null)
    {
        if (!$httpUrls) {
            $package = new DefinitionDecorator('templating.asset.path_package');
            $package
                ->setPublic(false)
                ->setScope('request')
                ->replaceArgument(1, $version)
                ->replaceArgument(2, $format)
            ;

            return $package;
        }

        if ($httpUrls == $sslUrls) {
            $package = new DefinitionDecorator('templating.asset.url_package');
            $package
                ->setPublic(false)
                ->replaceArgument(0, $sslUrls)
                ->replaceArgument(1, $version)
                ->replaceArgument(2, $format)
            ;

            return $package;
        }

        $prefix = $name ? 'templating.asset.package.'.$name : 'templating.asset.default_package';

        $httpPackage = new DefinitionDecorator('templating.asset.url_package');
        $httpPackage
            ->replaceArgument(0, $httpUrls)
            ->replaceArgument(1, $version)
            ->replaceArgument(2, $format)
        ;
        $container->setDefinition($prefix.'.http', $httpPackage);

        if ($sslUrls) {
            $sslPackage = new DefinitionDecorator('templating.asset.url_package');
            $sslPackage
                ->replaceArgument(0, $sslUrls)
                ->replaceArgument(1, $version)
                ->replaceArgument(2, $format)
            ;
        } else {
            $sslPackage = new DefinitionDecorator('templating.asset.path_package');
            $sslPackage
                ->setScope('request')
                ->replaceArgument(1, $version)
                ->replaceArgument(2, $format)
            ;
        }
        $container->setDefinition($prefix.'.ssl', $sslPackage);

        $package = new DefinitionDecorator('templating.asset.request_aware_package');
        $package
            ->setPublic(false)
            ->setScope('request')
            ->replaceArgument(1, $prefix.'.http')
            ->replaceArgument(2, $prefix.'.ssl')
        ;

        return $package;
    }

    /**
     * Loads the translator configuration.
     *
     * @param array            $config    A translator configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    private function registerTranslatorConfiguration(array $config, ContainerBuilder $container)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        // Use the "real" translator instead of the identity default
        $container->setAlias('translator', 'translator.default');
        $translator = $container->findDefinition('translator.default');
        if (!is_array($config['fallback'])) {
            $config['fallback'] = array($config['fallback']);
        }
        $translator->addMethodCall('setFallbackLocales', array($config['fallback']));

        // Discover translation directories
        $dirs = array();
        if (class_exists('Symfony\Component\Validator\Validator')) {
            $r = new \ReflectionClass('Symfony\Component\Validator\Validator');

            $dirs[] = dirname($r->getFilename()).'/Resources/translations';
        }
        if (class_exists('Symfony\Component\Form\Form')) {
            $r = new \ReflectionClass('Symfony\Component\Form\Form');

            $dirs[] = dirname($r->getFilename()).'/Resources/translations';
        }
        if (class_exists('Symfony\Component\Security\Core\Exception\AuthenticationException')) {
            $r = new \ReflectionClass('Symfony\Component\Security\Core\Exception\AuthenticationException');

            $dirs[] = dirname($r->getFilename()).'/../../Resources/translations';
        }
        $overridePath = $container->getParameter('kernel.root_dir').'/Resources/%s/translations';
        foreach ($container->getParameter('kernel.bundles') as $bundle => $class) {
            $reflection = new \ReflectionClass($class);
            if (is_dir($dir = dirname($reflection->getFilename()).'/Resources/translations')) {
                $dirs[] = $dir;
            }
            if (is_dir($dir = sprintf($overridePath, $bundle))) {
                $dirs[] = $dir;
            }
        }
        if (is_dir($dir = $container->getParameter('kernel.root_dir').'/Resources/translations')) {
            $dirs[] = $dir;
        }

        // Register translation resources
        if ($dirs) {
            foreach ($dirs as $dir) {
                $container->addResource(new DirectoryResource($dir));
            }
            $finder = Finder::create()
                ->files()
                ->filter(function (\SplFileInfo $file) {
                    return 2 === substr_count($file->getBasename(), '.') && preg_match('/\.\w+$/', $file->getBasename());
                })
                ->in($dirs)
            ;

            foreach ($finder as $file) {
                // filename is domain.locale.format
                list($domain, $locale, $format) = explode('.', $file->getBasename(), 3);
                $translator->addMethodCall('addResource', array($format, (string) $file, $locale, $domain));
            }
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
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('validator.xml');

        $container->setParameter('validator.translation_domain', $config['translation_domain']);
        $container->setParameter('validator.mapping.loader.xml_files_loader.mapping_files', $this->getValidatorXmlMappingFiles($container));
        $container->setParameter('validator.mapping.loader.yaml_files_loader.mapping_files', $this->getValidatorYamlMappingFiles($container));

        if (array_key_exists('enable_annotations', $config) && $config['enable_annotations']) {
            $loaderChain = $container->getDefinition('validator.mapping.loader.loader_chain');
            $arguments = $loaderChain->getArguments();
            array_unshift($arguments[0], new Reference('validator.mapping.loader.annotation_loader'));
            $loaderChain->setArguments($arguments);
        }

        if (isset($config['cache'])) {
            $container->getDefinition('validator.mapping.class_metadata_factory')
                ->replaceArgument(1, new Reference('validator.mapping.cache.'.$config['cache']));
            $container->setParameter(
                'validator.mapping.cache.prefix',
                'validator_'.md5($container->getParameter('kernel.root_dir'))
            );
        }
    }

    private function getValidatorXmlMappingFiles(ContainerBuilder $container)
    {
        $files = array();

        if (interface_exists('Symfony\Component\Form\FormInterface')) {
            $reflClass = new \ReflectionClass('Symfony\Component\Form\FormInterface');
            $files[] = dirname($reflClass->getFileName()).'/Resources/config/validation.xml';
            $container->addResource(new FileResource($files[0]));
        }

        foreach ($container->getParameter('kernel.bundles') as $bundle) {
            $reflection = new \ReflectionClass($bundle);
            if (is_file($file = dirname($reflection->getFilename()).'/Resources/config/validation.xml')) {
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
            if (is_file($file = dirname($reflection->getFilename()).'/Resources/config/validation.yml')) {
                $files[] = realpath($file);
                $container->addResource(new FileResource($file));
            }
        }

        return $files;
    }

    private function registerAnnotationsConfiguration(array $config, ContainerBuilder $container,$loader)
    {
        $loader->load('annotations.xml');

        if ('file' === $config['cache']) {
            $cacheDir = $container->getParameterBag()->resolveValue($config['file_cache_dir']);
            if (!is_dir($cacheDir) && false === @mkdir($cacheDir, 0777, true)) {
                throw new \RuntimeException(sprintf('Could not create cache directory "%s".', $cacheDir));
            }

            $container
                ->getDefinition('annotations.file_cache_reader')
                ->replaceArgument(1, $cacheDir)
                ->replaceArgument(2, $config['debug'])
            ;
            $container->setAlias('annotation_reader', 'annotations.file_cache_reader');
        } elseif ('none' !== $config['cache']) {
            $container
                ->getDefinition('annotations.cached_reader')
                ->replaceArgument(1, new Reference($config['cache']))
                ->replaceArgument(2, $config['debug'])
            ;
            $container->setAlias('annotation_reader', 'annotations.cached_reader');
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
        return 'http://symfony.com/schema/dic/symfony';
    }
}
