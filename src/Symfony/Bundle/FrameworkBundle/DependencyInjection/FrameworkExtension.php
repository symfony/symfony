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

use Composer\InstalledVersions;
use Doctrine\ORM\Mapping\Embeddable;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\MappedSuperclass;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Bridge\Twig\Extension\CsrfExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Bundle\FullStack;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsTargetedValueResolver as AsTargetedConsoleValueResolver;
use Symfony\Component\Console\Messenger\RunCommandMessageHandler;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapperInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestHeaderValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\EventListener\ProfilerListener;
use Symfony\Component\HttpKernel\Log\DebugLoggerConfigurator;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\String\LazyString;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Translation\Command\TranslationLintCommand as BaseTranslationLintCommand;
use Symfony\Component\Translation\Command\XliffLintCommand as BaseXliffLintCommand;
use Symfony\Component\Translation\Command\XliffUpdateSourcesCommand;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Command\LintCommand as BaseYamlLintCommand;
use Symfony\Component\Yaml\Schema\SchemaResolverInterface;
use Symfony\Contracts\Cache\CallbackInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * Process the configuration and prepare the dependency injection container with
 * parameters and services.
 */
class FrameworkExtension extends Extension
{
    private array $configsEnabled = [];

    /**
     * Responds to the app.config configuration parameter.
     *
     * @throws LogicException
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        if (!$container instanceof MergeExtensionConfigurationContainerBuilder && !$container->hasDefinition('parameter_bag')) {
            trigger_deprecation('symfony/framework-bundle', '8.1', 'Loading "%s" without first loading "%s" is deprecated; call "new %s()->getContainerExtension()->load([], $container);" before "%s::load()".', self::class, ServicesBundle::class, ServicesBundle::class, self::class);

            new ServicesBundle()->getContainerExtension()->load([], $container);
        }

        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));

        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('symfony/symfony') && 'symfony/symfony' !== (InstalledVersions::getRootPackage()['name'] ?? '')) {
            throw new \LogicException('Requiring the "symfony/symfony" package is unsupported; replace it with standalone components instead.');
        }

        // ValidationBundle overrides this with the configured domain; the default has to exist either
        // way, because services such as argument_resolver.request_payload reference it whether or not
        // validation is enabled
        $container->setParameter('validator.translation_domain', 'validators');

        $loader->load('web.php');
        $loader->load('services.php');
        $loader->load('fragment_renderer.php');
        $loader->load('error_renderer.php');

        if (!class_exists(RequestHeaderValueResolver::class)) {
            $container->removeDefinition('argument_resolver.header_value_resolver');
        }
        if (!class_exists(ControllerAttributesListener::class)) {
            $container->removeDefinition('kernel.controller_attributes_listener');
            $container->removeDefinition('serialize_controller_result_listener');
        }

        if (!ContainerBuilder::willBeAvailable('symfony/expression-language', ExpressionLanguage::class, ['symfony/framework-bundle'])) {
            $container->removeDefinition('controller.expression_language');
        }

        if ($this->hasConsole()) {
            $loader->load('console.php');

            if (!class_exists(BaseXliffLintCommand::class)) {
                $container->removeDefinition('console.command.xliff_lint');
            }
            if (!class_exists(BaseYamlLintCommand::class)) {
                $container->removeDefinition('console.command.yaml_lint');
            } elseif (!ContainerBuilder::willBeAvailable('symfony/yaml', SchemaResolverInterface::class, ['symfony/framework-bundle'])) {
                $container->getDefinition('console.command.yaml_lint')->setArguments([]);
            } elseif ($container->hasParameter('.kernel.config_dir')) {
                $container->getDefinition('console.command.yaml_lint')->getArgument(0)->replaceArgument(0, $container->getParameter('.kernel.config_dir'));
            }

            if (!class_exists(BaseTranslationLintCommand::class)) {
                $container->removeDefinition('console.command.translation_lint');
            }

            if (!class_exists(XliffUpdateSourcesCommand::class)) {
                $container->removeDefinition('console.command.translation_xliff_update_sources');
            }

            if (!class_exists(RunCommandMessageHandler::class)) {
                $container->removeDefinition('console.messenger.application');
                $container->removeDefinition('console.messenger.execute_command_handler');
            }
        }

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        // warmup config enabled
        $this->readConfigEnabled('profiler', $container, $config['profiler']);

        $container->getDefinition('locale_listener')->replaceArgument(3, $config['set_locale_from_accept_language']);
        $container->getDefinition('response_listener')->replaceArgument(1, $config['set_content_language_from_locale']);
        $container->getDefinition('http_kernel')->replaceArgument(4, $config['handle_all_throwables'] ?? false);

        // If the slugger is used but the String component is not available, we should throw an error
        if (!ContainerBuilder::willBeAvailable('symfony/string', SluggerInterface::class, ['symfony/framework-bundle'])) {
            $container->register('slugger', SluggerInterface::class)
                ->addError('You cannot use the "slugger" service since the String component is not installed. Try running "composer require symfony/string".');
        } else {
            if (!ContainerBuilder::willBeAvailable('symfony/translation', LocaleAwareInterface::class, ['symfony/framework-bundle'])) {
                $container->register('slugger', SluggerInterface::class)
                    ->addError('You cannot use the "slugger" service since the Translation contracts are not installed. Try running "composer require symfony/translation".');
            }

            if (!\extension_loaded('intl') && !\defined('PHPUNIT_COMPOSER_INSTALL')) {
                trigger_deprecation('', '', 'Please install the "intl" PHP extension for best performance.');
            }
        }

        $emptySecretHint = '"framework.secret" option';
        if (isset($config['secret'])) {
            $container->setParameter('kernel.secret', $config['secret']);
            $usedEnvs = [];
            $container->resolveEnvPlaceholders($config['secret'], null, $usedEnvs);

            if ($usedEnvs) {
                $emptySecretHint = \sprintf('"%s" env var%s', implode('", "', $usedEnvs), 1 === \count($usedEnvs) ? '' : 's');
            }
        }
        $container->parameterCannotBeEmpty('kernel.secret', 'A non-empty value for the parameter "kernel.secret" is required. Did you forget to configure the '.$emptySecretHint.'?');

        $container->setParameter('kernel.http_method_override', $config['http_method_override']);
        $container->setParameter('kernel.allowed_http_method_override', $config['allowed_http_method_override']);
        $container->setParameter('kernel.trust_x_sendfile_type_header', $config['trust_x_sendfile_type_header']);
        $container->setParameter('kernel.trusted_hosts', [0] === array_keys($config['trusted_hosts']) ? $config['trusted_hosts'][0] : $config['trusted_hosts']);
        $container->setParameter('kernel.default_locale', $config['default_locale']);
        $container->setParameter('kernel.enabled_locales', $config['enabled_locales']);
        $container->setParameter('kernel.error_controller', $config['error_controller']);

        if (($config['trusted_proxies'] ?? false) && ($config['trusted_headers'] ?? false)) {
            $container->setParameter('kernel.trusted_proxies', \is_array($config['trusted_proxies']) && [0] === array_keys($config['trusted_proxies']) ? $config['trusted_proxies'][0] : $config['trusted_proxies']);
            $container->setParameter('kernel.trusted_headers', [0] === array_keys($config['trusted_headers']) ? $config['trusted_headers'][0] : $config['trusted_headers']);
        }

        if (!$container->hasParameter('debug.file_link_format')) {
            $container->setParameter('debug.file_link_format', $config['ide']);
        }

        if (!empty($config['test'])) {
            $loader->load('test.php');

            if (!class_exists(AbstractBrowser::class)) {
                $container->removeDefinition('test.client');
            }
        }

        if ($this->readConfigEnabled('request', $container, $config['request'])) {
            $this->registerRequestConfiguration($config['request'], $container, $loader);
        }

        $this->registerHttpCacheConfiguration($config['http_cache'], $container, $config['http_method_override'], $config['allowed_http_method_override']);
        $this->registerEsiConfiguration($config['esi'], $container, $loader);
        $this->registerSsiConfiguration($config['ssi'], $container, $loader);
        $this->registerFragmentsConfiguration($config['fragments'], $container, $loader);
        $container->getDefinition('uri_signer')->addArgument($config['uri_signer']['expiration']);
        $this->registerDebugConfiguration($config['php_errors'], $container, $loader);
        $this->registerSecretsConfiguration($config['secrets'], $container, $loader, $config['secret'] ?? null);

        $exceptionListener = $container->getDefinition('exception_listener');

        $loggers = [];
        foreach ($config['exceptions'] as $exception) {
            if (!isset($exception['log_channel'])) {
                continue;
            }
            $loggers[$exception['log_channel']] = new Reference('monolog.logger.'.$exception['log_channel'], ContainerInterface::NULL_ON_INVALID_REFERENCE);
        }

        $exceptionListener
            ->replaceArgument(3, $config['exceptions'])
            ->setArgument(4, $loggers)
        ;

        if ($this->readConfigEnabled('session', $container, $config['session'])) {
            if (!\extension_loaded('session')) {
                throw new LogicException('Session support cannot be enabled as the session extension is not installed. See https://php.net/session.installation for instructions.');
            }

            $this->registerSessionConfiguration($config['session'], $container, $loader);
            if (!empty($config['test'])) {
                // test listener will replace the existing session listener
                // as we are aliasing to avoid duplicated registered events
                $container->setAlias('session_listener', 'test.session.listener');
            }
        } elseif (!empty($config['test'])) {
            $container->removeDefinition('test.session.listener');
        }

        // csrf depends on session or stateless token ids being registered
        if (null === $config['csrf_protection']['enabled']) {
            $this->writeConfigEnabled('csrf_protection', ($config['csrf_protection']['stateless_token_ids'] || $this->readConfigEnabled('session', $container, $config['session'])) && !class_exists(FullStack::class) && ContainerBuilder::willBeAvailable('symfony/security-csrf', CsrfTokenManagerInterface::class, ['symfony/framework-bundle']), $config['csrf_protection']);
        }
        $this->registerSecurityCsrfConfiguration($config['csrf_protection'], $container, $loader);

        // form depends on csrf being registered
        if ($this->readConfigEnabled('form', $container, $config['form'])) {
            if (!class_exists(Form::class)) {
                throw new LogicException('Form support cannot be enabled as the Form component is not installed. Try running "composer require symfony/form".');
            }

            $this->registerFormConfiguration($config, $container, $loader);

            if (!ContainerBuilder::willBeAvailable('symfony/validator', Validation::class, ['symfony/framework-bundle', 'symfony/form'])) {
                $container->removeDefinition('form.type_extension.form.validator');
                $container->removeDefinition('form.type_guesser.validator');
            }
        } else {
            $container->removeDefinition('console.command.form_debug');
        }

        // profiler depends on form, validation, translation and serializer being registered
        $this->registerProfilerConfiguration($config['profiler'], $container, $loader);

        $container->registerForAutoconfiguration(CallbackInterface::class)
            ->addTag('container.reversible');
        $container->registerForAutoconfiguration(ValueResolverInterface::class)
            ->addTag('controller.argument_value_resolver');
        $container->registerForAutoconfiguration(AbstractController::class)
            ->addTag('controller.service_arguments');
        $container->registerForAutoconfiguration(DataCollectorInterface::class)
            ->addTag('data_collector');
        $container->registerForAutoconfiguration(FormTypeInterface::class)
            ->addTag('form.type', ['csrf_token_id' => '%.form.type_extension.csrf.token_id%']);
        $container->registerForAutoconfiguration(FormTypeGuesserInterface::class)
            ->addTag('form.type_guesser');
        $container->registerForAutoconfiguration(FormTypeExtensionInterface::class)
            ->addTag('form.type_extension');
        $container->registerForAutoconfiguration(CacheClearerInterface::class)
            ->addTag('kernel.cache_clearer');
        $container->registerForAutoconfiguration(CacheWarmerInterface::class)
            ->addTag('kernel.cache_warmer');
        $container->registerForAutoconfiguration(LocaleAwareInterface::class)
            ->addTag('kernel.locale_aware');

        $container->registerAttributeForAutoconfiguration(AsController::class, static function (ChildDefinition $definition, AsController $attribute): void {
            $definition->addTag('controller.service_arguments');
        });
        $container->registerAttributeForAutoconfiguration(Route::class, static function (ChildDefinition $definition, Route $attribute, \ReflectionClass|\ReflectionMethod $reflection): void {
            $definition->addTag('controller.service_arguments')->addTag('routing.controller');
        });
        $container->registerAttributeForAutoconfiguration(AsTargetedValueResolver::class, static function (ChildDefinition $definition, AsTargetedValueResolver $attribute): void {
            $definition->addTag('controller.targeted_value_resolver', $attribute->name ? ['name' => $attribute->name] : []);
        });
        $container->registerAttributeForAutoconfiguration(AsTargetedConsoleValueResolver::class, static function (ChildDefinition $definition, AsTargetedConsoleValueResolver $attribute): void {
            $definition->addTag('console.targeted_value_resolver', $attribute->name ? ['name' => $attribute->name] : []);
        });

        $container->registerAttributeForAutoconfiguration(Entity::class, static function (ChildDefinition $definition) {
            $definition->addTag('container.excluded', ['source' => 'because it\'s a Doctrine entity'])->addTag('doctrine.orm.entity');
        });
        $container->registerAttributeForAutoconfiguration(Embeddable::class, static function (ChildDefinition $definition) {
            $definition->addTag('container.excluded', ['source' => 'because it\'s a Doctrine embeddable']);
        });
        $container->registerAttributeForAutoconfiguration(MappedSuperclass::class, static function (ChildDefinition $definition) {
            $definition->addTag('container.excluded', ['source' => 'because it\'s a Doctrine mapped superclass'])->addTag('doctrine.orm.entity');
        });

        if (!$config['disallow_search_engine_index']) {
            $container->removeDefinition('disallow_search_engine_index_response_listener');
        }

        $container->registerForAutoconfiguration(RouteLoaderInterface::class)
            ->addTag('routing.route_loader');

    }

    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return new Configuration($container->getParameter('kernel.debug'));
    }

    protected function hasConsole(): bool
    {
        return class_exists(Application::class);
    }

    private function registerFormConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('form.php');

        if (!property_exists(ValidatorExtension::class, 'violationMapper')) {
            $container->removeDefinition('form.violation_mapper');
            $container->removeAlias(ViolationMapperInterface::class);
            $container->getDefinition('form.type_extension.form.validator')->replaceArgument(1, false);
        }
        if (null === $config['form']['csrf_protection']['enabled']) {
            $this->writeConfigEnabled('form.csrf_protection', $config['csrf_protection']['enabled'], $config['form']['csrf_protection']);
        }

        if ($this->readConfigEnabled('form.csrf_protection', $container, $config['form']['csrf_protection'])) {
            if (!$container->hasDefinition('security.csrf.token_generator')) {
                throw new \LogicException('To use form CSRF protection, "framework.csrf_protection" must be enabled.');
            }

            $loader->load('form_csrf.php');

            $container->setParameter('form.type_extension.csrf.enabled', true);
            $container->setParameter('form.type_extension.csrf.field_name', $config['form']['csrf_protection']['field_name']);
            $container->setParameter('form.type_extension.csrf.field_attr', $config['form']['csrf_protection']['field_attr']);
            $container->setParameter('.form.type_extension.csrf.token_id', $config['form']['csrf_protection']['token_id']);
        } else {
            $container->setParameter('form.type_extension.csrf.enabled', false);
        }

        $container->registerAttributeForAutoconfiguration(AsFormType::class, static function (ChildDefinition $definition) {
            $definition->addResourceTag('form.data_class');
        });

        if (!ContainerBuilder::willBeAvailable('symfony/translation', Translator::class, ['symfony/framework-bundle', 'symfony/form'])) {
            $container->removeDefinition('form.type_extension.upload.validator');
        }
    }

    private function registerHttpCacheConfiguration(array $config, ContainerBuilder $container, bool $httpMethodOverride, ?array $allowedHttpMethodOverride): void
    {
        $options = $config;
        unset($options['enabled']);

        if (!$options['private_headers']) {
            unset($options['private_headers']);
        }

        if (!$options['skip_response_headers']) {
            unset($options['skip_response_headers']);
        }

        $container->getDefinition('http_cache')
            ->setPublic($config['enabled'])
            ->replaceArgument(3, $options);

        if ($httpMethodOverride) {
            $container->getDefinition('http_cache')
                  ->addArgument((new Definition('void'))
                      ->setFactory([Request::class, 'enableHttpMethodParameterOverride'])
                  );
        }

        if (null !== $allowedHttpMethodOverride) {
            $container->getDefinition('http_cache')
                    ->addArgument((new Definition('void'))
                        ->setFactory([Request::class, 'setAllowedHttpMethodOverride'])
                        ->addArgument($allowedHttpMethodOverride)
                    );
        }
    }

    private function registerEsiConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$this->readConfigEnabled('esi', $container, $config)) {
            $container->removeDefinition('fragment.renderer.esi');

            return;
        }

        $loader->load('esi.php');
    }

    private function registerSsiConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$this->readConfigEnabled('ssi', $container, $config)) {
            $container->removeDefinition('fragment.renderer.ssi');

            return;
        }

        $loader->load('ssi.php');
    }

    private function registerFragmentsConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$this->readConfigEnabled('fragments', $container, $config)) {
            $container->removeDefinition('fragment.renderer.hinclude');

            return;
        }

        $container->getDefinition('fragment.renderer.hinclude')
            ->replaceArgument(2, $config['hinclude_default_template']);

        $container->setParameter('fragment.renderer.hinclude.global_template', $config['hinclude_default_template']);
        $container->deprecateParameter('fragment.renderer.hinclude.global_template', 'symfony/framework-bundle', '8.2', 'The "%s" parameter is deprecated. It will be removed in version 9.0.');

        $loader->load('fragment_listener.php');
        $container->setParameter('fragment.path', $config['path']);
    }

    private function registerProfilerConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$this->readConfigEnabled('profiler', $container, $config)) {
            // this is needed for the WebProfiler to work even if the profiler is disabled
            $container->setParameter('data_collector.templates', []);

            return;
        }

        $loader->load('profiling.php');
        $loader->load('collectors.php');
        $loader->load('cache_debug.php');

        if ($this->isInitializedConfigEnabled('form')) {
            $loader->load('form_debug.php');
        }

        $container->setParameter('profiler_listener.only_exceptions', $config['only_exceptions']);
        $container->setParameter('profiler_listener.only_main_requests', $config['only_main_requests']);

        // Choose storage class based on the DSN
        [$class] = explode(':', $config['dsn'], 2);
        if ('file' !== $class) {
            throw new \LogicException(\sprintf('Driver "%s" is not supported for the profiler.', $class));
        }

        $container->setParameter('profiler.storage.dsn', $config['dsn']);

        $container->getDefinition('profiler')
            ->addArgument($config['collect'])
            ->addTag('kernel.reset', ['method' => 'reset']);

        if (($config['excluded_paths'] || $config['excluded_http_codes']) && 8 > (new \ReflectionMethod(ProfilerListener::class, '__construct'))->getNumberOfParameters()) {
            throw new LogicException('Excluding requests from the profiler cannot be enabled as this version of the HttpKernel component does not support it. Try upgrading "symfony/http-kernel".');
        }

        $container->getDefinition('profiler_listener')
            ->addArgument($config['collect_parameter'])
            ->addArgument($config['excluded_paths'])
            ->addArgument($config['excluded_http_codes']);

        if (!$container->getParameter('kernel.debug') || !$this->hasConsole() || !$container->has('debug.stopwatch')) {
            $container->removeDefinition('console_profiler_listener');
        }

        if (!$this->hasConsole()) {
            $container->removeDefinition('.data_collector.command');
        }
    }

    private function registerDebugConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('debug_prod.php');

        $debug = $container->getParameter('kernel.debug');

        if (class_exists(Stopwatch::class)) {
            $container->register('debug.stopwatch', Stopwatch::class)
                ->addArgument(true)
                ->setPublic($debug)
                ->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(Stopwatch::class, new Alias('debug.stopwatch', false));
        }

        if ($debug && !$container->hasParameter('debug.container.dump')) {
            $container->setParameter('debug.container.dump', '%kernel.build_dir%/%kernel.container_class%.xml');
        }

        if ($debug && class_exists(Stopwatch::class)) {
            $loader->load('debug.php');

            if (!$this->hasConsole()) {
                $container->removeDefinition('debug.console.argument_resolver');
            }
        }

        $definition = $container->findDefinition('debug.error_handler_configurator');

        if (false === $config['log']) {
            $definition->replaceArgument(0, null);
        } elseif (true !== $config['log']) {
            $definition->replaceArgument(1, $config['log']);
        }

        if (!$config['throw']) {
            $container->setParameter('debug.error_handler.throw_at', 0);
        }

        if ($debug && class_exists(DebugProcessor::class)) {
            $definition = new Definition(DebugProcessor::class);
            $definition->addArgument(new Reference('.virtual_request_stack'));
            $definition->addTag('kernel.reset', ['method' => 'reset']);
            $container->setDefinition('debug.log_processor', $definition);

            $container->register('debug.debug_logger_configurator', DebugLoggerConfigurator::class)
                ->setArguments([new Reference('debug.log_processor'), '%kernel.runtime_mode.web%']);
        }
    }


    private function registerSessionConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('session.php');

        // session storage
        $container->setAlias('session.storage.factory', $config['storage_factory_id']);

        $options = ['cache_limiter' => '0'];
        foreach (['name', 'cookie_lifetime', 'cookie_path', 'cookie_domain', 'cookie_secure', 'cookie_httponly', 'cookie_samesite', 'use_cookies', 'gc_maxlifetime', 'gc_probability', 'gc_divisor'] as $key) {
            if (isset($config[$key])) {
                $options[$key] = $config[$key];
            }
        }

        if ('auto' === ($options['cookie_secure'] ?? null)) {
            $container->getDefinition('session.storage.factory.native')->replaceArgument(3, true);
            $container->getDefinition('session.storage.factory.php_bridge')->replaceArgument(2, true);
        }

        $container->setParameter('session.storage.options', $options);
        $container->setParameter('session.metadata.cookie_lifetime', $options['cookie_lifetime'] ?? null);

        // session handler (the internal callback registered with PHP session management)
        if (null === ($config['handler_id'] ?? $config['save_path'] ?? null)) {
            $config['save_path'] = null;
            $container->setAlias('session.handler', 'session.handler.native');
        } else {
            $config['handler_id'] ??= 'session.handler.native_file';

            if (!\array_key_exists('save_path', $config)) {
                $config['save_path'] = '%kernel.cache_dir%/sessions';
            }
            $container->resolveEnvPlaceholders($config['handler_id'], null, $usedEnvs);

            if ($usedEnvs || str_contains($config['handler_id'], '://')) {
                $container->getDefinition('session.abstract_handler')
                    ->replaceArgument(0, $config['handler_id']);

                $container->setAlias('session.handler', 'session.abstract_handler');
            } else {
                $container->setAlias('session.handler', $config['handler_id']);
            }
        }

        $container->setParameter('session.save_path', $config['save_path']);

        $container->setParameter('session.metadata.update_threshold', $config['metadata_update_threshold']);
    }

    private function registerRequestConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if ($config['formats']) {
            $loader->load('request.php');

            $listener = $container->getDefinition('request.add_request_formats_listener');
            $listener->replaceArgument(0, $config['formats']);
        }
    }


    /**
     * Returns a definition for an asset package.
     */


