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
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\Types\ContextFactory;
use PhpParser\Parser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use Psr\Http\Client\ClientInterface;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Bridge\Twig\Extension\CsrfExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Bundle\FullStack;
use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsTargetedValueResolver as AsTargetedConsoleValueResolver;
use Symfony\Component\Console\EventListener\ValidateQuestionInputListener;
use Symfony\Component\Console\Messenger\RunCommandMessageHandler;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;
use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapperInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\Exception\ChunkCacheItemNotFoundException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\HttpClient\ThrottlingHttpClient;
use Symfony\Component\HttpClient\UriTemplateHttpClient;
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
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Mailer\Bridge as MailerBridge;
use Symfony\Component\Mailer\Command\MailerTestCommand;
use Symfony\Component\Mailer\EventListener\InMemoryPgpPublicKeyRepository;
use Symfony\Component\Mailer\EventListener\InMemorySmimeCertificateRepository;
use Symfony\Component\Mailer\EventListener\PgpMimeEncryptedMessageListener;
use Symfony\Component\Mailer\EventListener\PgpMimeSignedMessageListener;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mime\Crypto\PgpEncrypter;
use Symfony\Component\Mime\Crypto\PgpSigner;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Notifier\Bridge as NotifierBridge;
use Symfony\Component\Notifier\Bridge\FakeChat\FakeChatTransportFactory;
use Symfony\Component\Notifier\Bridge\FakeSms\FakeSmsTransportFactory;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface as NotifierTransportFactoryInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\PropertyInfo\Extractor\ConstructorArgumentTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\RateLimiter\CompoundRateLimiterFactory;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\Attribute as SerializerMapping;
use Symfony\Component\Serializer\Attribute\ExtendsSerializationFor;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\String\LazyString;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Translation\Bridge as TranslationBridge;
use Symfony\Component\Translation\Command\TranslationLintCommand as BaseTranslationLintCommand;
use Symfony\Component\Translation\Command\XliffLintCommand as BaseXliffLintCommand;
use Symfony\Component\Translation\Command\XliffUpdateSourcesCommand;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Translation\PseudoLocalizationTranslator;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Attribute\ExtendsValidationFor;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\ExpressionLanguageProvider;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\GroupProviderInterface;
use Symfony\Component\Validator\Mapping\Loader\PropertyInfoLoader;
use Symfony\Component\Validator\ObjectInitializerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Webhook\Controller\WebhookController;
use Symfony\Component\Webhook\Server\SignatureFormat;
use Symfony\Component\Yaml\Command\LintCommand as BaseYamlLintCommand;
use Symfony\Component\Yaml\Schema\SchemaResolverInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CallbackInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
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

        if (!ContainerBuilder::willBeAvailable('symfony/validator', Validation::class, ['symfony/framework-bundle', 'symfony/form'])) {
            $container->setParameter('validator.translation_domain', 'validators');
        }

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
        $this->readConfigEnabled('translator', $container, $config['translator']);
        $this->readConfigEnabled('profiler', $container, $config['profiler']);

        // A translator must always be registered (as support is included by
        // default in the Form and Validator component). If disabled, an identity
        // translator will be used and everything will still work as expected.
        if ($this->readConfigEnabled('translator', $container, $config['translator']) || $this->readConfigEnabled('form', $container, $config['form']) || $this->readConfigEnabled('validation', $container, $config['validation'])) {
            if (!class_exists(Translator::class) && $this->readConfigEnabled('translator', $container, $config['translator'])) {
                throw new LogicException('Translation support cannot be enabled as the Translation component is not installed. Try running "composer require symfony/translation".');
            }

            if (class_exists(Translator::class)) {
                $loader->load('identity_translator.php');
            }
        }

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

        if ($this->readConfigEnabled('assets', $container, $config['assets'])) {
            if (!class_exists(Package::class)) {
                throw new LogicException('Asset support cannot be enabled as the Asset component is not installed. Try running "composer require symfony/asset".');
            }

            $this->registerAssetsConfiguration($config['assets'], $container, $loader);
        }

        if ($this->readConfigEnabled('asset_mapper', $container, $config['asset_mapper'])) {
            if (!class_exists(AssetMapper::class)) {
                throw new LogicException('AssetMapper support cannot be enabled as the AssetMapper component is not installed. Try running "composer require symfony/asset-mapper".');
            }

            $this->registerAssetMapperConfiguration($config['asset_mapper'], $container, $loader, $this->readConfigEnabled('assets', $container, $config['assets']), $this->readConfigEnabled('http_client', $container, $config['http_client']));
        }

        if ($this->readConfigEnabled('http_client', $container, $config['http_client'])) {
            $this->readConfigEnabled('rate_limiter', $container, $config['rate_limiter']); // makes sure that isInitializedConfigEnabled() will work
            $this->registerHttpClientConfiguration($config['http_client'], $container, $loader);
        }

        if ($this->readConfigEnabled('mailer', $container, $config['mailer'])) {
            $this->readConfigEnabled('rate_limiter', $container, $config['rate_limiter']);
            $this->registerMailerConfiguration($config['mailer'], $container, $loader, $this->readConfigEnabled('webhook', $container, $config['webhook']));

            if (!$this->hasConsole() || !class_exists(MailerTestCommand::class)) {
                $container->removeDefinition('console.command.mailer_test');
            }
        }

        $propertyInfoEnabled = $this->readConfigEnabled('property_info', $container, $config['property_info']);
        $this->registerHttpCacheConfiguration($config['http_cache'], $container, $config['http_method_override'], $config['allowed_http_method_override']);
        $this->registerEsiConfiguration($config['esi'], $container, $loader);
        $this->registerSsiConfiguration($config['ssi'], $container, $loader);
        $this->registerFragmentsConfiguration($config['fragments'], $container, $loader);
        $container->getDefinition('uri_signer')->addArgument($config['uri_signer']['expiration']);
        $this->registerTranslatorConfiguration($config['translator'], $container, $loader, $config['default_locale'], $config['enabled_locales']);
        $this->registerDebugConfiguration($config['php_errors'], $container, $loader);
        $this->registerRouterConfiguration($config['router'], $container, $loader, $config['enabled_locales']);
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

        if ($this->readConfigEnabled('serializer', $container, $config['serializer'])) {
            if (!class_exists(Serializer::class)) {
                throw new LogicException('Serializer support cannot be enabled as the Serializer component is not installed. Try running "composer require symfony/serializer-pack".');
            }

            $this->registerSerializerConfiguration($config['serializer'], $container, $loader);
        } else {
            $container->getDefinition('argument_resolver.request_payload')
                ->setArguments([])
                ->addError('You can neither use "#[MapRequestPayload]" nor "#[MapQueryString]" since the Serializer component is not '
                    .(class_exists(Serializer::class) ? 'enabled. Try setting "framework.serializer.enabled" to true.' : 'installed. Try running "composer require symfony/serializer-pack".')
                )
                ->addTag('container.error')
                ->clearTag('kernel.event_subscriber');

            $container->removeDefinition('console.command.serializer_debug');
        }

        if ($propertyInfoEnabled) {
            $this->registerPropertyInfoConfiguration($config['property_info'], $container, $loader);
        }

        if ($this->readConfigEnabled('rate_limiter', $container, $config['rate_limiter'])) {
            if (!interface_exists(LimiterInterface::class)) {
                throw new LogicException('Rate limiter support cannot be enabled as the RateLimiter component is not installed. Try running "composer require symfony/rate-limiter".');
            }

            $this->registerRateLimiterConfiguration($config['rate_limiter'], $container, $loader);
        }

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

            if (ContainerBuilder::willBeAvailable('symfony/validator', Validation::class, ['symfony/framework-bundle', 'symfony/form'])) {
                $this->writeConfigEnabled('validation', true, $config['validation']);
            } else {
                $container->removeDefinition('form.type_extension.form.validator');
                $container->removeDefinition('form.type_guesser.validator');
            }
        } else {
            $container->removeDefinition('console.command.form_debug');
        }

        // validation depends on form, annotations being registered
        $this->registerValidationConfiguration($config['validation'], $container, $loader, $propertyInfoEnabled);

        // notifier depends on mailer being registered
        if ($this->readConfigEnabled('notifier', $container, $config['notifier'])) {
            $this->registerNotifierConfiguration($config['notifier'], $container, $loader, $this->readConfigEnabled('webhook', $container, $config['webhook']));
        }

        // profiler depends on form, validation, translation, messenger, mailer, http-client, notifier, serializer being registered. console is optional
        $this->registerProfilerConfiguration($config['profiler'], $container, $loader);

        // These listeners keep every message, attachments included, for the
        // lifetime of the process. Only the profiler and the test assertions
        // consume them, so drop them when neither is around, and let them skip
        // messages nobody will collect otherwise. Test mode keeps collecting
        // unconditionally because the assertions read the listeners directly.
        if (!($config['test'] ?? false)) {
            $loggerListeners = [
                'mailer' => 'mailer.message_logger_listener',
                'notifier' => 'notifier.notification_logger_listener',
            ];

            foreach ($loggerListeners as $extension => $id) {
                if (!$this->isInitializedConfigEnabled($extension)) {
                    continue;
                }

                if ($this->isInitializedConfigEnabled('profiler')) {
                    $container->getDefinition($id)
                        ->setArgument(0, new Reference('profiler.is_disabled_state_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE));
                } else {
                    $container->removeDefinition($id);
                }
            }
        }

        if ($this->readConfigEnabled('webhook', $container, $config['webhook'])) {
            $this->registerWebhookConfiguration($config['webhook'], $container, $loader, $this->readConfigEnabled('serializer', $container, $config['serializer']));

            // If Webhook is installed but the HttpClient component is not available, we should throw an error
            if (!$this->readConfigEnabled('http_client', $container, $config['http_client'])) {
                $container->getDefinition('webhook.transport')
                    ->setArguments([])
                    ->addError('You cannot use the "webhook transport" service since the HttpClient component is not '
                        .(class_exists(ScopingHttpClient::class) ? 'enabled. Try setting "framework.http_client.enabled" to true.' : 'installed. Try running "composer require symfony/http-client".')
                    )
                    ->addTag('container.error');
            }
        }

        $container->registerForAutoconfiguration(PackageInterface::class)
            ->addTag('assets.package');
        $container->registerForAutoconfiguration(AssetCompilerInterface::class)
            ->addTag('asset_mapper.compiler');
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
        $container->registerForAutoconfiguration(PropertyListExtractorInterface::class)
            ->addTag('property_info.list_extractor');
        $container->registerForAutoconfiguration(PropertyTypeExtractorInterface::class)
            ->addTag('property_info.type_extractor');
        $container->registerForAutoconfiguration(ConstructorArgumentTypeExtractorInterface::class)
            ->addTag('property_info.constructor_extractor');
        $container->registerForAutoconfiguration(PropertyDescriptionExtractorInterface::class)
            ->addTag('property_info.description_extractor');
        $container->registerForAutoconfiguration(PropertyAccessExtractorInterface::class)
            ->addTag('property_info.access_extractor');
        $container->registerForAutoconfiguration(PropertyInitializableExtractorInterface::class)
            ->addTag('property_info.initializable_extractor');
        $container->registerForAutoconfiguration(EncoderInterface::class)
            ->addTag('serializer.encoder');
        $container->registerForAutoconfiguration(DecoderInterface::class)
            ->addTag('serializer.encoder');
        $container->registerForAutoconfiguration(NormalizerInterface::class)
            ->addTag('serializer.normalizer');
        $container->registerForAutoconfiguration(DenormalizerInterface::class)
            ->addTag('serializer.normalizer');
        $container->registerForAutoconfiguration(ConstraintValidatorInterface::class)
            ->addTag('validator.constraint_validator');
        $container->registerForAutoconfiguration(GroupProviderInterface::class)
            ->addTag('validator.group_provider');
        $container->registerForAutoconfiguration(ObjectInitializerInterface::class)
            ->addTag('validator.initializer');

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

        $container->registerForAutoconfiguration(Constraint::class)
            ->addTag('container.excluded', ['source' => 'because it\'s a validation constraint']);
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

        if ($this->isInitializedConfigEnabled('validation')) {
            $loader->load('validator_debug.php');
        }

        if ($this->isInitializedConfigEnabled('translator')) {
            $loader->load('translation_debug.php');

            $container->getDefinition('translator.data_collector')->setDecoratedService('translator');
        }

        if ($this->isInitializedConfigEnabled('mailer')) {
            $loader->load('mailer_debug.php');
        }

        if ($this->isInitializedConfigEnabled('http_client')) {
            $loader->load('http_client_debug.php');
        }

        if ($this->isInitializedConfigEnabled('notifier')) {
            $loader->load('notifier_debug.php');
        }

        if ($this->isInitializedConfigEnabled('serializer')) {
            $loader->load('serializer_debug.php');
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

    private function registerRouterConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader, array $enabledLocales): void
    {
        if (!$this->readConfigEnabled('router', $container, $config)) {
            $container->removeDefinition('console.command.router_debug');
            $container->removeDefinition('console.command.router_match');

            return;
        }

        // Read the deprecated "router.request_context.{host,scheme}" parameters before routing.php
        // sets their defaults, so that an explicit user value takes precedence. They are inlined as
        // arguments of the "router.request_context" service below: this avoids both triggering their
        // deprecation and eagerly resolving every env-var-based parameter through ParameterBag::all()
        // at runtime.
        $parameters = $container->getParameterBag()->all();
        $requestContextHost = $parameters['router.request_context.host'] ?? 'localhost';
        $requestContextScheme = $parameters['router.request_context.scheme'] ?? 'http';

        $loader->load('routing.php');

        $container->getDefinition('router.request_context')
            ->setArgument(1, $requestContextHost)
            ->setArgument(2, $requestContextScheme);

        $container->deprecateParameter('router.request_context.scheme', 'symfony/framework-bundle', '8.1', 'Parameter "router.request_context.scheme" is deprecated, use "router.request_context.base_url" parameter or the "framework.router.default_uri" config option instead.');
        $container->deprecateParameter('router.request_context.host', 'symfony/framework-bundle', '8.1', 'Parameter "router.request_context.host" is deprecated, use "router.request_context.base_url" parameter or the "framework.router.default_uri" config option instead.');

        if ($config['utf8']) {
            $container->getDefinition('routing.loader')->replaceArgument(1, ['utf8' => true]);
        }

        if ($enabledLocales) {
            $usedEnvs = [];
            $container->resolveEnvPlaceholders($enabledLocales, null, $usedEnvs);

            if (!$usedEnvs) {
                $locales = implode('|', array_map('preg_quote', $enabledLocales));
            } else {
                $locales = (new Definition('string'))
                    ->setFactory('implode')
                    ->setArguments(['|', (new Definition('array'))
                        ->setFactory('array_map')
                        ->setArguments(['preg_quote', $enabledLocales]),
                    ]);
            }

            $container->getDefinition('routing.loader')->replaceArgument(2, ['_locale' => $locales]);
        }

        if (!ContainerBuilder::willBeAvailable('symfony/expression-language', ExpressionLanguage::class, ['symfony/framework-bundle', 'symfony/routing'])) {
            $container->removeDefinition('router.expression_language_provider');
        }

        $container->setParameter('router.resource', $config['resource']);
        $container->setParameter('router.cache_dir', '%kernel.build_dir%');
        $router = $container->findDefinition('router.default');
        $argument = $router->getArgument(2);
        $argument['strict_requirements'] = $config['strict_requirements'];
        if (isset($config['type'])) {
            $argument['resource_type'] = $config['type'];
        }
        $router->replaceArgument(2, $argument);

        $container->setParameter('request_listener.http_port', $config['http_port']);
        $container->setParameter('request_listener.https_port', $config['https_port']);

        if (null !== $config['default_uri']) {
            $container->setParameter('router.request_context.base_url', $config['default_uri']);
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

    private function registerAssetsConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('assets.php');

        if ($config['version_strategy']) {
            $defaultVersion = new Reference($config['version_strategy']);
        } else {
            $defaultVersion = $this->createVersion($container, $config['version'], $config['version_format'], $config['json_manifest_path'], '_default', $config['strict_mode']);
        }

        $defaultPackage = $this->createPackageDefinition($config['base_path'], $config['base_urls'], $defaultVersion);
        $container->setDefinition('assets._default_package', $defaultPackage);

        foreach ($config['packages'] as $name => $package) {
            if (null !== $package['version_strategy']) {
                $version = new Reference($package['version_strategy']);
            } elseif (!\array_key_exists('version', $package) && null === $package['json_manifest_path']) {
                // if neither version nor json_manifest_path are specified, use the default
                $version = $defaultVersion;
            } else {
                // let format fallback to main version_format
                $format = $package['version_format'] ?: $config['version_format'];
                $version = $package['version'] ?? null;
                $version = $this->createVersion($container, $version, $format, $package['json_manifest_path'], $name, $package['strict_mode']);
            }

            $packageDefinition = $this->createPackageDefinition($package['base_path'], $package['base_urls'], $version)
                ->addTag('assets.package', ['package' => $name]);
            $container->setDefinition('assets._package_'.$name, $packageDefinition);
            $container->registerAliasForArgument('assets._package_'.$name, PackageInterface::class, $name.'.package', $name);
        }
    }

    private function registerAssetMapperConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader, bool $assetEnabled, bool $httpClientEnabled): void
    {
        $loader->load('asset_mapper.php');

        if (!$assetEnabled) {
            $container->removeDefinition('asset_mapper.asset_package');
        } else {
            $container->getDefinition('asset_mapper.asset_package')
                ->replaceArgument(3, $config['server'] ? $config['public_prefix'] : null);
        }

        if (!$httpClientEnabled) {
            $container->register('asset_mapper.http_client', HttpClientInterface::class)
                ->addTag('container.error')
                ->addError('You cannot use the AssetMapper integration since the HttpClient component is not enabled. Try enabling the "framework.http_client" config option.');
        }

        $paths = $config['paths'];
        foreach ($container->getParameter('kernel.bundles_metadata') as $name => $bundle) {
            if ($container->fileExists($dir = $bundle['path'].'/Resources/public') || $container->fileExists($dir = $bundle['path'].'/public')) {
                $paths[$dir] = \sprintf('bundles/%s', preg_replace('/bundle$/', '', strtolower($name)));
            }
        }
        $excludedPathPatterns = [];
        foreach ($config['excluded_patterns'] as $path) {
            $excludedPathPatterns[] = Glob::toRegex($path, true, false);
        }

        $container->getDefinition('asset_mapper.repository')
            ->setArgument(0, $paths)
            ->setArgument(2, $excludedPathPatterns)
            ->setArgument(3, $config['exclude_dotfiles']);

        $container->getDefinition('asset_mapper.public_assets_path_resolver')
            ->setArgument(0, $config['public_prefix']);

        $publicDirectory = $this->getPublicDirectory($container);
        $publicAssetsDirectory = rtrim($publicDirectory.'/'.ltrim($config['public_prefix'], '/'), '/');
        $container->getDefinition('asset_mapper.local_public_assets_filesystem')
            ->setArgument(0, $publicDirectory)
        ;

        $container->getDefinition('asset_mapper.compiled_asset_mapper_config_reader')
            ->setArgument(0, $publicAssetsDirectory);

        if (!$config['server']) {
            $container->removeDefinition('asset_mapper.dev_server_subscriber');
        } else {
            $container->getDefinition('asset_mapper.dev_server_subscriber')
                ->setArgument(1, $config['public_prefix'])
                ->setArgument(2, $config['extensions']);
        }

        $container->getDefinition('asset_mapper.compiler.css_asset_url_compiler')
            ->setArgument(0, $config['missing_import_mode']);

        $container->getDefinition('asset_mapper.compiler.javascript_import_path_compiler')
            ->setArgument(1, $config['missing_import_mode']);

        $container
            ->getDefinition('asset_mapper.importmap.remote_package_storage')
            ->replaceArgument(0, $config['vendor_dir'])
        ;
        $container
            ->getDefinition('asset_mapper.mapped_asset_factory')
            ->replaceArgument(2, $config['vendor_dir'])
        ;

        $container
            ->getDefinition('asset_mapper.importmap.generator')
            ->replaceArgument(3, $config['importmap_integrity_algorithms'])
            ->setArgument(4, $config['importmap_entries'])
        ;

        $container
            ->getDefinition('asset_mapper.importmap.config_reader')
            ->replaceArgument(0, $config['importmap_path'])
        ;

        $container
            ->getDefinition('asset_mapper.importmap.renderer')
            ->replaceArgument(3, $config['importmap_polyfill'])
            ->replaceArgument(4, $config['importmap_script_attributes'])
        ;
        $container
            ->getDefinition('asset_mapper.importmap.update_checker')
            ->replaceArgument(3, $config['minimum_release_age'])
        ;

        $compressors = [];
        foreach ($config['precompress']['formats'] as $format) {
            $compressors[$format] = new Reference("asset_mapper.compressor.$format");
        }

        $container->getDefinition('asset_mapper.compressor')->replaceArgument(0, $compressors ?: null);

        if ($config['precompress']['enabled']) {
            $container
                ->getDefinition('asset_mapper.local_public_assets_filesystem')
                ->addArgument(new Reference('asset_mapper.compressor'))
                ->addArgument($config['precompress']['extensions'])
            ;
        }
    }

    /**
     * Returns a definition for an asset package.
     */
    private function createPackageDefinition(?string $basePath, array $baseUrls, Reference $version): Definition
    {
        if ($basePath && $baseUrls) {
            throw new \LogicException('An asset package cannot have base URLs and base paths.');
        }

        $package = new ChildDefinition($baseUrls ? 'assets.url_package' : 'assets.path_package');
        $package
            ->replaceArgument(0, $baseUrls ?: $basePath)
            ->replaceArgument(1, $version)
        ;

        return $package;
    }

    private function createVersion(ContainerBuilder $container, ?string $version, ?string $format, ?string $jsonManifestPath, string $name, bool $strictMode): Reference
    {
        // Configuration prevents $version and $jsonManifestPath from being set
        if (null !== $version) {
            $def = new ChildDefinition('assets.static_version_strategy');
            $def
                ->replaceArgument(0, $version)
                ->replaceArgument(1, $format)
            ;
            $container->setDefinition('assets._version_'.$name, $def);

            return new Reference('assets._version_'.$name);
        }

        if (null !== $jsonManifestPath) {
            $def = new ChildDefinition('assets.json_manifest_version_strategy');
            $def->replaceArgument(0, $jsonManifestPath);
            $def->replaceArgument(2, $strictMode);
            $container->setDefinition('assets._version_'.$name, $def);

            return new Reference('assets._version_'.$name);
        }

        return new Reference('assets.empty_version_strategy');
    }

    private function registerTranslatorConfiguration(array $config, ContainerBuilder $container, LoaderInterface $loader, string $defaultLocale, array $enabledLocales): void
    {
        if (!$this->readConfigEnabled('translator', $container, $config)) {
            $container->removeDefinition('console.command.translation_debug');
            $container->removeDefinition('console.command.translation_extract');
            $container->removeDefinition('console.command.translation_pull');
            $container->removeDefinition('console.command.translation_push');
            $container->removeDefinition('console.command.translation_lint');
            $container->removeDefinition('console.command.translation_xliff_update_sources');

            return;
        }

        $loader->load('translation.php');

        if (!ContainerBuilder::willBeAvailable('symfony/translation', LocaleSwitcher::class, ['symfony/framework-bundle'])) {
            $container->removeDefinition('translation.locale_switcher');
        }

        // don't use ContainerBuilder::willBeAvailable() as these are not needed in production
        if (interface_exists(Parser::class)) {
            $container->removeDefinition('translation.extractor.php');
        } else {
            $container->removeDefinition('translation.extractor.php_ast');
        }

        $loader->load('translation_providers.php');

        // Use the "real" translator instead of the identity default
        $container->setAlias('translator', 'translator.default')->setPublic(true);
        $container->setAlias('translator.formatter', new Alias($config['formatter'], false));
        $translator = $container->findDefinition('translator.default');
        $translator->addMethodCall('setFallbackLocales', [$config['fallbacks'] ?: [$defaultLocale]]);

        $defaultOptions = $translator->getArgument(4);
        $defaultOptions['cache_dir'] = $config['cache_dir'];
        $translator->setArgument(4, $defaultOptions);
        $translator->setArgument(5, $enabledLocales);

        $container->setParameter('translator.logging', $config['logging']);
        $container->setParameter('translator.default_path', $config['default_path']);

        // Discover translation directories
        $dirs = [];
        $transPaths = [];
        $nonExistingDirs = [];
        if (ContainerBuilder::willBeAvailable('symfony/validator', Validation::class, ['symfony/framework-bundle', 'symfony/translation'])) {
            $r = new \ReflectionClass(Validation::class);

            $dirs[] = $transPaths[] = \dirname($r->getFileName()).'/Resources/translations';
        }
        if (ContainerBuilder::willBeAvailable('symfony/form', Form::class, ['symfony/framework-bundle', 'symfony/translation'])) {
            $r = new \ReflectionClass(Form::class);

            $dirs[] = $transPaths[] = \dirname($r->getFileName()).'/Resources/translations';
        }
        if (ContainerBuilder::willBeAvailable('symfony/security-core', AuthenticationException::class, ['symfony/framework-bundle', 'symfony/translation'])) {
            $r = new \ReflectionClass(AuthenticationException::class);

            $dirs[] = $transPaths[] = \dirname($r->getFileName(), 2).'/Resources/translations';
        }
        $defaultDir = $container->getParameterBag()->resolveValue($config['default_path']);
        foreach ($container->getParameter('kernel.bundles_metadata') as $name => $bundle) {
            if ($container->fileExists($dir = $bundle['path'].'/Resources/translations') || $container->fileExists($dir = $bundle['path'].'/translations')) {
                $dirs[] = $transPaths[] = $dir;
            } else {
                $nonExistingDirs[] = $dir;
            }
        }

        foreach ($config['paths'] as $dir) {
            if ($container->fileExists($dir)) {
                $dirs[] = $transPaths[] = $dir;
            } else {
                throw new \UnexpectedValueException(\sprintf('"%s" defined in translator.paths does not exist or is not a directory.', $dir));
            }
        }

        if ($container->hasDefinition('console.command.translation_debug')) {
            $container->getDefinition('console.command.translation_debug')->replaceArgument(5, $transPaths);
        }

        if ($container->hasDefinition('console.command.translation_extract')) {
            $container->getDefinition('console.command.translation_extract')->replaceArgument(6, $transPaths);
        }

        if ($container->hasDefinition('console.command.translation_xliff_update_sources')) {
            $container->getDefinition('console.command.translation_xliff_update_sources')->replaceArgument(3, array_merge($config['paths'], [$config['default_path']]));
        }

        if (null === $defaultDir) {
            // allow null
        } elseif ($container->fileExists($defaultDir)) {
            $dirs[] = $defaultDir;
        } else {
            $nonExistingDirs[] = $defaultDir;
        }

        // Register translation resources
        if ($dirs) {
            $files = [];

            foreach ($dirs as $dir) {
                $finder = Finder::create()
                    ->followLinks()
                    ->files()
                    ->filter(static fn (\SplFileInfo $file) => 2 <= substr_count($file->getBasename(), '.') && preg_match('/\.\w+$/', $file->getBasename()))
                    ->in($dir)
                    ->sortByName()
                ;
                foreach ($finder as $file) {
                    $fileNameParts = explode('.', basename($file));
                    $locale = $fileNameParts[\count($fileNameParts) - 2];
                    if (!isset($files[$locale])) {
                        $files[$locale] = [];
                    }

                    $files[$locale][] = (string) $file;
                }
            }

            $projectDir = $container->getParameter('kernel.project_dir');

            $options = array_merge(
                $translator->getArgument(4),
                [
                    'resource_files' => $files,
                    'scanned_directories' => $scannedDirectories = array_merge($dirs, $nonExistingDirs),
                    'cache_vary' => [
                        'scanned_directories' => array_map(static fn ($dir) => str_starts_with($dir, $projectDir.'/') ? substr($dir, 1 + \strlen($projectDir)) : $dir, $scannedDirectories),
                    ],
                ]
            );

            $translator->replaceArgument(4, $options);
        }

        foreach ($config['globals'] as $name => $global) {
            $translator->addMethodCall('addGlobalParameter', [$name, $global['value'] ?? new Definition(TranslatableMessage::class, [$global['message'], $global['parameters'] ?? [], $global['domain'] ?? null])]);
        }

        if ($config['pseudo_localization']['enabled']) {
            $options = $config['pseudo_localization'];
            unset($options['enabled']);

            $container
                ->register('translator.pseudo', PseudoLocalizationTranslator::class)
                ->setDecoratedService('translator', null, -1) // Lower priority than "translator.data_collector"
                ->setArguments([
                    new Reference('translator.pseudo.inner'),
                    $options,
                ]);
        }

        $classToServices = [
            TranslationBridge\Crowdin\CrowdinProviderFactory::class => ['symfony/crowdin-translation-provider', ['translation.provider_factory.crowdin', 'translation.provider_factory.crowdin.http_client']],
            TranslationBridge\Loco\LocoProviderFactory::class => ['symfony/loco-translation-provider', ['translation.provider_factory.loco', 'translation.provider_factory.loco.http_client']],
            TranslationBridge\Lokalise\LokaliseProviderFactory::class => ['symfony/lokalise-translation-provider', ['translation.provider_factory.lokalise']],
            TranslationBridge\Phrase\PhraseProviderFactory::class => ['symfony/phrase-translation-provider', ['translation.provider_factory.phrase']],
            TranslationBridge\PoEditor\PoEditorProviderFactory::class => ['symfony/po-editor-translation-provider', ['translation.provider_factory.poeditor']],
        ];

        $parentPackages = ['symfony/framework-bundle', 'symfony/translation', 'symfony/http-client'];

        foreach ($classToServices as $class => [$package, $services]) {
            if ($container->hasDefinition('http_client') && ContainerBuilder::willBeAvailable($package, $class, $parentPackages)) {
                continue;
            }

            foreach ($services as $service) {
                $container->removeDefinition($service);
            }
        }

        if (!$config['providers']) {
            return;
        }

        $locales = $enabledLocales;

        foreach ($config['providers'] as $provider) {
            if ($provider['locales']) {
                $locales = array_merge($locales, $provider['locales']);
            }
        }

        $locales = array_values(array_unique($locales));

        $container->getDefinition('console.command.translation_pull')
            ->replaceArgument(4, array_merge($transPaths, [$config['default_path']]))
            ->replaceArgument(5, $locales)
        ;

        $container->getDefinition('console.command.translation_push')
            ->replaceArgument(2, array_merge($transPaths, [$config['default_path']]))
            ->replaceArgument(3, $locales)
        ;

        $container->getDefinition('translation.provider_collection_factory')
            ->replaceArgument(1, $locales)
        ;

        $container->getDefinition('translation.provider_collection')->setArgument(0, $config['providers']);
    }

    private function registerValidationConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader, bool $propertyInfoEnabled): void
    {
        if (!$this->readConfigEnabled('validation', $container, $config)) {
            $container->removeDefinition('console.command.validator_debug');
            $container->removeDefinition('.console.validate_question_input_listener');

            return;
        }

        if (!class_exists(Validation::class)) {
            throw new LogicException('Validation support cannot be enabled as the Validator component is not installed. Try running "composer require symfony/validator".');
        }

        if (!class_exists(ValidateQuestionInputListener::class)) {
            $container->removeDefinition('.console.validate_question_input_listener');
        }

        $loader->load('validator.php');

        $validatorBuilder = $container->getDefinition('validator.builder');

        $container->setParameter('validator.translation_domain', $config['translation_domain']);

        $files = ['xml' => [], 'yml' => []];
        $this->registerValidatorMapping($container, $config, $files);

        if ($files['xml']) {
            $validatorBuilder->addMethodCall('addXmlMappings', [$files['xml']]);
        }

        if ($files['yml']) {
            $validatorBuilder->addMethodCall('addYamlMappings', [$files['yml']]);
        }

        $definition = $container->findDefinition('validator.email');
        $definition->replaceArgument(0, $config['email_validation_mode']);

        // When attributes are disabled, it means from runtime-discovery only; autoconfiguration should still happen.
        // And when runtime-discovery of attributes is enabled, we can skip compile-time autoconfiguration in debug mode.
        if (!($config['enable_attributes'] ?? false) || !$container->getParameter('kernel.debug')) {
            // The $reflector argument hints at where the attribute could be used
            $container->registerAttributeForAutoconfiguration(Constraint::class, static function (ChildDefinition $definition, Constraint $attribute, \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflector) {
                $definition->addTag('validator.attribute_metadata');
            });
        }

        $container->registerAttributeForAutoconfiguration(ExtendsValidationFor::class, static function (ChildDefinition $definition, ExtendsValidationFor $attribute) {
            $definition->addTag('validator.attribute_metadata', ['for' => $attribute->class])
                ->addTag('container.excluded', ['source' => 'because it\'s a validator constraint extension']);
        });

        if ($config['enable_attributes'] ?? false) {
            $validatorBuilder->addMethodCall('enableAttributeMapping');
        }

        if ($config['static_method'] ?? false) {
            foreach ($config['static_method'] as $methodName) {
                $validatorBuilder->addMethodCall('addMethodMapping', [$methodName]);
            }
        }

        if (!$container->getParameter('kernel.debug')) {
            $validatorBuilder->addMethodCall('setMappingCache', [new Reference('validator.mapping.cache.adapter')]);
        }

        if ($config['disable_translation'] ?? false) {
            $validatorBuilder->addMethodCall('disableTranslation');
        }

        if ($config['property_metadata_existence_check'] ?? false) {
            $validatorBuilder->addMethodCall('enablePropertyMetadataExistenceCheck');
        }

        $container->setParameter('validator.auto_mapping', $config['auto_mapping']);
        if (!$propertyInfoEnabled || !class_exists(PropertyInfoLoader::class)) {
            $container->removeDefinition('validator.property_info_loader');
        }

        $container
            ->getDefinition('validator.not_compromised_password')
            ->setArgument(2, $config['not_compromised_password']['enabled'])
            ->setArgument(3, $config['not_compromised_password']['endpoint'])
        ;

        if (!class_exists(ExpressionLanguage::class)) {
            $container->removeDefinition('validator.expression_language');
            $container->removeDefinition('validator.expression_language_provider');
        } elseif (!class_exists(ExpressionLanguageProvider::class)) {
            $container->removeDefinition('validator.expression_language_provider');
        }
    }

    private function registerValidatorMapping(ContainerBuilder $container, array $config, array &$files): void
    {
        $fileRecorder = static function ($extension, $path) use (&$files) {
            $files['yaml' === $extension ? 'yml' : $extension][] = $path;
        };

        if (!ContainerBuilder::willBeAvailable('symfony/form', Form::class, ['symfony/framework-bundle', 'symfony/validator'])) {
            $container->removeDefinition('validator.form.attribute_metadata');
        }

        foreach ($container->getParameter('kernel.bundles_metadata') as $bundle) {
            $configDir = is_dir($bundle['path'].'/Resources/config') ? $bundle['path'].'/Resources/config' : $bundle['path'].'/config';

            if (
                $container->fileExists($file = $configDir.'/validation.yaml', false)
                || $container->fileExists($file = $configDir.'/validation.yml', false)
            ) {
                $fileRecorder('yml', $file);
            }

            if ($container->fileExists($file = $configDir.'/validation.xml', false)) {
                $fileRecorder('xml', $file);
            }

            if ($container->fileExists($dir = $configDir.'/validation', '/^$/')) {
                $this->registerMappingFilesFromDir($dir, $fileRecorder);
            }
        }

        $projectDir = $container->getParameter('kernel.project_dir');
        if ($container->fileExists($dir = $projectDir.'/config/validator', '/^$/')) {
            $this->registerMappingFilesFromDir($dir, $fileRecorder);
        }

        $this->registerMappingFilesFromConfig($container, $config, $fileRecorder);
    }

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

    private function registerSerializerConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('serializer.php');

        $chainLoader = $container->getDefinition('serializer.mapping.chain_loader');

        if (!class_exists(Yaml::class)) {
            $container->removeDefinition('serializer.encoder.yaml');
        }

        if (!class_exists(Headers::class)) {
            $container->removeDefinition('serializer.normalizer.mime_message');
        }

        if ($container->getParameter('kernel.debug')) {
            $container->removeDefinition('serializer.mapping.cache_class_metadata_factory');
        }

        if (!$this->readConfigEnabled('translator', $container, $config)) {
            $container->removeDefinition('serializer.normalizer.translatable');
        }

        $serializerLoaders = [];

        // When attributes are disabled, it means from runtime-discovery only; autoconfiguration should still happen.
        // And when runtime-discovery of attributes is enabled, we can skip compile-time autoconfiguration in debug mode.
        if (!($config['enable_attributes'] ?? false) || !$container->getParameter('kernel.debug')) {
            // The $reflector argument hints at where the attribute could be used
            $configurator = static function (ChildDefinition $definition, object $attribute, \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflector) {
                $definition->addTag('serializer.attribute_metadata');
            };
            $container->registerAttributeForAutoconfiguration(SerializerMapping\Context::class, $configurator);
            $container->registerAttributeForAutoconfiguration(SerializerMapping\Groups::class, $configurator);

            $configurator = static function (ChildDefinition $definition, object $attribute, \ReflectionMethod|\ReflectionProperty $reflector) {
                $definition->addTag('serializer.attribute_metadata');
            };
            $container->registerAttributeForAutoconfiguration(SerializerMapping\Ignore::class, $configurator);
            $container->registerAttributeForAutoconfiguration(SerializerMapping\MaxDepth::class, $configurator);
            $container->registerAttributeForAutoconfiguration(SerializerMapping\SerializedName::class, $configurator);
            $container->registerAttributeForAutoconfiguration(SerializerMapping\SerializedPath::class, $configurator);

            $container->registerAttributeForAutoconfiguration(SerializerMapping\DiscriminatorMap::class, static function (ChildDefinition $definition) {
                $definition->addTag('serializer.attribute_metadata');
            });
        }

        $container->registerAttributeForAutoconfiguration(SerializerMapping\DiscriminatorMapType::class, static function (ChildDefinition $definition, SerializerMapping\DiscriminatorMapType $attribute) {
            $definition->addTag('serializer.attribute_metadata', ['for' => $attribute->class, 'type' => $attribute->type, 'discriminator_map_type' => true]);
        });

        $serializerLoaders[] = new Reference('serializer.mapping.attribute_loader');

        $container->getDefinition('serializer.mapping.attribute_loader')
            ->replaceArgument(0, $config['enable_attributes'] ?? false);

        $fileRecorder = static function ($extension, $path) use (&$serializerLoaders) {
            $definition = new Definition(\in_array($extension, ['yaml', 'yml'], true) ? YamlFileLoader::class : XmlFileLoader::class, [$path]);
            $serializerLoaders[] = $definition;
        };

        foreach ($container->getParameter('kernel.bundles_metadata') as $bundle) {
            $configDir = is_dir($bundle['path'].'/Resources/config') ? $bundle['path'].'/Resources/config' : $bundle['path'].'/config';

            if ($container->fileExists($file = $configDir.'/serialization.xml', false)) {
                $fileRecorder('xml', $file);
            }

            if (
                $container->fileExists($file = $configDir.'/serialization.yaml', false)
                || $container->fileExists($file = $configDir.'/serialization.yml', false)
            ) {
                $fileRecorder('yml', $file);
            }

            if ($container->fileExists($dir = $configDir.'/serialization', '/^$/')) {
                $this->registerMappingFilesFromDir($dir, $fileRecorder);
            }
        }

        $projectDir = $container->getParameter('kernel.project_dir');
        if ($container->fileExists($dir = $projectDir.'/config/serializer', '/^$/')) {
            $this->registerMappingFilesFromDir($dir, $fileRecorder);
        }

        $this->registerMappingFilesFromConfig($container, $config, $fileRecorder);

        $chainLoader->replaceArgument(0, $serializerLoaders);
        $container->getDefinition('serializer.mapping.cache_warmer')->replaceArgument(0, $serializerLoaders);

        if ($config['name_converter'] ?? false) {
            $container->setParameter('.serializer.name_converter', $config['name_converter']);
            $container->getDefinition('serializer.name_converter.metadata_aware')->setArgument(1, new Reference($config['name_converter']));
        }

        $defaultContext = $config['default_context'] ?? [];

        if ($defaultContext) {
            $container->setParameter('serializer.default_context', $defaultContext);
        }

        if ($config['circular_reference_handler'] ?? false) {
            $container->setParameter('.serializer.circular_reference_handler', $config['circular_reference_handler']);
        }

        if ($config['max_depth_handler'] ?? false) {
            $container->setParameter('.serializer.max_depth_handler', $config['max_depth_handler']);
        }

        $container->getDefinition('serializer.normalizer.property')->setArgument(5, $defaultContext);

        $container->setParameter('.serializer.named_serializers', $config['named_serializers'] ?? []);

        $container->registerAttributeForAutoconfiguration(ExtendsSerializationFor::class, static function (ChildDefinition $definition, ExtendsSerializationFor $attribute) {
            $definition->addTag('serializer.attribute_metadata', ['for' => $attribute->class])
                ->addTag('container.excluded', ['source' => 'because it\'s a serializer metadata extension']);
        });
    }

    private function registerPropertyInfoConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!interface_exists(PropertyInfoExtractorInterface::class)) {
            throw new LogicException('PropertyInfo support cannot be enabled as the PropertyInfo component is not installed. Try running "composer require symfony/property-info".');
        }

        $loader->load('property_info.php');

        if (!$config['with_constructor_extractor']) {
            $container->removeDefinition('property_info.constructor_extractor');
        }

        if (
            ContainerBuilder::willBeAvailable('phpstan/phpdoc-parser', PhpDocParser::class, ['symfony/framework-bundle', 'symfony/property-info'])
            && ContainerBuilder::willBeAvailable('phpdocumentor/type-resolver', ContextFactory::class, ['symfony/framework-bundle', 'symfony/property-info'])
        ) {
            $definition = $container->register('property_info.phpstan_extractor', PhpStanExtractor::class);
            $definition->addTag('property_info.type_extractor', ['priority' => -1000]);
            $definition->addTag('property_info.constructor_extractor', ['priority' => -1000]);
        }

        if (ContainerBuilder::willBeAvailable('phpdocumentor/reflection-docblock', DocBlockFactoryInterface::class, ['symfony/framework-bundle', 'symfony/property-info'])) {
            $definition = $container->register('property_info.php_doc_extractor', PhpDocExtractor::class);
            $definition->addTag('property_info.description_extractor', ['priority' => -1000]);
            $definition->addTag('property_info.type_extractor', ['priority' => -1001]);
            $definition->addTag('property_info.constructor_extractor', ['priority' => -1001]);
        }

        if ($container->getParameter('kernel.debug')) {
            $container->removeDefinition('property_info.cache');
        }
    }

    private function registerHttpClientConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('http_client.php');

        $options = $config['default_options'] ?? [];
        $cachingOptions = $options['caching'] ?? ['enabled' => false];
        unset($options['caching']);
        $rateLimiter = $options['rate_limiter'] ?? null;
        unset($options['rate_limiter']);
        $retryOptions = $options['retry_failed'] ?? ['enabled' => false];
        unset($options['retry_failed']);
        $defaultUriTemplateVars = $options['vars'] ?? [];
        unset($options['vars']);
        $container->getDefinition('http_client.transport')->setArguments([$options, $config['max_host_connections'] ?? 6]);

        if (!$hasPsr18 = ContainerBuilder::willBeAvailable('psr/http-client', ClientInterface::class, ['symfony/framework-bundle', 'symfony/http-client'])) {
            $container->removeDefinition('psr18.http_client');
            $container->removeAlias(ClientInterface::class);
        }

        if (!$hasHttplug = ContainerBuilder::willBeAvailable('php-http/httplug', HttpAsyncClient::class, ['symfony/framework-bundle', 'symfony/http-client'])) {
            $container->removeDefinition('httplug.http_client');
            $container->removeAlias(HttpAsyncClient::class);
            $container->removeAlias(HttpClient::class);
        }

        if ($this->readConfigEnabled('http_client.caching', $container, $cachingOptions)) {
            $this->registerCachingHttpClient($cachingOptions, $options, 'http_client', $container);
        }

        if (null !== $rateLimiter) {
            $this->registerThrottlingHttpClient($rateLimiter, 'http_client', $container);
        }

        if ($this->readConfigEnabled('http_client.retry_failed', $container, $retryOptions)) {
            $this->registerRetryableHttpClient($retryOptions, 'http_client', $container);
        }

        if (ContainerBuilder::willBeAvailable('guzzlehttp/uri-template', \GuzzleHttp\UriTemplate\UriTemplate::class, [])) {
            $container->setAlias('http_client.uri_template_expander', 'http_client.uri_template_expander.guzzle');
        } elseif (ContainerBuilder::willBeAvailable('rize/uri-template', \Rize\UriTemplate::class, [])) {
            $container->setAlias('http_client.uri_template_expander', 'http_client.uri_template_expander.rize');
        }

        $container
            ->getDefinition('http_client.uri_template')
            ->setArgument(2, $defaultUriTemplateVars);

        if (!$defaultMockResponseFactory = $config['mock_response_factory'] ?? null) {
            $defaultTransportId = 'http_client.transport';
        } elseif (\is_string($defaultMockResponseFactory)) {
            $defaultTransportId = '.http_client.mock_transport.'.$defaultMockResponseFactory;
            $container->register($defaultTransportId, MockHttpClient::class)
                ->setArguments([new Reference($defaultMockResponseFactory)])
                ->addTag('kernel.reset', ['method' => 'reset']);
        } else {
            $defaultTransportId = 'http_client.mock_transport';
        }

        $realTransportId = 'http_client.transport';

        if ('http_client.transport' !== $defaultTransportId) {
            // Decorate "http_client.transport" instead of replacing it as the transport of "http_client", so that
            // decorators registered on "http_client.transport" remain in the chain when a mock factory is configured.
            // The highest priority makes the mock the innermost decorator: decorators keep running around it whatever
            // their own priority. The undecorated transport stays available under "http_client.transport.real" for
            // scoped clients that opt out with "mock_response_factory: false".
            $container->getDefinition($defaultTransportId)
                ->setDecoratedService('http_client.transport', $realTransportId = 'http_client.transport.real', \PHP_INT_MAX);
            $defaultTransportId = 'http_client.transport';
        }

        foreach ($config['scoped_clients'] as $name => $scopeConfig) {
            if ($container->has($name)) {
                throw new InvalidArgumentException(\sprintf('Invalid scope name: "%s" is reserved.', $name));
            }

            $scope = $scopeConfig['scope'] ?? null;
            unset($scopeConfig['scope']);
            $cachingOptions = $scopeConfig['caching'] ?? ['enabled' => false];
            unset($scopeConfig['caching']);
            $rateLimiter = $scopeConfig['rate_limiter'] ?? null;
            unset($scopeConfig['rate_limiter']);
            $retryOptions = $scopeConfig['retry_failed'] ?? ['enabled' => false];
            unset($scopeConfig['retry_failed']);

            // the base URI is the first one tried and the configured list holds the fallbacks; the
            // scoping and the retryable clients must agree on the whole set
            if ($retryOptions['base_uris'] ?? []) {
                $retryOptions['base_uris'] = array_merge([$scopeConfig['base_uri']], $retryOptions['base_uris']);
            }

            if (false === $mockResponseFactory = $scopeConfig['mock_response_factory'] ?? $defaultMockResponseFactory) {
                $transportId = $realTransportId;
            } elseif ($mockResponseFactory === $defaultMockResponseFactory) {
                $transportId = $defaultTransportId;
            } elseif (\is_string($mockResponseFactory)) {
                $transportId = '.http_client.mock_transport.'.$mockResponseFactory;
                $container->register($transportId, MockHttpClient::class)
                    ->setArguments([new Reference($mockResponseFactory)])
                    ->addTag('kernel.reset', ['method' => 'reset']);
            } else {
                $transportId = 'http_client.mock_transport';
            }
            unset($scopeConfig['mock_response_factory']);

            // This "transport" service is decorated in the following order:
            // 1. ThrottlingHttpClient (5) -> throttles requests
            // 2. UriTemplateHttpClient (10) -> expands URI templates
            // 3. ScopingHttpClient (15) -> resolves relative URLs and applies scope configuration
            // 4. CachingHttpClient (20) -> caches responses
            // 5. RetryableHttpClient (25) -> retries requests
            // 6. TraceableHttpClient (100) -> traces requests
            //
            // when "retry_failed.base_uris" is set, RetryableHttpClient moves to 12 so that it
            // wraps ScopingHttpClient instead of being wrapped by it, see below
            $container->register($name, HttpClientInterface::class)
                ->setFactory('current')
                ->setArguments([[new Reference($transportId)]])
                ->addTag('http_client.client')
            ;

            $scopingDefinition = $container->register($name.'.scoping', ScopingHttpClient::class)
                ->setDecoratedService($name, null, 15)
                ->addTag('kernel.reset', ['method' => 'reset', 'on_invalid' => 'ignore']);

            if (null === $scope) {
                $baseUri = $scopeConfig['base_uri'];
                unset($scopeConfig['base_uri']);

                if ($retryOptions['base_uris'] ?? []) {
                    // the scope must match every URI the retryable client may rotate to, otherwise
                    // the scoped options stop applying as soon as it leaves the first one
                    $scopingDefinition
                        ->setFactory([ScopingHttpClient::class, 'forBaseUris'])
                        ->setArguments([new Reference('.inner'), $retryOptions['base_uris'], $scopeConfig]);
                } else {
                    $scopingDefinition
                        ->setFactory([ScopingHttpClient::class, 'forBaseUri'])
                        ->setArguments([new Reference('.inner'), $baseUri, $scopeConfig]);
                }
            } else {
                $scopingDefinition
                    ->setArguments([new Reference('.inner'), [$scope => $scopeConfig], $scope]);
            }

            if ($this->readConfigEnabled('http_client.scoped_clients.'.$name.'.caching', $container, $cachingOptions)) {
                $this->registerCachingHttpClient($cachingOptions, $scopeConfig, $name, $container);
            }

            if (null !== $rateLimiter) {
                $this->registerThrottlingHttpClient($rateLimiter, $name, $container);
            }

            if ($this->readConfigEnabled('http_client.scoped_clients.'.$name.'.retry_failed', $container, $retryOptions)) {
                $this->registerRetryableHttpClient($retryOptions, $name, $container);
            }

            $container
                ->register($name.'.uri_template', UriTemplateHttpClient::class)
                ->setDecoratedService($name, null, 10)
                ->setArguments([
                    new Reference('.inner'),
                    new Reference('http_client.uri_template_expander', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    $defaultUriTemplateVars,
                ]);

            $container->registerAliasForArgument($name, HttpClientInterface::class);

            if ($hasPsr18) {
                $container->setDefinition('psr18.'.$name, new ChildDefinition('psr18.http_client'))
                    ->replaceArgument(0, new Reference($name));

                $container->registerAliasForArgument('psr18.'.$name, ClientInterface::class, $name);
            }

            if ($hasHttplug) {
                $container->setDefinition('httplug.'.$name, new ChildDefinition('httplug.http_client'))
                    ->replaceArgument(0, new Reference($name));

                $container->registerAliasForArgument('httplug.'.$name, HttpAsyncClient::class, $name);
            }
        }
    }

    private function registerCachingHttpClient(array $options, array $defaultOptions, string $name, ContainerBuilder $container): void
    {
        if (!class_exists(ChunkCacheItemNotFoundException::class)) {
            throw new LogicException('Caching cannot be enabled as version 7.4+ of the HttpClient component is required.');
        }

        $definition = $container
            ->register($name.'.caching', CachingHttpClient::class)
            ->setDecoratedService($name, null, 20)
            ->setArguments([
                new Reference('.inner'),
                new Reference($options['cache_pool']),
                $defaultOptions,
                $options['shared'],
                $options['max_ttl'],
            ]);

        if (method_exists(CachingHttpClient::class, 'setLogger')) {
            $definition
                ->addMethodCall('setLogger', [new Reference('logger')])
                ->addTag('monolog.logger', ['channel' => 'http_client']);
        }
    }

    private function registerThrottlingHttpClient(string $rateLimiter, string $name, ContainerBuilder $container): void
    {
        if (!$this->isInitializedConfigEnabled('rate_limiter')) {
            throw new LogicException('Rate limiter cannot be used within HttpClient as the RateLimiter component is not enabled.');
        }

        $container->register($name.'.throttling.limiter', LimiterInterface::class)
            ->setFactory([new Reference('limiter.'.$rateLimiter), 'create']);

        $container
            ->register($name.'.throttling', ThrottlingHttpClient::class)
            ->setDecoratedService($name, null, 5)
            ->setArguments([new Reference('.inner'), new Reference($name.'.throttling.limiter')]);
    }

    private function registerRetryableHttpClient(array $options, string $name, ContainerBuilder $container): void
    {
        if (null !== $options['retry_strategy']) {
            $retryStrategy = new Reference($options['retry_strategy']);
        } else {
            $retryStrategy = new ChildDefinition('http_client.abstract_retry_strategy');
            $codes = [];
            foreach ($options['http_codes'] as $code => $codeOptions) {
                if ($codeOptions['methods']) {
                    $codes[$code] = $codeOptions['methods'];
                } else {
                    $codes[] = $code;
                }
            }

            $retryStrategy
                ->replaceArgument(0, $codes ?: GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES)
                ->replaceArgument(1, $options['delay'])
                ->replaceArgument(2, $options['multiplier'])
                ->replaceArgument(3, $options['max_delay'])
                ->replaceArgument(4, $options['jitter']);
            $container->setDefinition($name.'.retry_strategy', $retryStrategy);

            $retryStrategy = new Reference($name.'.retry_strategy');
        }

        // when retrying against several URIs, the retryable client must sit outside the scoping one:
        // scoping resolves the URL and consumes the "base_uri" option, so a base URI injected below
        // it would never be applied
        $definition = $container
            ->register($name.'.retryable', RetryableHttpClient::class)
            ->setDecoratedService($name, null, $options['base_uris'] ? 12 : 25)
            ->setArguments([new Reference('.inner'), $retryStrategy, $options['max_retries'], new Reference('logger')])
            ->addTag('monolog.logger', ['channel' => 'http_client']);

        if ($options['base_uris']) {
            $definition->addMethodCall('withOptions', [['base_uri' => $options['base_uris']]], true);
        }
    }

    private function registerMailerConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader, bool $webhookEnabled): void
    {
        if (!class_exists(Mailer::class)) {
            throw new LogicException('Mailer support cannot be enabled as the component is not installed. Try running "composer require symfony/mailer".');
        }

        $loader->load('mailer.php');
        $loader->load('mailer_transports.php');
        if (!$config['transports'] && null === $config['dsn']) {
            $config['dsn'] = 'smtp://null';
        }
        $transports = $config['dsn'] ? ['main' => $config['dsn']] : $config['transports'];
        $transports = array_map(static function (array|string $transport): array {
            if (\is_array($transport)) {
                return $transport;
            }

            return ['dsn' => $transport];
        }, $transports);

        $container->getDefinition('mailer.transports')->setArgument(0, array_combine(array_keys($transports), array_column($transports, 'dsn')));

        $transportRateLimiterReferences = [];

        foreach ($transports as $name => $transport) {
            if ($transport['rate_limiter'] ?? null) {
                $transportRateLimiterReferences[$name] = new Reference('limiter.'.$transport['rate_limiter']);
            }
        }

        if ($transportRateLimiterReferences && $this->isInitializedConfigEnabled('rate_limiter')) {
            if (!interface_exists(LimiterInterface::class)) {
                throw new LogicException('Rate limiter cannot be used within Mailer as the RateLimiter component is not installed. Try running "composer require symfony/rate-limiter".');
            }

            $container->getDefinition('mailer.rate_limiter_locator')->replaceArgument(0, $transportRateLimiterReferences);
        } else {
            $container->removeDefinition('mailer.rate_limiter_locator');
        }

        $mailer = $container->getDefinition('mailer.mailer');
        if (false === $messageBus = $config['message_bus']) {
            $mailer->replaceArgument(1, null);
        } else {
            $mailer->replaceArgument(1, $messageBus ? new Reference($messageBus) : new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }

        $classToServices = [
            MailerBridge\AhaSend\Transport\AhaSendTransportFactory::class => ['symfony/aha-send-mailer', 'mailer.transport_factory.ahasend'],
            MailerBridge\Azure\Transport\AzureTransportFactory::class => ['symfony/azure-mailer', 'mailer.transport_factory.azure'],
            MailerBridge\Brevo\Transport\BrevoTransportFactory::class => ['symfony/brevo-mailer', 'mailer.transport_factory.brevo'],
            MailerBridge\Cloudflare\Transport\CloudflareTransportFactory::class => ['symfony/cloudflare-mailer', 'mailer.transport_factory.cloudflare'],
            MailerBridge\Google\Transport\GmailTransportFactory::class => ['symfony/google-mailer', 'mailer.transport_factory.gmail'],
            MailerBridge\Infobip\Transport\InfobipTransportFactory::class => ['symfony/infobip-mailer', 'mailer.transport_factory.infobip'],
            MailerBridge\MailerSend\Transport\MailerSendTransportFactory::class => ['symfony/mailer-send-mailer', 'mailer.transport_factory.mailersend'],
            MailerBridge\Mailgun\Transport\MailgunTransportFactory::class => ['symfony/mailgun-mailer', 'mailer.transport_factory.mailgun'],
            MailerBridge\Mailjet\Transport\MailjetTransportFactory::class => ['symfony/mailjet-mailer', 'mailer.transport_factory.mailjet'],
            MailerBridge\MailKite\Transport\MailKiteTransportFactory::class => ['symfony/mail-kite-mailer', 'mailer.transport_factory.mailkite'],
            MailerBridge\Mailomat\Transport\MailomatTransportFactory::class => ['symfony/mailomat-mailer', 'mailer.transport_factory.mailomat'],
            MailerBridge\MailPace\Transport\MailPaceTransportFactory::class => ['symfony/mail-pace-mailer', 'mailer.transport_factory.mailpace'],
            MailerBridge\Mailchimp\Transport\MandrillTransportFactory::class => ['symfony/mailchimp-mailer', 'mailer.transport_factory.mailchimp'],
            MailerBridge\MicrosoftGraph\Transport\MicrosoftGraphTransportFactory::class => ['symfony/microsoft-graph-mailer', 'mailer.transport_factory.microsoftgraph'],
            MailerBridge\Postal\Transport\PostalTransportFactory::class => ['symfony/postal-mailer', 'mailer.transport_factory.postal'],
            MailerBridge\Postmark\Transport\PostmarkTransportFactory::class => ['symfony/postmark-mailer', 'mailer.transport_factory.postmark'],
            MailerBridge\PufferPost\Transport\PufferPostTransportFactory::class => ['symfony/puffer-post-mailer', 'mailer.transport_factory.pufferpost'],
            MailerBridge\Mailtrap\Transport\MailtrapTransportFactory::class => ['symfony/mailtrap-mailer', 'mailer.transport_factory.mailtrap'],
            MailerBridge\Resend\Transport\ResendTransportFactory::class => ['symfony/resend-mailer', 'mailer.transport_factory.resend'],
            MailerBridge\Scaleway\Transport\ScalewayTransportFactory::class => ['symfony/scaleway-mailer', 'mailer.transport_factory.scaleway'],
            MailerBridge\Sendgrid\Transport\SendgridTransportFactory::class => ['symfony/sendgrid-mailer', 'mailer.transport_factory.sendgrid'],
            MailerBridge\Amazon\Transport\SesTransportFactory::class => ['symfony/amazon-mailer', 'mailer.transport_factory.amazon'],
            MailerBridge\Sweego\Transport\SweegoTransportFactory::class => ['symfony/sweego-mailer', 'mailer.transport_factory.sweego'],
            MailerBridge\TurboSmtp\Transport\TurboSmtpTransportFactory::class => ['symfony/turbo-smtp-mailer', 'mailer.transport_factory.turbosmtp'],
        ];

        foreach ($classToServices as $class => [$package, $service]) {
            if (!ContainerBuilder::willBeAvailable($package, $class, ['symfony/framework-bundle', 'symfony/mailer'])) {
                $container->removeDefinition($service);
            }
        }

        $envelopeListener = $container->getDefinition('mailer.envelope_listener');
        $envelopeListener->setArgument(0, $config['envelope']['sender'] ?? null);
        $envelopeListener->setArgument(1, $config['envelope']['recipients'] ?? null);
        $envelopeListener->setArgument(2, $config['envelope']['allowed_recipients'] ?? []);

        $tracking = $config['tracking'];
        $hasTracking = null !== $tracking['opens'] || null !== $tracking['clicks'];

        if ($hasTracking && !class_exists(TrackingHeader::class)) {
            throw new LogicException('Configuring "framework.mailer.tracking" requires symfony/mailer 8.2 or higher.');
        }

        if ($config['headers'] || $hasTracking) {
            $headers = new Definition(Headers::class);
            if ($hasTracking && !isset(array_change_key_case($config['headers'])['x-track'])) {
                $headers->addMethodCall('add', [new Definition(TrackingHeader::class, [$tracking['opens'], $tracking['clicks']])]);
            }
            foreach ($config['headers'] as $name => $data) {
                $value = $data['value'];
                if (\in_array(strtolower($name), ['from', 'to', 'cc', 'bcc', 'reply-to'], true)) {
                    $value = (array) $value;
                }
                $headers->addMethodCall('addHeader', [$name, $value]);
            }
            $messageListener = $container->getDefinition('mailer.message_listener');
            $messageListener->setArgument(0, $headers);
        } else {
            $container->removeDefinition('mailer.message_listener');
        }

        if ($config['dkim_signer']['enabled']) {
            $dkimSigner = $container->getDefinition('mailer.dkim_signer');
            $dkimSigner->setArgument(0, $config['dkim_signer']['key']);
            $dkimSigner->setArgument(1, $config['dkim_signer']['domain']);
            $dkimSigner->setArgument(2, $config['dkim_signer']['select']);
            $dkimSigner->setArgument(3, $config['dkim_signer']['options']);
            $dkimSigner->setArgument(4, $config['dkim_signer']['passphrase']);
        } else {
            $container->removeDefinition('mailer.dkim_signer');
            $container->removeDefinition('mailer.dkim_signer.listener');
        }

        if ($config['smime_signer']['enabled']) {
            $smimeSigner = $container->getDefinition('mailer.smime_signer');
            $smimeSigner->setArgument(0, $config['smime_signer']['certificate']);
            $smimeSigner->setArgument(1, $config['smime_signer']['key']);
            $smimeSigner->setArgument(2, $config['smime_signer']['passphrase']);
            $smimeSigner->setArgument(3, $config['smime_signer']['extra_certificates']);
            $smimeSigner->setArgument(4, $config['smime_signer']['sign_options']);
        } else {
            $container->removeDefinition('mailer.smime_signer');
            $container->removeDefinition('mailer.smime_signer.listener');
        }

        if ($config['smime_encrypter']['enabled']) {
            if ($config['smime_encrypter']['certificates']) {
                $container->setDefinition('mailer.smime_encrypter.repository', new Definition(InMemorySmimeCertificateRepository::class, [$config['smime_encrypter']['certificates']]));
            } else {
                $container->setAlias('mailer.smime_encrypter.repository', $config['smime_encrypter']['repository']);
            }
            $container->setParameter('mailer.smime_encrypter.cipher', $config['smime_encrypter']['cipher']);
            $container->getDefinition('mailer.smime_encrypter.listener')
                ->setArgument(2, $config['smime_encrypter']['on_missing_certificate'])
                ->setArgument(3, $config['smime_encrypter']['encrypt_for_sender']);
        } else {
            $container->removeDefinition('mailer.smime_encrypter.listener');
        }

        if ($config['pgp_signer']['enabled']) {
            if (!class_exists(PgpSigner::class)) {
                throw new LogicException('PGP/MIME signed messages support cannot be enabled as this version of the Mime component does not support it. Try upgrading "symfony/mime".');
            }
            if (!class_exists(PgpMimeSignedMessageListener::class)) {
                throw new LogicException('PGP/MIME signed messages support cannot be enabled as this version of the Mailer component does not support it.');
            }
            if (!class_exists(Process::class)) {
                throw new LogicException('PGP/MIME signed messages support cannot be enabled as the Process component is not installed. Try running "composer require symfony/process".');
            }
            $pgpSigner = $container->getDefinition('mailer.pgp_signer');
            $pgpSigner->setArgument(0, $config['pgp_signer']['secret_key']);
            $pgpSigner->setArgument(1, $config['pgp_signer']['public_key']);
            $pgpSigner->setArgument(2, $config['pgp_signer']['passphrase']);
            $pgpSigner->setArgument(3, [
                'binary' => $config['pgp_signer']['binary'],
                'digest_algorithm' => $config['pgp_signer']['digest_algorithm'],
            ]);
        } else {
            $container->removeDefinition('mailer.pgp_signer');
            $container->removeDefinition('mailer.pgp_signer.listener');
        }

        if ($config['pgp_encrypter']['enabled']) {
            if (!class_exists(PgpEncrypter::class)) {
                throw new LogicException('PGP/MIME encrypted messages support cannot be enabled as this version of the Mime component does not support it. Try upgrading "symfony/mime".');
            }
            if (!class_exists(PgpMimeEncryptedMessageListener::class)) {
                throw new LogicException('PGP/MIME encrypted messages support cannot be enabled as this version of the Mailer component does not support it.');
            }
            if (!class_exists(Process::class)) {
                throw new LogicException('PGP/MIME encrypted messages support cannot be enabled as the Process component is not installed. Try running "composer require symfony/process".');
            }
            if ($config['pgp_encrypter']['keys']) {
                $container->setDefinition('mailer.pgp_encrypter.repository', new Definition(InMemoryPgpPublicKeyRepository::class, [$config['pgp_encrypter']['keys']]));
            } else {
                $container->setAlias('mailer.pgp_encrypter.repository', $config['pgp_encrypter']['repository']);
            }
            $pgpEncrypter = $container->getDefinition('mailer.pgp_encrypter');
            $pgpEncrypter->setArgument(0, [
                'binary' => $config['pgp_encrypter']['binary'],
                'cipher_algorithm' => $config['pgp_encrypter']['cipher_algorithm'],
                'timeout' => $config['pgp_encrypter']['timeout'],
                'hide_recipients' => $config['pgp_encrypter']['hide_recipients'],
            ]);
            $container->getDefinition('mailer.pgp_encrypter.listener')
                ->setArgument(2, $config['pgp_encrypter']['on_missing_key'])
                ->setArgument(3, $config['pgp_encrypter']['encrypt_for_sender']);
        } else {
            $container->removeDefinition('mailer.pgp_encrypter');
            $container->removeDefinition('mailer.pgp_encrypter.listener');
        }

        if ($webhookEnabled) {
            $loader->load('mailer_webhook.php');

            $debug = $container->getParameter('kernel.debug');
            $webhookRequestParsers = [
                MailerBridge\AhaSend\Webhook\AhaSendRequestParser::class => ['symfony/aha-send-mailer', 'mailer.webhook.request_parser.ahasend'],
                MailerBridge\Azure\Webhook\AzureRequestParser::class => ['symfony/azure-mailer', 'mailer.webhook.request_parser.azure'],
                MailerBridge\Brevo\Webhook\BrevoRequestParser::class => ['symfony/brevo-mailer', 'mailer.webhook.request_parser.brevo'],
                MailerBridge\MailerSend\Webhook\MailerSendRequestParser::class => ['symfony/mailer-send-mailer', 'mailer.webhook.request_parser.mailersend'],
                MailerBridge\Mailchimp\Webhook\MailchimpRequestParser::class => ['symfony/mailchimp-mailer', 'mailer.webhook.request_parser.mailchimp'],
                MailerBridge\Mailgun\Webhook\MailgunRequestParser::class => ['symfony/mailgun-mailer', 'mailer.webhook.request_parser.mailgun'],
                MailerBridge\Mailjet\Webhook\MailjetRequestParser::class => ['symfony/mailjet-mailer', 'mailer.webhook.request_parser.mailjet'],
                MailerBridge\Mailomat\Webhook\MailomatRequestParser::class => ['symfony/mailomat-mailer', 'mailer.webhook.request_parser.mailomat'],
                MailerBridge\Postmark\Webhook\PostmarkRequestParser::class => ['symfony/postmark-mailer', 'mailer.webhook.request_parser.postmark'],
                MailerBridge\Mailtrap\Webhook\MailtrapRequestParser::class => ['symfony/mailtrap-mailer', 'mailer.webhook.request_parser.mailtrap'],
                MailerBridge\Resend\Webhook\ResendRequestParser::class => ['symfony/resend-mailer', 'mailer.webhook.request_parser.resend'],
                MailerBridge\Scaleway\Webhook\ScalewayRequestParser::class => ['symfony/scaleway-mailer', 'mailer.webhook.request_parser.scaleway'],
                MailerBridge\Sendgrid\Webhook\SendgridRequestParser::class => ['symfony/sendgrid-mailer', 'mailer.webhook.request_parser.sendgrid'],
                MailerBridge\Sweego\Webhook\SweegoRequestParser::class => ['symfony/sweego-mailer', 'mailer.webhook.request_parser.sweego'],
                MailerBridge\TurboSmtp\Webhook\TurboSmtpRequestParser::class => ['symfony/turbo-smtp-mailer', 'mailer.webhook.request_parser.turbosmtp'],
            ];

            foreach ($webhookRequestParsers as $class => [$package, $service]) {
                if (!ContainerBuilder::willBeAvailable($package, $class, ['symfony/framework-bundle', 'symfony/mailer'])) {
                    $container->removeDefinition($service);
                } elseif ($debug && \defined($class.'::PROVIDER_IPS')) {
                    $container->getDefinition($service)->setArgument('$allowedIPs', [...$class::PROVIDER_IPS, '127.0.0.1']);
                }
            }
        }
    }

    private function registerNotifierConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader, bool $webhookEnabled): void
    {
        if (!class_exists(Notifier::class)) {
            throw new LogicException('Notifier support cannot be enabled as the component is not installed. Try running "composer require symfony/notifier".');
        }

        $loader->load('notifier.php');
        $loader->load('notifier_transports.php');

        if ($config['chatter_transports']) {
            $container->getDefinition('chatter.transports')->setArgument(0, $config['chatter_transports']);
        } else {
            $container->removeDefinition('chatter');
            $container->removeAlias(ChatterInterface::class);
        }
        if ($config['texter_transports']) {
            $container->getDefinition('texter.transports')->setArgument(0, $config['texter_transports']);
        } else {
            $container->removeDefinition('texter');
            $container->removeAlias(TexterInterface::class);
        }

        if ($this->isInitializedConfigEnabled('mailer')) {
            $sender = $container->getDefinition('mailer.envelope_listener')->getArgument(0);
            $container->getDefinition('notifier.channel.email')->setArgument(2, $sender);
        } else {
            $container->removeDefinition('notifier.channel.email');
        }

        foreach (['texter', 'chatter', 'notifier.channel.chat', 'notifier.channel.email', 'notifier.channel.sms', 'notifier.channel.push', 'notifier.channel.desktop'] as $serviceId) {
            if (!$container->hasDefinition($serviceId)) {
                continue;
            }

            if (false === $messageBus = $config['message_bus']) {
                $container->getDefinition($serviceId)->replaceArgument(1, null);
            } else {
                $container->getDefinition($serviceId)->replaceArgument(1, $messageBus ? new Reference($messageBus) : new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            }
        }

        // read by DefaultMessageBusPass, which wires the channels when a default message bus is registered
        $container->setParameter('.notifier.notification_on_failed_messages', $config['notification_on_failed_messages']);

        $container->getDefinition('notifier.channel_policy')->setArgument(0, $config['channel_policy']);

        $container->registerForAutoconfiguration(NotifierTransportFactoryInterface::class)
            ->addTag('chatter.transport_factory');

        $container->registerForAutoconfiguration(NotifierTransportFactoryInterface::class)
            ->addTag('texter.transport_factory');

        $classToServices = [
            NotifierBridge\AllMySms\AllMySmsTransportFactory::class => ['symfony/all-my-sms-notifier', 'notifier.transport_factory.all-my-sms'],
            NotifierBridge\AmazonSns\AmazonSnsTransportFactory::class => ['symfony/amazon-sns-notifier', 'notifier.transport_factory.amazon-sns'],
            NotifierBridge\Bandwidth\BandwidthTransportFactory::class => ['symfony/bandwidth-notifier', 'notifier.transport_factory.bandwidth'],
            NotifierBridge\Bluesky\BlueskyTransportFactory::class => ['symfony/bluesky-notifier', 'notifier.transport_factory.bluesky'],
            NotifierBridge\Brevo\BrevoTransportFactory::class => ['symfony/brevo-notifier', 'notifier.transport_factory.brevo'],
            NotifierBridge\Chatwork\ChatworkTransportFactory::class => ['symfony/chatwork-notifier', 'notifier.transport_factory.chatwork'],
            NotifierBridge\Clickatell\ClickatellTransportFactory::class => ['symfony/clickatell-notifier', 'notifier.transport_factory.clickatell'],
            NotifierBridge\ClickSend\ClickSendTransportFactory::class => ['symfony/click-send-notifier', 'notifier.transport_factory.click-send'],
            NotifierBridge\ContactEveryone\ContactEveryoneTransportFactory::class => ['symfony/contact-everyone-notifier', 'notifier.transport_factory.contact-everyone'],
            NotifierBridge\Discord\DiscordTransportFactory::class => ['symfony/discord-notifier', 'notifier.transport_factory.discord'],
            NotifierBridge\Engagespot\EngagespotTransportFactory::class => ['symfony/engagespot-notifier', 'notifier.transport_factory.engagespot'],
            NotifierBridge\Esendex\EsendexTransportFactory::class => ['symfony/esendex-notifier', 'notifier.transport_factory.esendex'],
            NotifierBridge\Expo\ExpoTransportFactory::class => ['symfony/expo-notifier', 'notifier.transport_factory.expo'],
            NotifierBridge\FacebookPage\FacebookPageTransportFactory::class => ['symfony/facebook-page-notifier', 'notifier.transport_factory.facebook-page'],
            NotifierBridge\Firebase\FirebaseTransportFactory::class => ['symfony/firebase-notifier', 'notifier.transport_factory.firebase'],
            NotifierBridge\FortySixElks\FortySixElksTransportFactory::class => ['symfony/forty-six-elks-notifier', 'notifier.transport_factory.forty-six-elks'],
            NotifierBridge\FreeMobile\FreeMobileTransportFactory::class => ['symfony/free-mobile-notifier', 'notifier.transport_factory.free-mobile'],
            NotifierBridge\GatewayApi\GatewayApiTransportFactory::class => ['symfony/gateway-api-notifier', 'notifier.transport_factory.gateway-api'],
            NotifierBridge\GoIp\GoIpTransportFactory::class => ['symfony/go-ip-notifier', 'notifier.transport_factory.go-ip'],
            NotifierBridge\GoogleChat\GoogleChatTransportFactory::class => ['symfony/google-chat-notifier', 'notifier.transport_factory.google-chat'],
            NotifierBridge\Infobip\InfobipTransportFactory::class => ['symfony/infobip-notifier', 'notifier.transport_factory.infobip'],
            NotifierBridge\Instagram\InstagramTransportFactory::class => ['symfony/instagram-notifier', 'notifier.transport_factory.instagram'],
            NotifierBridge\Iqsms\IqsmsTransportFactory::class => ['symfony/iqsms-notifier', 'notifier.transport_factory.iqsms'],
            NotifierBridge\Isendpro\IsendproTransportFactory::class => ['symfony/isendpro-notifier', 'notifier.transport_factory.isendpro'],
            NotifierBridge\JoliNotif\JoliNotifTransportFactory::class => ['symfony/joli-notif-notifier', 'notifier.transport_factory.joli-notif'],
            NotifierBridge\KazInfoTeh\KazInfoTehTransportFactory::class => ['symfony/kaz-info-teh-notifier', 'notifier.transport_factory.kaz-info-teh'],
            NotifierBridge\LightSms\LightSmsTransportFactory::class => ['symfony/light-sms-notifier', 'notifier.transport_factory.light-sms'],
            NotifierBridge\LineBot\LineBotTransportFactory::class => ['symfony/line-bot-notifier', 'notifier.transport_factory.line-bot'],
            NotifierBridge\LineNotify\LineNotifyTransportFactory::class => ['symfony/line-notify-notifier', 'notifier.transport_factory.line-notify'],
            NotifierBridge\LinkedIn\LinkedInTransportFactory::class => ['symfony/linked-in-notifier', 'notifier.transport_factory.linked-in'],
            NotifierBridge\Lox24\Lox24TransportFactory::class => ['symfony/lox24-notifier', 'notifier.transport_factory.lox24'],
            NotifierBridge\Mailjet\MailjetTransportFactory::class => ['symfony/mailjet-notifier', 'notifier.transport_factory.mailjet'],
            NotifierBridge\Mastodon\MastodonTransportFactory::class => ['symfony/mastodon-notifier', 'notifier.transport_factory.mastodon'],
            NotifierBridge\Matrix\MatrixTransportFactory::class => ['symfony/matrix-notifier', 'notifier.transport_factory.matrix'],
            NotifierBridge\Mattermost\MattermostTransportFactory::class => ['symfony/mattermost-notifier', 'notifier.transport_factory.mattermost'],
            NotifierBridge\Mercure\MercureTransportFactory::class => ['symfony/mercure-notifier', 'notifier.transport_factory.mercure'],
            NotifierBridge\MessageBird\MessageBirdTransportFactory::class => ['symfony/message-bird-notifier', 'notifier.transport_factory.message-bird'],
            NotifierBridge\MessageMedia\MessageMediaTransportFactory::class => ['symfony/message-media-notifier', 'notifier.transport_factory.message-media'],
            NotifierBridge\MicrosoftTeams\MicrosoftTeamsTransportFactory::class => ['symfony/microsoft-teams-notifier', 'notifier.transport_factory.microsoft-teams'],
            NotifierBridge\Mobyt\MobytTransportFactory::class => ['symfony/mobyt-notifier', 'notifier.transport_factory.mobyt'],
            NotifierBridge\Novu\NovuTransportFactory::class => ['symfony/novu-notifier', 'notifier.transport_factory.novu'],
            NotifierBridge\Ntfy\NtfyTransportFactory::class => ['symfony/ntfy-notifier', 'notifier.transport_factory.ntfy'],
            NotifierBridge\Octopush\OctopushTransportFactory::class => ['symfony/octopush-notifier', 'notifier.transport_factory.octopush'],
            NotifierBridge\OneSignal\OneSignalTransportFactory::class => ['symfony/one-signal-notifier', 'notifier.transport_factory.one-signal'],
            NotifierBridge\OrangeSms\OrangeSmsTransportFactory::class => ['symfony/orange-sms-notifier', 'notifier.transport_factory.orange-sms'],
            NotifierBridge\OvhCloud\OvhCloudTransportFactory::class => ['symfony/ovh-cloud-notifier', 'notifier.transport_factory.ovh-cloud'],
            NotifierBridge\PagerDuty\PagerDutyTransportFactory::class => ['symfony/pager-duty-notifier', 'notifier.transport_factory.pager-duty'],
            NotifierBridge\Plivo\PlivoTransportFactory::class => ['symfony/plivo-notifier', 'notifier.transport_factory.plivo'],
            NotifierBridge\Prelude\PreludeTransportFactory::class => ['symfony/prelude-notifier', 'notifier.transport_factory.prelude'],
            NotifierBridge\Primotexto\PrimotextoTransportFactory::class => ['symfony/primotexto-notifier', 'notifier.transport_factory.primotexto'],
            NotifierBridge\Pushover\PushoverTransportFactory::class => ['symfony/pushover-notifier', 'notifier.transport_factory.pushover'],
            NotifierBridge\Pushy\PushyTransportFactory::class => ['symfony/pushy-notifier', 'notifier.transport_factory.pushy'],
            NotifierBridge\Redlink\RedlinkTransportFactory::class => ['symfony/redlink-notifier', 'notifier.transport_factory.redlink'],
            NotifierBridge\RingCentral\RingCentralTransportFactory::class => ['symfony/ring-central-notifier', 'notifier.transport_factory.ring-central'],
            NotifierBridge\RocketChat\RocketChatTransportFactory::class => ['symfony/rocket-chat-notifier', 'notifier.transport_factory.rocket-chat'],
            NotifierBridge\Sendberry\SendberryTransportFactory::class => ['symfony/sendberry-notifier', 'notifier.transport_factory.sendberry'],
            NotifierBridge\Sipgate\SipgateTransportFactory::class => ['symfony/sipgate-notifier', 'notifier.transport_factory.sipgate'],
            NotifierBridge\SimpleTextin\SimpleTextinTransportFactory::class => ['symfony/simple-textin-notifier', 'notifier.transport_factory.simple-textin'],
            NotifierBridge\Sevenio\SevenIoTransportFactory::class => ['symfony/sevenio-notifier', 'notifier.transport_factory.sevenio'],
            NotifierBridge\Sinch\SinchTransportFactory::class => ['symfony/sinch-notifier', 'notifier.transport_factory.sinch'],
            NotifierBridge\Slack\SlackTransportFactory::class => ['symfony/slack-notifier', 'notifier.transport_factory.slack'],
            NotifierBridge\Smsapi\SmsapiTransportFactory::class => ['symfony/smsapi-notifier', 'notifier.transport_factory.smsapi'],
            NotifierBridge\SmsBiuras\SmsBiurasTransportFactory::class => ['symfony/sms-biuras-notifier', 'notifier.transport_factory.sms-biuras'],
            NotifierBridge\Smsbox\SmsboxTransportFactory::class => ['symfony/smsbox-notifier', 'notifier.transport_factory.smsbox'],
            NotifierBridge\Smsc\SmscTransportFactory::class => ['symfony/smsc-notifier', 'notifier.transport_factory.smsc'],
            NotifierBridge\SmsFactor\SmsFactorTransportFactory::class => ['symfony/sms-factor-notifier', 'notifier.transport_factory.sms-factor'],
            NotifierBridge\SmsProxima\SmsProximaTransportFactory::class => ['symfony/sms-proxima-notifier', 'notifier.transport_factory.sms-proxima'],
            NotifierBridge\Smsmode\SmsmodeTransportFactory::class => ['symfony/smsmode-notifier', 'notifier.transport_factory.smsmode'],
            NotifierBridge\SmsSluzba\SmsSluzbaTransportFactory::class => ['symfony/sms-sluzba-notifier', 'notifier.transport_factory.sms-sluzba'],
            NotifierBridge\Smsense\SmsenseTransportFactory::class => ['symfony/smsense-notifier', 'notifier.transport_factory.smsense'],
            NotifierBridge\SpotHit\SpotHitTransportFactory::class => ['symfony/spot-hit-notifier', 'notifier.transport_factory.spot-hit'],
            NotifierBridge\Sweego\SweegoTransportFactory::class => ['symfony/sweego-notifier', 'notifier.transport_factory.sweego'],
            NotifierBridge\Telegram\TelegramTransportFactory::class => ['symfony/telegram-notifier', 'notifier.transport_factory.telegram'],
            NotifierBridge\Telnyx\TelnyxTransportFactory::class => ['symfony/telnyx-notifier', 'notifier.transport_factory.telnyx'],
            NotifierBridge\Termii\TermiiTransportFactory::class => ['symfony/termii-notifier', 'notifier.transport_factory.termii'],
            NotifierBridge\Threads\ThreadsTransportFactory::class => ['symfony/threads-notifier', 'notifier.transport_factory.threads'],
            NotifierBridge\TurboSms\TurboSmsTransportFactory::class => ['symfony/turbo-sms-notifier', 'notifier.transport_factory.turbo-sms'],
            NotifierBridge\Twilio\TwilioTransportFactory::class => ['symfony/twilio-notifier', 'notifier.transport_factory.twilio'],
            NotifierBridge\Twitter\TwitterTransportFactory::class => ['symfony/twitter-notifier', 'notifier.transport_factory.twitter'],
            NotifierBridge\Unifonic\UnifonicTransportFactory::class => ['symfony/unifonic-notifier', 'notifier.transport_factory.unifonic'],
            NotifierBridge\Vonage\VonageTransportFactory::class => ['symfony/vonage-notifier', 'notifier.transport_factory.vonage'],
            NotifierBridge\WhatsApp\WhatsAppTransportFactory::class => ['symfony/whats-app-notifier', 'notifier.transport_factory.whats-app'],
            NotifierBridge\Yunpian\YunpianTransportFactory::class => ['symfony/yunpian-notifier', 'notifier.transport_factory.yunpian'],
            NotifierBridge\Zendesk\ZendeskTransportFactory::class => ['symfony/zendesk-notifier', 'notifier.transport_factory.zendesk'],
            NotifierBridge\Zulip\ZulipTransportFactory::class => ['symfony/zulip-notifier', 'notifier.transport_factory.zulip'],
        ];

        $parentPackages = ['symfony/framework-bundle', 'symfony/notifier'];

        foreach ($classToServices as $class => [$package, $service]) {
            if (!ContainerBuilder::willBeAvailable($package, $class, $parentPackages)) {
                $container->removeDefinition($service);
            }
        }

        if (ContainerBuilder::willBeAvailable('symfony/mercure-notifier', NotifierBridge\Mercure\MercureTransportFactory::class, $parentPackages) && ContainerBuilder::willBeAvailable('symfony/mercure-bundle', MercureBundle::class, $parentPackages) && \in_array(MercureBundle::class, $container->getParameter('kernel.bundles'), true)) {
            $container->getDefinition('notifier.transport_factory.mercure')
                ->replaceArgument(0, new Reference(HubRegistry::class))
                ->replaceArgument(1, new Reference('event_dispatcher', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->addArgument(new Reference('http_client', ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        } elseif (ContainerBuilder::willBeAvailable('symfony/mercure-notifier', NotifierBridge\Mercure\MercureTransportFactory::class, $parentPackages)) {
            $container->removeDefinition('notifier.transport_factory.mercure');
        }

        // don't use ContainerBuilder::willBeAvailable() as these are not needed in production
        if (class_exists(FakeChatTransportFactory::class)) {
            $container->getDefinition('notifier.transport_factory.fake-chat')
                ->replaceArgument(0, new Reference('mailer', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->replaceArgument(1, new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->addArgument(new Reference('event_dispatcher', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->addArgument(new Reference('http_client', ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        } else {
            $container->removeDefinition('notifier.transport_factory.fake-chat');
        }

        // don't use ContainerBuilder::willBeAvailable() as these are not needed in production
        if (class_exists(FakeSmsTransportFactory::class)) {
            $container->getDefinition('notifier.transport_factory.fake-sms')
                ->replaceArgument(0, new Reference('mailer', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->replaceArgument(1, new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->addArgument(new Reference('event_dispatcher', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->addArgument(new Reference('http_client', ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        } else {
            $container->removeDefinition('notifier.transport_factory.fake-sms');
        }

        if (ContainerBuilder::willBeAvailable('symfony/bluesky-notifier', NotifierBridge\Bluesky\BlueskyTransportFactory::class, ['symfony/framework-bundle', 'symfony/notifier'])) {
            $container->getDefinition('notifier.transport_factory.bluesky')
                ->addArgument(new Reference('logger'))
                ->addArgument(new Reference('clock', ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        }

        if (isset($config['admin_recipients'])) {
            $notifier = $container->getDefinition('notifier');
            foreach ($config['admin_recipients'] as $i => $recipient) {
                $id = 'notifier.admin_recipient.'.$i;
                $container->setDefinition($id, new Definition(Recipient::class, [$recipient['email'], $recipient['phone']]));
                $notifier->addMethodCall('addAdminRecipient', [new Reference($id)]);
            }
        }

        if ($webhookEnabled) {
            $loader->load('notifier_webhook.php');

            $webhookRequestParsers = [
                NotifierBridge\Lox24\Webhook\Lox24RequestParser::class => ['symfony/lox24-notifier', 'notifier.webhook.request_parser.lox24'],
                NotifierBridge\Smsbox\Webhook\SmsboxRequestParser::class => ['symfony/smsbox-notifier', 'notifier.webhook.request_parser.smsbox'],
                NotifierBridge\Sweego\Webhook\SweegoRequestParser::class => ['symfony/sweego-notifier', 'notifier.webhook.request_parser.sweego'],
                NotifierBridge\Twilio\Webhook\TwilioRequestParser::class => ['symfony/twilio-notifier', 'notifier.webhook.request_parser.twilio'],
                NotifierBridge\Vonage\Webhook\VonageRequestParser::class => ['symfony/vonage-notifier', 'notifier.webhook.request_parser.vonage'],
            ];

            foreach ($webhookRequestParsers as $class => [$package, $service]) {
                if (!ContainerBuilder::willBeAvailable($package, $class, ['symfony/framework-bundle', 'symfony/notifier'])) {
                    $container->removeDefinition($service);
                }
            }
        }
    }

    private function registerWebhookConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader, bool $serializerEnabled): void
    {
        if (!class_exists(WebhookController::class)) {
            throw new LogicException('Webhook support cannot be enabled as the component is not installed. Try running "composer require symfony/webhook".');
        }

        $loader->load('webhook.php');

        $parsers = [];
        foreach ($config['routing'] as $type => $cfg) {
            $parsers[$type] = [
                'parser' => new Reference($cfg['service']),
                'secret' => $cfg['secret'],
            ];
        }

        $controller = $container->getDefinition('webhook.controller');
        $controller->replaceArgument(0, $parsers);
        $controller->replaceArgument(1, new Reference($config['message_bus']));

        $jsonBodyConfigurator = $container->getDefinition('webhook.body_configurator.json');
        $jsonBodyConfigurator->replaceArgument(0, new Reference($serializerEnabled ? 'webhook.payload_serializer.serializer' : 'webhook.payload_serializer.json'));

        if (!class_exists(SignatureFormat::class)) {
            foreach (['signature_format' => 'legacy', 'timestamp_header_name' => 'Webhook-Timestamp', 'timestamp_tolerance' => 300] as $option => $default) {
                if ($default !== $config[$option]) {
                    throw new LogicException(\sprintf('Configuring "framework.webhook.%s" requires symfony/webhook 8.2 or higher. Try running "composer update symfony/webhook".', $option));
                }
            }
        }
        $signatureFormat = class_exists(SignatureFormat::class) ? SignatureFormat::from($config['signature_format']) : null;

        $jsonBodyConfigurator->replaceArgument(1, $signatureFormat);

        $container->getDefinition('webhook.headers_configurator')
            ->replaceArgument(0, $config['event_header_name'])
            ->replaceArgument(1, $config['id_header_name'])
            ->replaceArgument(2, $config['timestamp_header_name'])
            ->replaceArgument(4, $signatureFormat);

        $container->getDefinition('webhook.signer')
            ->replaceArgument(0, $config['signing_algorithm'])
            ->replaceArgument(1, $config['signature_header_name'])
            ->replaceArgument(2, $signatureFormat)
            ->replaceArgument(3, $config['timestamp_header_name']);

        $container->getDefinition('webhook.request_parser')
            ->replaceArgument(0, $config['signing_algorithm'])
            ->replaceArgument(1, $config['signature_header_name'])
            ->replaceArgument(2, $config['event_header_name'])
            ->replaceArgument(3, $config['id_header_name'])
            ->replaceArgument(4, $config['timestamp_header_name'])
            ->replaceArgument(5, $signatureFormat)
            ->replaceArgument(6, $config['timestamp_tolerance']);

        $clientId = $config['http_client'];

        if ($this->readConfigEnabled('webhook.no_private_network', $container, $config['no_private_network'])) {
            if (!class_exists(NoPrivateNetworkHttpClient::class)) {
                throw new LogicException('Configuring "framework.webhook.no_private_network" requires the HttpClient component. Try running "composer require symfony/http-client".');
            }

            $container->register('webhook.http_client', NoPrivateNetworkHttpClient::class)
                ->setArguments([
                    new Reference($clientId),
                    $config['no_private_network']['subnets'],
                    $config['no_private_network']['allow_list'],
                ])
                ->addTag('kernel.reset', ['method' => 'reset']);

            $clientId = 'webhook.http_client';
        }

        $container->getDefinition('webhook.transport')->replaceArgument(0, new Reference($clientId));
    }

    private function registerRateLimiterConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('rate_limiter.php');

        $limiters = [];
        $compoundLimiters = [];
        $lockFactories = [];

        foreach ($config['limiters'] as $name => $limiterConfig) {
            if ('compound' === $limiterConfig['policy']) {
                $compoundLimiters[$name] = $limiterConfig;

                continue;
            }

            unset($limiterConfig['limiters']);

            $limiters[] = $name;

            // default configuration (when used by other DI extensions)
            $limiterConfig += ['lock_factory' => 'lock.factory', 'cache_pool' => 'cache.rate_limiter'];

            $limiter = $container->setDefinition($limiterId = 'limiter.'.$name, new ChildDefinition('limiter'))
                ->addTag('rate_limiter', ['name' => $name]);

            if ('auto' === $limiterConfig['lock_factory']) {
                $lockFactories[$limiterId] = [2, null];
            } elseif (null !== $limiterConfig['lock_factory']) {
                if (!interface_exists(LockInterface::class)) {
                    throw new LogicException(\sprintf('Rate limiter "%s" requires the Lock component to be installed. Try running "composer require symfony/lock".', $name));
                }

                if ('lock.factory' === $limiterConfig['lock_factory']) {
                    $lockFactories[$limiterId] = [2, \sprintf('Rate limiter "%s"', $name)];
                } else {
                    $limiter->replaceArgument(2, new Reference($limiterConfig['lock_factory']));
                }
            }
            unset($limiterConfig['lock_factory']);

            if (null === $storageId = $limiterConfig['storage_service'] ?? null) {
                $container->register($storageId = 'limiter.storage.'.$name, CacheStorage::class)->addArgument(new Reference($limiterConfig['cache_pool']));
            }

            $limiter->replaceArgument(1, new Reference($storageId));
            unset($limiterConfig['storage_service'], $limiterConfig['cache_pool']);

            $limiterConfig['id'] = $name;
            $limiter->replaceArgument(0, $limiterConfig);

            $container->registerAliasForArgument($limiterId, RateLimiterFactoryInterface::class, $name.'.limiter', $name);
        }

        foreach ($compoundLimiters as $name => $limiterConfig) {
            if (!$limiterConfig['limiters']) {
                throw new LogicException(\sprintf('Compound rate limiter "%s" requires at least one sub-limiter.', $name));
            }

            if ($unknownLimiters = array_diff(array_keys($limiterConfig['limiters']), $limiters)) {
                throw new LogicException(\sprintf('Compound rate limiter "%s" references unknown limiter(s) "%s".', $name, implode('", "', $unknownLimiters)));
            }

            $factories = $keys = [];
            foreach ($limiterConfig['limiters'] as $subName => $subConfig) {
                $factories[$subName] = new Reference('limiter.'.$subName);

                if (null !== $subConfig['key']) {
                    $keys[$subName] = $subConfig['key'];
                }
            }

            $container->register($limiterId = 'limiter.'.$name, CompoundRateLimiterFactory::class)
                ->addTag('rate_limiter', ['name' => $name])
                ->setArguments([new IteratorArgument($factories), $keys])
            ;

            $container->registerAliasForArgument($limiterId, RateLimiterFactoryInterface::class, $name.'.limiter', $name);
        }

        if (class_exists(RateLimiterBuilder::class)) {
            $builderConfig = $config['builder'];

            $builder = $container->getDefinition('limiter_builder');

            if (null === $storageId = $builderConfig['storage_service']) {
                $container->register($storageId = 'limiter_builder.storage', CacheStorage::class)->addArgument(new Reference($builderConfig['cache_pool']));
            }

            $builder->replaceArgument(0, new Reference($storageId));

            if ('auto' === $builderConfig['lock_factory']) {
                if (interface_exists(LockInterface::class)) {
                    $lockFactories['limiter_builder'] = [1, null];
                }
            } elseif ($builderConfig['lock_factory']) {
                if (!interface_exists(LockInterface::class)) {
                    throw new LogicException('Rate Limiter Builder requires the Lock component to be installed. Try running "composer require symfony/lock".');
                }

                if ('lock.factory' === $builderConfig['lock_factory']) {
                    $lockFactories['limiter_builder'] = [1, 'Rate Limiter Builder'];
                } else {
                    $builder->replaceArgument(1, new Reference($builderConfig['lock_factory']));
                }
            }

            $container->setAlias(RateLimiterBuilder::class, 'limiter_builder');
        } else {
            $container->removeDefinition('limiter_builder');
        }

        if ($lockFactories) {
            $container->setParameter('.rate_limiter.lock_factories', $lockFactories);
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

    private function getPublicDirectory(ContainerBuilder $container): string
    {
        $projectDir = $container->getParameter('kernel.project_dir');
        $defaultPublicDir = $projectDir.'/public';

        $composerFilePath = $projectDir.'/composer.json';

        if (!file_exists($composerFilePath)) {
            return $defaultPublicDir;
        }

        $container->addResource(new FileResource($composerFilePath));
        $composerConfig = json_decode((new Filesystem())->readFile($composerFilePath), true, flags: \JSON_THROW_ON_ERROR);

        return isset($composerConfig['extra']['public-dir']) ? $projectDir.'/'.$composerConfig['extra']['public-dir'] : $defaultPublicDir;
    }
}
