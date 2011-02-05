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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Resource\FileResource;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * FrameworkExtension.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
    public function configLoad(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');

        $loader->load('web.xml');
        $loader->load('form.xml');
        $loader->load('services.xml');

        if ($container->getParameter('kernel.debug')) {
            $loader->load('debug.xml');
            $container->setDefinition('event_dispatcher', $container->findDefinition('debug.event_dispatcher'));
            $container->setAlias('debug.event_dispatcher', 'event_dispatcher');
        }

        $config = $this->mergeConfigs($configs);

        $warmer = isset($config['cache_warmer']) ? $config['cache_warmer'] : !$container->getParameter('kernel.debug');
        $container->setParameter('kernel.cache_warmup', $warmer);

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
                $container->getDefinition('error_handler')->addMethodCall('register', array());
                $container->setParameter('error_handler.level', $config['error_handler']);
            }
        }

        if (isset($config['ide'])) {
            $patterns = array(
                'textmate' => 'txmt://open?url=file://%%f&line=%%l',
                'macvim'   => 'mvim://open?url=file://%%f&line=%%l',
            );
            $pattern = isset($patterns[$config['ide']]) ? $patterns[$config['ide']] : $config['ide'];
            $container->setParameter('debug.file_link_format', $pattern);
        }

        if ($config['test']) {
            $loader->load('test.xml');
            $config['session']['storage_id'] = 'array';
        }

        $this->registerCsrfProtectionConfiguration($config['csrf_protection'], $container);
        $this->registerEsiConfiguration($config['esi'], $loader);
        $this->registerProfilerConfiguration($config['profiler'], $container, $loader);
        $this->registerRouterConfiguration($config['router'], $container, $loader);
        $this->registerSessionConfiguration($config['session'], $container, $loader);
        $this->registerTemplatingConfiguration($config['templating'], $container, $loader);
        $this->registerTranslatorConfiguration($config['translator'], $container, $loader);
        $this->registerValidationConfiguration($config['validation'], $container, $loader);

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

            'Symfony\\Component\\EventDispatcher\\EventInterface',
            'Symfony\\Component\\EventDispatcher\\Event',
            'Symfony\\Component\\EventDispatcher\\EventDispatcherInterface',
            'Symfony\\Component\\EventDispatcher\\EventDispatcher',
            'Symfony\\Bundle\\FrameworkBundle\\EventDispatcher',
        ));
    }

    /**
     * Merges a set of configuration arrays and returns the result.
     *
     * This method internally specifies the available options and their
     * default values. Given an array of configuration arrays, this method
     * intelligently merges those configuration values and returns the final,
     * flattened product.
     *
     * @param array $configs An array of configuration arrays to merge
     * @return array The merged configuration array
     */
    protected function mergeConfigs(array $configs)
    {
        $defaultOptions = array(
            'cache_warmer' => null,
            'charset'      => null,
            'csrf_protection' => array(
                'enabled'    => null,
                'field_name' => null,
                'secret'     => null,
            ),
            'document_root' => null,
            'error_handler' => null,
            // TODO: consolidate into a scalar unless future options are planned 
            'esi' => array(
                'enabled' => null,
            ),
            'ide' => null,
            'profiler' => array(
                'enabled'         => false,
                'only_exceptions' => null,
                'matcher' => array(
                    'ip'      => null,
                    'path'    => null,
                    'service' => null,
                ),
            ),
            'router' => array(
                'cache_warmer' => null,
                'resource'     => null,
            ),
            'session' => array(
                'auto_start'      => null,
                'class'           => null,
                'default_locale'  => null,
                'storage_id'      => 'native',
                // NativeSessionStorage options
                'name'            => null,
                'lifetime'        => null,
                'path'            => null,
                'domain'          => null,
                'secure'          => null,
                'httponly'        => null,
                // PdoSessionStorage options
                'pdo.db_table'    => null,
                'pdo.db_id_col'   => null,
                'pdo.db_data_col' => null,
                'pdo.db_time_col' => null,
            ),
            'templating' => array(
                'assets_version'   => null,
                'assets_base_urls' => null,
                'cache'            => null,
                'cache_warmer'     => null,
                'engines'          => array(),
                'loaders'          => array(),
            ),
            'test' => null,
            'translator' => array(
                'enabled'  => null,
                'fallback' => null,
            ),
            'validation' => array(
                'enabled'     => null,
                'annotations' => null,
                'namespaces'  => array(),
            ),
        );

        $mergedConfig = $defaultOptions;

        foreach ($configs as $config) {
            $config = $this->normalizeKeys($config);

            if (isset($config['profiler'])) {
                $config['profiler']['enabled'] = true;
            }

            if (isset($config['templating']) && is_array($config['templating'])) {
                $config['templating']['engines'] = $this->normalizeConfig($config['templating'], 'engine');
                $config['templating']['loaders'] = $this->normalizeConfig($config['templating'], 'loader');
                unset($config['templating']['engine'], $config['templating']['loader']);
            }

            if (isset($config['validation']) && is_array($config['validation'])) {
                $config['validation']['namespaces'] = $this->normalizeConfig($config['validation'], 'namespace');
                unset($config['validation']['namespace']);
            }

            $mergedConfig = $this->mergeOptions($mergedConfig, $config, $defaultOptions);
        }

        return $mergedConfig;
    }

    /**
     * Merges a single level of configuration options.
     *
     * @param array  $current  The value of the options before merging
     * @param array  $new      The new values to be merged
     * @param array  $default  The corresponding default values for the option level
     * @param string $basePath Base property path for the option level
     * @return array The merged options
     * @throws InvalidArgumentException When an unsupported is found
     */
    protected function mergeOptions(array $current, array $new, array $default, $basePath = null)
    {
        if ($unsupportedOptions = array_diff_key($new, $default)) {
            throw new \InvalidArgumentException('The following options are not supported: '.implode(', ', array_keys($unsupportedOptions)));
        }

        foreach ($default as $key => $defaultValue) {
            if (array_key_exists($key, $new)) {
                $optionPath = $basePath ? $basePath.'.'.$key : $key;
                $current[$key] = $this->mergeOptionValue($current[$key], $new[$key], $defaultValue, $optionPath);
            }
        }

        return $current;
    }

    /**
     * Merges an option value.
     *
     * @param mixed  $current    The value of the option before merging
     * @param mixed  $new        The new value to be merged
     * @param mixed  $default    The corresponding default value for the option
     * @param string $optionPath Property path for the option
     * @return mixed The merged value
     * @throws InvalidArgumentException When an invalid option is found
     */
    protected function mergeOptionValue($current, $new, $defaultValue, $optionPath)
    {
        // Allow profiler.matcher array to be overridden with any value. This
        // option requires no merge logic and would not benefit from the type
        // validation below.
        if ('profiler.matcher' === $optionPath) {
            return $new;
        }

        // Ensure that the new value's type is an array if expected
        if (is_array($defaultValue) && !is_array($new)) {
            throw new \InvalidArgumentException(sprintf('Expected array type for option "%s", %s given', $optionPath, gettype($new)));
        }

        switch ($optionPath) {
            // Engine options are arrays of strings, although XML configurations
            // store the engine ID in an attribute. Dedupe after merging.
            case 'templating.engines':
                $new = array_map(function($engine) { return is_array($engine) ? $engine['id'] : $engine; }, $new);
                return array_unique(array_merge($current, $new));

            // Loader options are arrays of strings, so dedupe after merging
            case 'templating.loaders':
                return array_unique(array_merge($current, $new));

            // The namespace options' keys are used for the annotation prefix
            // and are significant, so do not dedupe array values.  Be mindful
            // of XML configurations, which store the prefix in an attribute.
            case 'validation.namespaces':
                foreach ($new as $prefix => $namespace) {
                    if (is_array($namespace)) {
                        $new[$namespace['prefix']] = $namespace['namespace'];
                        unset($new[$prefix]);
                    }
                }
                return array_merge($current, $new);
        }

        return is_array($defaultValue) ? $this->mergeOptions($current, $new, $defaultValue, $optionPath) : $new;
    }

    /**
     * Loads the CSRF protection configuration.
     *
     * @param array            $config    A CSRF protection configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function registerCsrfProtectionConfiguration(array $config, ContainerBuilder $container)
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
    protected function registerEsiConfiguration(array $config, XmlFileLoader $loader)
    {
        if ($config['enabled']) {
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
    protected function registerProfilerConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$config['enabled']) {
            return;
        }

        $loader->load('profiling.xml');
        $loader->load('collectors.xml');

        if (isset($config['only_exceptions'])) {
            $container->setParameter('profiler_listener.only_exceptions', $config['only_exceptions']);
        }

        if ($config['matcher']) {
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
    protected function registerRouterConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('routing.xml');

        if (!isset($config['resource'])) {
            throw new \InvalidArgumentException('Router configuration requires a resource option.');
        }

        $container->setParameter('routing.resource', $config['resource']);

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
            $container->findDefinition('router')->getClass()
        ));
    }

    /**
     * Loads the session configuration.
     *
     * @param array            $config    A session configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    protected function registerSessionConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('session.xml');

        if ($config['auto_start']) {
            $container->getDefinition('session')->addMethodCall('start');
        }

        if (isset($config['class'])) {
            $container->setParameter('session.class', $config['class']);
        }

        if (isset($config['default_locale'])) {
            $container->setParameter('session.default_locale', $config['default_locale']);
        }

        $container->setAlias('session.storage', 'session.storage.'.$config['storage_id']);

        $options = $container->getParameter('session.storage.'.$config['storage_id'].'.options');
        foreach (array('name', 'lifetime', 'path', 'domain', 'secure', 'httponly', 'pdo.db_table', 'pdo.db_id_col', 'pdo.db_data_col', 'pdo.db_time_col') as $key) {
            if (isset($config[$key])) {
                $options[str_replace('pdo.', '', $key)] = $config[$key];
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
    protected function registerTemplatingConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('templating.xml');
        $loader->load('templating_php.xml');

        if ($container->getParameter('kernel.debug')) {
            $loader->load('templating_debug.xml');
        }

        if (isset($config['assets_version'])) {
            $container->setParameter('templating.assets.version', $config['assets_version']);
        }

        if (isset($config['assets_base_urls'])) {
            $container->setParameter('templating.assets.base_urls', $config['assets_base_urls']);
        }

        if ($config['loaders']) {
            $loaders = array_map(function($loader) { return new Reference($loader); }, $config['loaders']);

            // Use a deligation unless only a single loader was registered
            if (1 === count($loaders)) {
                $container->setAlias('templating.loader', (string) reset($loaders));
            } else {
                $container->getDefinition('templating.loader.chain')->addArgument($loaders);
                $container->setAlias('templating.loader', 'templating.loader.chain');
            }
        }

        if ($config['cache']) {
            // Wrap the existing loader with cache (must happen after loaders are registered)
            $container->setDefinition('templating.loader.wrapped', $container->findDefinition('templating.loader'));
            $container->setDefinition('templating.loader', $container->getDefinition('templating.loader.cache'));
            $container->setParameter('templating.loader.cache.path', $config['cache']);
        } else {
            $container->setParameter('templating.loader.cache.path', null);
        }

        if ($config['cache_warmer']) {
            $container->getDefinition('templating.cache_warmer.template_paths')->addTag('kernel.cache_warmer');
            $container->setAlias('templating.locator', 'templating.locator.cached');
        }

        if (!$config['engines']) {
            throw new \LogicException('You must register at least one templating engine.');
        }

        $this->addClassesToCompile(array(
            'Symfony\\Bundle\\FrameworkBundle\\Templating\\EngineInterface',
            'Symfony\\Component\\Templating\\EngineInterface',
            'Symfony\\Bundle\\FrameworkBundle\\Templating\\Loader\\TemplateLocatorInterface',
            $container->findDefinition('templating.locator')->getClass(),
        ));

        if (in_array('php', $config['engines'], true)) {
            $this->addClassesToCompile(array(
                'Symfony\\Component\\Templating\\PhpEngine',
                'Symfony\\Component\\Templating\\TemplateNameParserInterface',
                'Symfony\\Component\\Templating\\TemplateNameParser',
                'Symfony\\Component\\Templating\\Loader\\LoaderInterface',
                'Symfony\\Component\\Templating\\Storage\\Storage',
                'Symfony\\Component\\Templating\\Storage\\FileStorage',
                'Symfony\\Bundle\\FrameworkBundle\\Templating\\PhpEngine',
                'Symfony\\Bundle\\FrameworkBundle\\Templating\\TemplateNameParser',
                'Symfony\\Bundle\\FrameworkBundle\\Templating\\Loader\\FilesystemLoader',
            ));
        }

        $engines = array_map(function($engine) { return new Reference('templating.engine.'.$engine); }, $config['engines']);

        // Use a deligation unless only a single engine was registered
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
     * A translator must always be registered (as support is included by default
     * in the forms component).  If disabled, an identity translator will be
     * used and everything will still work as expected.
     *
     * @param array            $config    A translator configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    protected function registerTranslatorConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('translation.xml');

        if ($config['enabled']) {
            // Use the "real" translator instead of the identity default
            $container->setDefinition('translator', $container->findDefinition('translator.real'));

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

        if (isset($config['fallback'])) {
            $container->setParameter('translator.fallback_locale', $config['fallback']);
        }
    }

    /**
     * Loads the validator configuration.
     *
     * @param array            $config    A validation configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param XmlFileLoader    $loader    An XmlFileLoader instance
     */
    protected function registerValidationConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if ($config['enabled']) {
            $loader->load('validator.xml');

            $xmlMappingFiles = array();
            $yamlMappingFiles = array();

            // Include default entries from the framework
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

            $xmlFilesLoader = new Definition('%validator.mapping.loader.xml_files_loader.class%', array($xmlMappingFiles));
            $xmlFilesLoader->setPublic(false);

            $yamlFilesLoader = new Definition('%validator.mapping.loader.yaml_files_loader.class%', array($yamlMappingFiles));
            $yamlFilesLoader->setPublic(false);

            $container->setDefinition('validator.mapping.loader.xml_files_loader', $xmlFilesLoader);
            $container->setDefinition('validator.mapping.loader.yaml_files_loader', $yamlFilesLoader);

            foreach ($xmlMappingFiles as $file) {
                $container->addResource(new FileResource($file));
            }

            foreach ($yamlMappingFiles as $file) {
                $container->addResource(new FileResource($file));
            }

            if ($config['annotations']) {
                // Register prefixes for constraint namespaces
                if ($namespaces = $config['namespaces']) {
                    $container->setParameter('validator.annotations.namespaces', array_merge(
                        $container->getParameter('validator.annotations.namespaces'),
                        $namespaces
                    ));
                }

                // Register annotation loader
                $annotationLoader = new Definition('%validator.mapping.loader.annotation_loader.class%');
                $annotationLoader->setPublic(false);
                $annotationLoader->addArgument(new Parameter('validator.annotations.namespaces'));

                $container->setDefinition('validator.mapping.loader.annotation_loader', $annotationLoader);

                $loaderChain = $container->getDefinition('validator.mapping.loader.loader_chain');
                $arguments = $loaderChain->getArguments();
                array_unshift($arguments[0], new Reference('validator.mapping.loader.annotation_loader'));
                $loaderChain->setArguments($arguments);
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