    /**
     * @param-immediately-invoked-callable $fileRecorder
     */
    private function registerMappingFilesFromDir(string $dir, callable $fileRecorder): void
    {
        foreach (Finder::create()->followLinks()->files()->in($dir)->name('/\.(xml|ya?ml)$/')->sortByName() as $file) {
            $fileRecorder($file->getExtension(), $file->getRealPath());
        }
    }

    /**
     * @param-immediately-invoked-callable $fileRecorder
     */
    private function registerMappingFilesFromConfig(ContainerBuilder $container, array $config, callable $fileRecorder): void
    {
        foreach ($config['mapping']['paths'] as $path) {
            if (is_dir($path)) {
                $this->registerMappingFilesFromDir($path, $fileRecorder);
                $container->addResource(new DirectoryResource($path, '/^$/'));
            } elseif ($container->fileExists($path, false)) {
                if (!preg_match('/\.(xml|ya?ml)$/', $path, $matches)) {
                    throw new \RuntimeException(\sprintf('Unsupported mapping type in "%s", supported types are XML & Yaml.', $path));
                }
                $fileRecorder($matches[1], $path);
            } else {
                throw new \RuntimeException(\sprintf('Could not open file or directory "%s".', $path));
            }
        }
    }

    private function registerSecretsConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader, ?string $secret): void
    {
        if (!$this->readConfigEnabled('secrets', $container, $config)) {
            $container->removeDefinition('console.command.secrets_set');
            $container->removeDefinition('console.command.secrets_list');
            $container->removeDefinition('console.command.secrets_reveal');
            $container->removeDefinition('console.command.secrets_remove');
            $container->removeDefinition('console.command.secrets_generate_key');
            $container->removeDefinition('console.command.secrets_decrypt_to_local');
            $container->removeDefinition('console.command.secrets_encrypt_from_local');

            return;
        }

        $loader->load('secrets.php');

        $container->resolveEnvPlaceholders($secret, null, $usedEnvs);
        $secretEnvVar = 1 === \count($usedEnvs ?? []) ? substr(key($usedEnvs), 1 + (strrpos(key($usedEnvs), ':') ?: -1)) : null;
        $container->getDefinition('secrets.vault')->replaceArgument(2, $secretEnvVar);
        $container->getDefinition('secrets.vault')->replaceArgument(0, $config['vault_directory']);

        if ($config['local_dotenv_file']) {
            $container->getDefinition('secrets.local_vault')->replaceArgument(0, $config['local_dotenv_file']);
        } else {
            $container->removeDefinition('secrets.local_vault');
        }

        if ($config['decryption_env_var']) {
            if (!preg_match('/^(?:[-.\w\\\\]*+:)*+[\w.]++$/', $config['decryption_env_var'])) {
                throw new InvalidArgumentException(\sprintf('Invalid value "%s" set as "decryption_env_var": only "word" and dot characters are allowed.', $config['decryption_env_var']));
            }

            if (ContainerBuilder::willBeAvailable('symfony/string', LazyString::class, ['symfony/framework-bundle'])) {
                $container->getDefinition('secrets.decryption_key')->replaceArgument(1, $config['decryption_env_var']);
            } else {
                $container->getDefinition('secrets.vault')->replaceArgument(1, "%env({$config['decryption_env_var']})%");
                $container->removeDefinition('secrets.decryption_key');
            }
        } else {
            $container->getDefinition('secrets.vault')->replaceArgument(1, null);
            $container->removeDefinition('secrets.decryption_key');
        }
    }

    private function registerSecurityCsrfConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$this->readConfigEnabled('csrf_protection', $container, $config)) {
            return;
        }

        if (!class_exists(CsrfToken::class)) {
            throw new LogicException('CSRF support cannot be enabled as the Security CSRF component is not installed. Try running "composer require symfony/security-csrf".');
        }
        if (!$config['stateless_token_ids'] && !$this->isInitializedConfigEnabled('session')) {
            throw new \LogicException('CSRF protection needs sessions to be enabled.');
        }

        // Enable services for CSRF protection (even without forms)
        $loader->load('security_csrf.php');

        if (!class_exists(CsrfExtension::class)) {
            $container->removeDefinition('twig.extension.security_csrf');
        }

        if (!$config['stateless_token_ids']) {
            $container->removeDefinition('security.csrf.same_origin_token_manager');
            $container->removeDefinition('security.csrf.same_origin_listener');

            return;
        }

        $container->getDefinition('security.csrf.same_origin_token_manager')
            ->replaceArgument(3, $config['stateless_token_ids'])
            ->replaceArgument(4, $config['check_header'])
            ->replaceArgument(5, $config['cookie_name']);

        $container->getDefinition('security.csrf.same_origin_listener')
            ->replaceArgument(0, $config['cookie_name']);

        if (!$this->isInitializedConfigEnabled('session')) {
            $container->setAlias('security.csrf.token_manager', 'security.csrf.same_origin_token_manager');
            $container->getDefinition('security.csrf.same_origin_token_manager')
                ->setDecoratedService(null)
                ->replaceArgument(2, null);
        }
    }


    protected function isConfigEnabled(ContainerBuilder $container, array $config): bool
    {
        throw new \LogicException('To prevent using outdated configuration, you must use the "readConfigEnabled" method instead.');
    }

    private function isInitializedConfigEnabled(string $path): bool
    {
        if (isset($this->configsEnabled[$path])) {
            return $this->configsEnabled[$path];
        }

        throw new LogicException(\sprintf('Can not read config enabled at "%s" because it has not been initialized.', $path));
    }

    private function readConfigEnabled(string $path, ContainerBuilder $container, array $config): bool
    {
        return $this->configsEnabled[$path] ??= parent::isConfigEnabled($container, $config);
    }

    private function writeConfigEnabled(string $path, bool $value, array &$config): void
    {
        if (isset($this->configsEnabled[$path])) {
            throw new LogicException('Can not change config enabled because it has already been read.');
        }

        $this->configsEnabled[$path] = $value;
        $config['enabled'] = $value;
    }
}
