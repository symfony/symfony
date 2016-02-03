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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Validator\Validation;

/**
 * FrameworkExtension.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jeremy Mikola <jmikola@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class FrameworkExtension extends Extension
{
    private $formConfigEnabled = false;
    private $translationConfigEnabled = false;
    private $sessionConfigEnabled = false;

    /**
     * @var string|null
     */
    private $kernelRootHash;

    /**
     * Responds to the app.config configuration parameter.
     *
     * @param array            $configs
     * @param ContainerBuilder $container
     *
     * @throws LogicException
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

        // Property access is used by both the Form and the Validator component
        $loader->load('property_access.xml');

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
            $this->sessionConfigEnabled = true;
            $this->registerSessionConfiguration($config['session'], $container, $loader);
        }

        if (isset($config['request'])) {
            $this->registerRequestConfiguration($config['request'], $container, $loader);
        }

        if ($this->isConfigEnabled($container, $config['form'])) {
            $this->formConfigEnabled = true;
            $this->registerFormConfiguration($config, $container, $loader);
            $config['validation']['enabled'] = true;

            if (!class_exists('Symfony\Component\Validator\Validation')) {
                throw new LogicException('The Validator component is required to use the Form component.');
            }
        }

        $this->registerSecurityCsrfConfiguration($config['csrf_protection'], $container, $loader);

        if (isset($config['assets'])) {
            $this->registerAssetsConfiguration($config['assets'], $container, $loader);
        }

        if (isset($config['templating'])) {
            $this->registerTemplatingConfiguration($config['templating'], $config['ide'], $container, $loader);
        }

        $this->registerValidationConfiguration($config['validation'], $container, $loader);
        $this->registerEsiConfiguration($config['esi'], $container, $loader);
        $this->registerSsiConfiguration($config['ssi'], $container, $loader);
        $this->registerFragmentsConfiguration($config['fragments'], $container, $loader);
        $this->registerTranslatorConfiguration($config['translator'], $container);
        $this->registerProfilerConfiguration($config['profiler'], $container, $loader);

        if (isset($config['router'])) {
            $this->registerRouterConfiguration($config['router'], $container, $loader);
        }

        $this->registerAnnotationsConfiguration($config['annotations'], $container, $loader);
        $this->registerPropertyAccessConfiguration($config['property_access'], $container);

        if (isset($config['serializer'])) {
            $this->registerSerializerConfiguration($config['serializer'], $container, $loader);
        }

        if (isset($config['property_info'])) {
            $this->registerPropertyInfoConfiguration($config['property_info'], $container, $loader);
        }

        $loader->load('debug_prod.xml');
        $definition = $container->findDefinition('debug.debug_handlers_listener');

        if ($container->hasParameter('templating.helper.code.file_link_format')) {
            $definition->replaceArgument(5, '%templating.helper.code.file_link_format%');
        }

        if ($container->getParameter('kernel.debug')) {
            $definition->replaceArgument(2, E_ALL & ~(E_COMPILE_ERROR | E_PARSE | E_ERROR | E_CORE_ERROR | E_RECOVERABLE_ERROR));

            $loader->load('debug.xml');

            $definition = $container->findDefinition('http_kernel');
            $definition->replaceArgument(1, new Reference('debug.controller_resolver'));

            // replace the regular event_dispatcher service with the debug one
            $definition = $container->findDefinition('event_dispatcher');
            $definition->setPublic(false);
            $container->setDefinition('debug.event_dispatcher.parent', $definition);
            $container->setAlias('event_dispatcher', 'debug.event_dispatcher');
        } else {
            $definition->replaceArgument(1, null);
        }

        $this->addClassesToCompile(array(
            'Symfony\\Component\\Config\\FileLocator',

            'Symfony\\Component\\Debug\\ErrorHandler',

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
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration($container->getParameter('kernel.debug'));
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
        if (null === $config['form']['csrf_protection']['enabled']) {
            $config['form']['csrf_protection']['enabled'] = $config['csrf_protection']['enabled'];
        }

        if ($this->isConfigEnabled($container, $config['form']['csrf_protection'])) {
            $loader->load('form_csrf.xml');

            $container->setParameter('form.type_extension.csrf.enabled', true);
            $container->setParameter('form.type_extension.csrf.field_name', $config['form']['csrf_protection']['field_name']);
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
     * Loads the SSI configuration.
     *
     * @param array            $config    An SSI configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerSsiConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $loader->load('ssi.xml');
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

        if ($this->formConfigEnabled) {
            $loader->load('form_debug.xml');
        }

        if ($this->translationConfigEnabled) {
            $loader->load('translation_debug.xml');
            $container->getDefinition('translator.data_collector')->setDecoratedService('translator');
        }

        $container->setParameter('profiler_listener.only_exceptions', $config['only_exceptions']);
        $container->setParameter('profiler_listener.only_master_requests', $config['only_master_requests']);

        // Choose storage class based on the DSN
        list($class) = explode(':', $config['dsn'], 2);
        if ('file' !== $class) {
            throw new \LogicException(sprintf('Driver "%s" is not supported for the profiler.', $class));
        }

        $container->setParameter('profiler.storage.dsn', $config['dsn']);

        if (isset($config['matcher'])) {
            if (isset($config['matcher']['service'])) {
                $container->setAlias('profiler.request_matcher', $config['matcher']['service']);
            } elseif (isset($config['matcher']['ip']) || isset($config['matcher']['path']) || isset($config['matcher']['ips'])) {
                $definition = $container->register('profiler.request_matcher', 'Symfony\\Component\\HttpFoundation\\RequestMatcher');
                $definition->setPublic(false);

                if (isset($config['matcher']['ip'])) {
                    $definition->addMethodCall('matchIp', array($config['matcher']['ip']));
                }

                if (isset($config['matcher']['ips'])) {
                    $definition->addMethodCall('matchIps', array($config['matcher']['ips']));
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
        foreach (array('name', 'cookie_lifetime', 'cookie_path', 'cookie_domain', 'cookie_secure', 'cookie_httponly', 'use_cookies', 'gc_maxlifetime', 'gc_probability', 'gc_divisor') as $key) {
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
            $handlerId = $config['handler_id'];

            if ($config['metadata_update_threshold'] > 0) {
                $container->getDefinition('session.handler.write_check')->addArgument(new Reference($handlerId));
                $handlerId = 'session.handler.write_check';
            }

            $container->setAlias('session.handler', $handlerId);
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

        $container->setParameter('session.metadata.update_threshold', $config['metadata_update_threshold']);
    }

    /**
     * Loads the request configuration.
     *
     * @param array            $config    A request configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerRequestConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if ($config['formats']) {
            $loader->load('request.xml');
            $container
                ->getDefinition('request.add_request_formats_listener')
                ->replaceArgument(0, $config['formats'])
            ;
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

        if (!$container->hasParameter('templating.helper.code.file_link_format')) {
            $links = array(
                'textmate' => 'txmt://open?url=file://%%f&line=%%l',
                'macvim' => 'mvim://open?url=file://%%f&line=%%l',
                'emacs' => 'emacs://open?url=file://%%f&line=%%l',
                'sublime' => 'subl://open?url=file://%%f&line=%%l',
            );

            $container->setParameter('templating.helper.code.file_link_format', str_replace('%', '%%', ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format')) ?: (isset($links[$ide]) ? $links[$ide] : $ide));
        }

        $container->setParameter('fragment.renderer.hinclude.global_template', $config['hinclude_default_template']);

        if ($container->getParameter('kernel.debug')) {
            $logger = new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);

            $container->getDefinition('templating.loader.cache')
                ->addTag('monolog.logger', array('channel' => 'templating'))
                ->addMethodCall('setLogger', array($logger));
            $container->getDefinition('templating.loader.chain')
                ->addTag('monolog.logger', array('channel' => 'templating'))
                ->addMethodCall('setLogger', array($logger));
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

        $container->getDefinition('fragment.renderer.hinclude')
            ->addTag('kernel.fragment_renderer', array('alias' => 'hinclude'))
            ->replaceArgument(0, new Reference('templating'))
        ;

        // configure the PHP engine if needed
        if (in_array('php', $config['engines'], true)) {
            $loader->load('templating_php.xml');

            $container->setParameter('templating.helper.form.resources', $config['form']['resources']);

            if ($container->getParameter('kernel.debug')) {
                $loader->load('templating_debug.xml');

                $container->setDefinition('templating.engine.php', $container->findDefinition('debug.templating.engine.php'));
                $container->setAlias('debug.templating.engine.php', 'templating.engine.php');
            }

            $this->addClassesToCompile(array(
                'Symfony\\Component\\Templating\\Storage\\FileStorage',
                'Symfony\\Bundle\\FrameworkBundle\\Templating\\PhpEngine',
                'Symfony\\Bundle\\FrameworkBundle\\Templating\\Loader\\FilesystemLoader',
            ));

            $container->getDefinition('templating.helper.assets')->replaceArgument(0, new Reference('assets.packages'));
        }
    }

    /**
     * Loads the assets configuration.
     *
     * @param array            $config    A assets configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerAssetsConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('assets.xml');

        $defaultVersion = $this->createVersion($container, $config['version'], $config['version_format'], '_default');

        $defaultPackage = $this->createPackageDefinition($config['base_path'], $config['base_urls'], $defaultVersion);
        $container->setDefinition('assets._default_package', $defaultPackage);

        $namedPackages = array();
        foreach ($config['packages'] as $name => $package) {
            if (!array_key_exists('version', $package)) {
                $version = $defaultVersion;
            } else {
                $format = $package['version_format'] ?: $config['version_format'];
                $version = $this->createVersion($container, $package['version'], $format, $name);
            }

            $container->setDefinition('assets._package_'.$name, $this->createPackageDefinition($package['base_path'], $package['base_urls'], $version));
            $namedPackages[$name] = new Reference('assets._package_'.$name);
        }

        $container->getDefinition('assets.packages')
            ->replaceArgument(0, new Reference('assets._default_package'))
            ->replaceArgument(1, $namedPackages)
        ;
    }

    /**
     * Returns a definition for an asset package.
     */
    private function createPackageDefinition($basePath, array $baseUrls, Reference $version)
    {
        if ($basePath && $baseUrls) {
            throw new \LogicException('An asset package cannot have base URLs and base paths.');
        }

        if (!$baseUrls) {
            $package = new DefinitionDecorator('assets.path_package');

            return $package
                ->setPublic(false)
                ->replaceArgument(0, $basePath)
                ->replaceArgument(1, $version)
            ;
        }

        $package = new DefinitionDecorator('assets.url_package');

        return $package
            ->setPublic(false)
            ->replaceArgument(0, $baseUrls)
            ->replaceArgument(1, $version)
        ;
    }

    private function createVersion(ContainerBuilder $container, $version, $format, $name)
    {
        if (null === $version) {
            return new Reference('assets.empty_version_strategy');
        }

        $def = new DefinitionDecorator('assets.static_version_strategy');
        $def
            ->replaceArgument(0, $version)
            ->replaceArgument(1, $format)
        ;
        $container->setDefinition('assets._version_'.$name, $def);

        return new Reference('assets._version_'.$name);
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
        $this->translationConfigEnabled = true;

        // Use the "real" translator instead of the identity default
        $container->setAlias('translator', 'translator.default');
        $translator = $container->findDefinition('translator.default');
        $translator->addMethodCall('setFallbackLocales', array($config['fallbacks']));

        $container->setParameter('translator.logging', $config['logging']);

        // Discover translation directories
        $dirs = array();
        if (class_exists('Symfony\Component\Validator\Validation')) {
            $r = new \ReflectionClass('Symfony\Component\Validator\Validation');

            $dirs[] = dirname($r->getFileName()).'/Resources/translations';
        }
        if (class_exists('Symfony\Component\Form\Form')) {
            $r = new \ReflectionClass('Symfony\Component\Form\Form');

            $dirs[] = dirname($r->getFileName()).'/Resources/translations';
        }
        if (class_exists('Symfony\Component\Security\Core\Exception\AuthenticationException')) {
            $r = new \ReflectionClass('Symfony\Component\Security\Core\Exception\AuthenticationException');

            $dirs[] = dirname($r->getFileName()).'/../Resources/translations';
        }
        $rootDir = $container->getParameter('kernel.root_dir');
        foreach ($container->getParameter('kernel.bundles') as $bundle => $class) {
            $reflection = new \ReflectionClass($class);
            if (is_dir($dir = dirname($reflection->getFileName()).'/Resources/translations')) {
                $dirs[] = $dir;
            }
            if (is_dir($dir = $rootDir.sprintf('/Resources/%s/translations', $bundle))) {
                $dirs[] = $dir;
            }
        }

        foreach ($config['paths'] as $dir) {
            if (is_dir($dir)) {
                $dirs[] = $dir;
            } else {
                throw new \UnexpectedValueException(sprintf('%s defined in translator.paths does not exist or is not a directory', $dir));
            }
        }

        if (is_dir($dir = $rootDir.'/Resources/translations')) {
            $dirs[] = $dir;
        }

        // Register translation resources
        if ($dirs) {
            foreach ($dirs as $dir) {
                $container->addResource(new DirectoryResource($dir));
            }

            $files = array();
            $finder = Finder::create()
                ->files()
                ->filter(function (\SplFileInfo $file) {
                    return 2 === substr_count($file->getBasename(), '.') && preg_match('/\.\w+$/', $file->getBasename());
                })
                ->in($dirs)
            ;

            $locales = array();
            foreach ($finder as $file) {
                list($domain, $locale, $format) = explode('.', $file->getBasename(), 3);
                if (!isset($files[$locale])) {
                    $files[$locale] = array();
                }

                $files[$locale][] = (string) $file;
            }

            $options = array_merge(
                $translator->getArgument(3),
                array('resource_files' => $files)
            );

            $translator->replaceArgument(3, $options);
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

        $validatorBuilder = $container->getDefinition('validator.builder');

        $container->setParameter('validator.translation_domain', $config['translation_domain']);

        list($xmlMappings, $yamlMappings) = $this->getValidatorMappingFiles($container);
        if (count($xmlMappings) > 0) {
            $validatorBuilder->addMethodCall('addXmlMappings', array($xmlMappings));
        }

        if (count($yamlMappings) > 0) {
            $validatorBuilder->addMethodCall('addYamlMappings', array($yamlMappings));
        }

        $definition = $container->findDefinition('validator.email');
        $definition->replaceArgument(0, $config['strict_email']);

        if (array_key_exists('enable_annotations', $config) && $config['enable_annotations']) {
            $validatorBuilder->addMethodCall('enableAnnotationMapping', array(new Reference('annotation_reader')));
        }

        if (array_key_exists('static_method', $config) && $config['static_method']) {
            foreach ($config['static_method'] as $methodName) {
                $validatorBuilder->addMethodCall('addMethodMapping', array($methodName));
            }
        }

        if (isset($config['cache'])) {
            $container->setParameter(
                'validator.mapping.cache.prefix',
                'validator_'.$this->getKernelRootHash($container)
            );

            $validatorBuilder->addMethodCall('setMetadataCache', array(new Reference($config['cache'])));
        }
    }

    private function getValidatorMappingFiles(ContainerBuilder $container)
    {
        $files = array(array(), array());

        if (interface_exists('Symfony\Component\Form\FormInterface')) {
            $reflClass = new \ReflectionClass('Symfony\Component\Form\FormInterface');
            $files[0][] = dirname($reflClass->getFileName()).'/Resources/config/validation.xml';
            $container->addResource(new FileResource($files[0][0]));
        }

        $bundles = $container->getParameter('kernel.bundles');
        foreach ($bundles as $bundle) {
            $reflection = new \ReflectionClass($bundle);
            $dirname = dirname($reflection->getFileName());

            if (is_file($file = $dirname.'/Resources/config/validation.xml')) {
                $files[0][] = realpath($file);
                $container->addResource(new FileResource($file));
            }

            if (is_file($file = $dirname.'/Resources/config/validation.yml')) {
                $files[1][] = realpath($file);
                $container->addResource(new FileResource($file));
            }

            if (is_dir($dir = $dirname.'/Resources/config/validation')) {
                foreach (Finder::create()->files()->in($dir)->name('*.xml') as $file) {
                    $files[0][] = $file->getRealpath();
                }
                foreach (Finder::create()->files()->in($dir)->name('*.yml') as $file) {
                    $files[1][] = $file->getRealpath();
                }

                $container->addResource(new DirectoryResource($dir));
            }
        }

        return $files;
    }

    private function registerAnnotationsConfiguration(array $config, ContainerBuilder $container, $loader)
    {
        $loader->load('annotations.xml');

        if ('none' !== $config['cache']) {
            if ('file' === $config['cache']) {
                $cacheDir = $container->getParameterBag()->resolveValue($config['file_cache_dir']);
                if (!is_dir($cacheDir) && false === @mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
                    throw new \RuntimeException(sprintf('Could not create cache directory "%s".', $cacheDir));
                }

                $container
                    ->getDefinition('annotations.filesystem_cache')
                    ->replaceArgument(0, $cacheDir)
                ;
            }

            $container
                ->getDefinition('annotations.cached_reader')
                ->replaceArgument(1, new Reference('file' !== $config['cache'] ? $config['cache'] : 'annotations.filesystem_cache'))
                ->replaceArgument(2, $config['debug'])
            ;
            $container->setAlias('annotation_reader', 'annotations.cached_reader');
        }
    }

    private function registerPropertyAccessConfiguration(array $config, ContainerBuilder $container)
    {
        $container
            ->getDefinition('property_accessor')
            ->replaceArgument(0, $config['magic_call'])
            ->replaceArgument(1, $config['throw_exception_on_invalid_index'])
        ;
    }

    /**
     * Loads the security configuration.
     *
     * @param array            $config    A CSRF configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     *
     * @throws \LogicException
     */
    private function registerSecurityCsrfConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        if (!$this->sessionConfigEnabled) {
            throw new \LogicException('CSRF protection needs sessions to be enabled.');
        }

        // Enable services for CSRF protection (even without forms)
        $loader->load('security_csrf.xml');
    }

    /**
     * Loads the serializer configuration.
     *
     * @param array            $config    A serializer configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    private function registerSerializerConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$config['enabled']) {
            return;
        }

        $loader->load('serializer.xml');
        $chainLoader = $container->getDefinition('serializer.mapping.chain_loader');

        $serializerLoaders = array();
        if (isset($config['enable_annotations']) && $config['enable_annotations']) {
            $annotationLoader = new Definition(
                'Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader',
                 array(new Reference('annotation_reader'))
            );
            $annotationLoader->setPublic(false);

            $serializerLoaders[] = $annotationLoader;
        }

        $bundles = $container->getParameter('kernel.bundles');
        foreach ($bundles as $bundle) {
            $reflection = new \ReflectionClass($bundle);
            $dirname = dirname($reflection->getFileName());

            if (is_file($file = $dirname.'/Resources/config/serialization.xml')) {
                $definition = new Definition('Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader', array(realpath($file)));
                $definition->setPublic(false);

                $serializerLoaders[] = $definition;
                $container->addResource(new FileResource($file));
            }

            if (is_file($file = $dirname.'/Resources/config/serialization.yml')) {
                $definition = new Definition('Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader', array(realpath($file)));
                $definition->setPublic(false);

                $serializerLoaders[] = $definition;
                $container->addResource(new FileResource($file));
            }

            if (is_dir($dir = $dirname.'/Resources/config/serialization')) {
                foreach (Finder::create()->files()->in($dir)->name('*.xml') as $file) {
                    $definition = new Definition('Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader', array($file->getRealpath()));
                    $definition->setPublic(false);

                    $serializerLoaders[] = $definition;
                }
                foreach (Finder::create()->files()->in($dir)->name('*.yml') as $file) {
                    $definition = new Definition('Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader', array($file->getRealpath()));
                    $definition->setPublic(false);

                    $serializerLoaders[] = $definition;
                }

                $container->addResource(new DirectoryResource($dir));
            }
        }

        $chainLoader->replaceArgument(0, $serializerLoaders);

        if (isset($config['cache']) && $config['cache']) {
            $container->setParameter(
                'serializer.mapping.cache.prefix',
                'serializer_'.$this->getKernelRootHash($container)
            );

            $container->getDefinition('serializer.mapping.class_metadata_factory')->replaceArgument(
                1, new Reference($config['cache'])
            );
        }

        if (isset($config['name_converter']) && $config['name_converter']) {
            $container->getDefinition('serializer.normalizer.object')->replaceArgument(1, new Reference($config['name_converter']));
        }
    }

    /**
     * Loads property info configuration.
     *
     * @param array            $config
     * @param ContainerBuilder $container
     * @param XmlFileLoader    $loader
     */
    private function registerPropertyInfoConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$config['enabled']) {
            return;
        }

        $loader->load('property_info.xml');

        if (class_exists('phpDocumentor\Reflection\ClassReflector')) {
            $definition = $container->register('property_info.php_doc_extractor', 'Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor');
            $definition->addTag('property_info.description_extractor', array('priority' => -1000));
            $definition->addTag('property_info.type_extractor', array('priority' => -1001));
        }
    }

    /**
     * Gets a hash of the kernel root directory.
     *
     * @param ContainerBuilder $container
     *
     * @return string
     */
    private function getKernelRootHash(ContainerBuilder $container)
    {
        if (!$this->kernelRootHash) {
            $this->kernelRootHash = hash('sha256', $container->getParameter('kernel.root_dir'));
        }

        return $this->kernelRootHash;
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
