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
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface as PsrClockInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerAwareInterface;
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
use Symfony\Component\AssetMapper\Compressor\CompressorInterface;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Config\Builder\ConfigBuilderGenerator;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\ResourceCheckerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\DataCollector\CommandDataCollector;
use Symfony\Component\Console\Debug\CliRequest;
use Symfony\Component\Console\Messenger\RunCommandMessageHandler;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\ArgumentTrait;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\EnvVarLoaderInterface;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Dotenv\Command\DebugCommand;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;
use Symfony\Component\Form\EnumFormTypeGuesser;
use Symfony\Component\Form\Extension\HtmlSanitizer\Type\TextTypeHtmlSanitizerExtension;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\Exception\ChunkCacheItemNotFoundException;
use Symfony\Component\HttpClient\Messenger\PingWebhookMessageHandler;
use Symfony\Component\HttpClient\MockHttpClient;
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
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\EventListener\IsSignatureValidAttributeListener;
use Symfony\Component\HttpKernel\Log\DebugLoggerConfigurator;
use Symfony\Component\HttpKernel\Profiler\ProfilerStateChecker;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\JsonStreamer\JsonStreamWriter;
use Symfony\Component\JsonStreamer\StreamReaderInterface;
use Symfony\Component\JsonStreamer\StreamWriterInterface;
use Symfony\Component\JsonStreamer\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Serializer\LockKeyNormalizer;
use Symfony\Component\Lock\Store\StoreFactory;
use Symfony\Component\Mailer\Bridge as MailerBridge;
use Symfony\Component\Mailer\Command\MailerTestCommand;
use Symfony\Component\Mailer\EventListener\DkimSignedMessageListener;
use Symfony\Component\Mailer\EventListener\MessengerTransportListener;
use Symfony\Component\Mailer\EventListener\SmimeEncryptedMessageListener;
use Symfony\Component\Mailer\EventListener\SmimeSignedMessageListener;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Bridge as MessengerBridge;
use Symfony\Component\Messenger\EventListener\ResetMemoryUsageListener;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\AddDefaultStampsMiddleware;
use Symfony\Component\Messenger\Middleware\DeduplicateMiddleware;
use Symfony\Component\Messenger\Middleware\RouterContextMiddleware;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpTransportFactory;
use Symfony\Component\Messenger\Transport\RedisExt\RedisTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface as MessengerTransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Notifier\Bridge as NotifierBridge;
use Symfony\Component\Notifier\Bridge\FakeChat\FakeChatTransportFactory;
use Symfony\Component\Notifier\Bridge\FakeSms\FakeSmsTransportFactory;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface as NotifierTransportFactoryInterface;
use Symfony\Component\ObjectMapper\ConditionCallableInterface;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\TransformCallableInterface;
use Symfony\Component\Process\Messenger\RunProcessMessageHandler;
use Symfony\Component\PropertyAccess\PropertyAccessor;
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
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\Loader\XmlFileLoader as RoutingXmlFileLoader;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Messenger\SchedulerTransportFactory;
use Symfony\Component\Scheduler\Messenger\Serializer\Normalizer\SchedulerTriggerNormalizer;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Semaphore\PersistingStoreInterface as SemaphoreStoreInterface;
use Symfony\Component\Semaphore\Semaphore;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\StoreFactory as SemaphoreStoreFactory;
use Symfony\Component\Serializer\Attribute as SerializerMapping;
use Symfony\Component\Serializer\Attribute\ExtendsSerializationFor;
use Symfony\Component\Serializer\DependencyInjection\AttributeMetadataPass as SerializerAttributeMetadataPass;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\NameConverter\SnakeCaseToCamelCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NumberNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\String\LazyString;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Translation\Bridge as TranslationBridge;
use Symfony\Component\Translation\Command\TranslationLintCommand as BaseTranslationLintCommand;
use Symfony\Component\Translation\Command\XliffLintCommand as BaseXliffLintCommand;
use Symfony\Component\Translation\Extractor\PhpAstExtractor;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Translation\PseudoLocalizationTranslator;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\PhpDocAwareReflectionTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;
use Symfony\Component\Uid\Factory\UuidFactory;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Validator\Attribute\ExtendsValidationFor;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\ExpressionLanguageProvider;
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\DependencyInjection\AttributeMetadataPass as ValidatorAttributeMetadataPass;
use Symfony\Component\Validator\GroupProviderInterface;
use Symfony\Component\Validator\Mapping\Loader\PropertyInfoLoader;
use Symfony\Component\Validator\ObjectInitializerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Webhook\Controller\WebhookController;
use Symfony\Component\WebLink\HttpHeaderParser;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\Workflow;
use Symfony\Component\Workflow\Arc;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Yaml\Command\LintCommand as BaseYamlLintCommand;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\CallbackInterface;
use Symfony\Contracts\Cache\NamespacedPoolInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
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

        if (!ContainerBuilder::willBeAvailable('symfony/clock', ClockInterface::class, ['symfony/framework-bundle'])) {
            $container->removeDefinition('clock');
            $container->removeAlias(ClockInterface::class);
            $container->removeAlias(PsrClockInterface::class);
        }

        $container->registerAliasForArgument('parameter_bag', PsrContainerInterface::class);

        $loader->load('process.php');

        if (!class_exists(RunProcessMessageHandler::class)) {
            $container->removeDefinition('process.messenger.process_message_handler');
        }
        if (!class_exists(IsSignatureValidAttributeListener::class)) {
            $container->removeDefinition('controller.is_signature_valid_attribute_listener');
        }
        if (!class_exists(ConfigBuilderGenerator::class)) {
            $container->removeDefinition('config_builder.warmer');
        }

        if ($this->hasConsole()) {
            $loader->load('console.php');

            if (!class_exists(BaseXliffLintCommand::class)) {
                $container->removeDefinition('console.command.xliff_lint');
            }
            if (!class_exists(BaseYamlLintCommand::class)) {
                $container->removeDefinition('console.command.yaml_lint');
            }

            if (!class_exists(BaseTranslationLintCommand::class)) {
                $container->removeDefinition('console.command.translation_lint');
            }

            if (!class_exists(DebugCommand::class)) {
                $container->removeDefinition('console.command.dotenv_debug');
            }

            if (!class_exists(RunCommandMessageHandler::class)) {
                $container->removeDefinition('console.messenger.application');
                $container->removeDefinition('console.messenger.execute_command_handler');
            }
        }

        // Load Cache configuration first as it is used by other components
        $loader->load('cache.php');

        if (!interface_exists(NamespacedPoolInterface::class)) {
            $container->removeAlias(NamespacedPoolInterface::class);
        }

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        // warmup config enabled
        $this->readConfigEnabled('translator', $container, $config['translator']);
        $this->readConfigEnabled('property_access', $container, $config['property_access']);
        $this->readConfigEnabled('profiler', $container, $config['profiler']);
        $this->readConfigEnabled('workflows', $container, $config['workflows']);

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
        } else {
            $container->removeDefinition('cache.asset_mapper');
        }

        if ($this->readConfigEnabled('http_client', $container, $config['http_client'])) {
            $this->readConfigEnabled('rate_limiter', $container, $config['rate_limiter']); // makes sure that isInitializedConfigEnabled() will work
            $this->registerHttpClientConfiguration($config['http_client'], $container, $loader);
        }

        if ($this->readConfigEnabled('mailer', $container, $config['mailer'])) {
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
        $this->registerTranslatorConfiguration($config['translator'], $container, $loader, $config['default_locale'], $config['enabled_locales']);
        $this->registerWorkflowConfiguration($config['workflows'], $container, $loader);
        $this->registerDebugConfiguration($config['php_errors'], $container, $loader);
        $this->registerRouterConfiguration($config['router'], $container, $loader, $config['enabled_locales']);
        $this->registerPropertyAccessConfiguration($config['property_access'], $container, $loader);
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

        if ($typeInfoEnabled = $this->readConfigEnabled('type_info', $container, $config['type_info'])) {
            $this->registerTypeInfoConfiguration($config['type_info'], $container, $loader);
        }

        if ($propertyInfoEnabled) {
            $this->registerPropertyInfoConfiguration($config['property_info'], $container, $loader);
        }

        if ($this->readConfigEnabled('json_streamer', $container, $config['json_streamer'])) {
            if (!$typeInfoEnabled) {
                throw new LogicException('JsonStreamer support cannot be enabled as the TypeInfo component is not '.(interface_exists(TypeResolverInterface::class) ? 'enabled.' : 'installed. Try running "composer require symfony/type-info".'));
            }

            $this->registerJsonStreamerConfiguration($config['json_streamer'], $container, $loader);
        }

        if ($this->readConfigEnabled('lock', $container, $config['lock'])) {
            $this->registerLockConfiguration($config['lock'], $container, $loader);
        }

        if ($this->readConfigEnabled('semaphore', $container, $config['semaphore'])) {
            $this->registerSemaphoreConfiguration($config['semaphore'], $container, $loader);
        }

        if ($this->readConfigEnabled('rate_limiter', $container, $config['rate_limiter'])) {
            if (!interface_exists(LimiterInterface::class)) {
                throw new LogicException('Rate limiter support cannot be enabled as the RateLimiter component is not installed. Try running "composer require symfony/rate-limiter".');
            }

            $this->registerRateLimiterConfiguration($config['rate_limiter'], $container, $loader);
        }

        if ($this->readConfigEnabled('web_link', $container, $config['web_link'])) {
            if (!class_exists(HttpHeaderSerializer::class)) {
                throw new LogicException('WebLink support cannot be enabled as the WebLink component is not installed. Try running "composer require symfony/weblink".');
            }

            $loader->load('web_link.php');

            // Require symfony/web-link 7.4
            if (!class_exists(HttpHeaderParser::class)) {
                $container->removeDefinition('web_link.http_header_parser');
            }
        }

        if ($this->readConfigEnabled('uid', $container, $config['uid'])) {
            if (!class_exists(UuidFactory::class)) {
                throw new LogicException('Uid support cannot be enabled as the Uid component is not installed. Try running "composer require symfony/uid".');
            }

            $this->registerUidConfiguration($config['uid'], $container, $loader);
        } else {
            $container->removeDefinition('argument_resolver.uid');
        }

        // register cache before session so both can share the connection services
        $this->registerCacheConfiguration($config['cache'], $container);

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
            if (!$this->readConfigEnabled('html_sanitizer', $container, $config['html_sanitizer']) || !class_exists(TextTypeHtmlSanitizerExtension::class)) {
                $container->removeDefinition('form.type_extension.form.html_sanitizer');
            }
        } else {
            $container->removeDefinition('console.command.form_debug');
        }

        // validation depends on form, annotations being registered
        $this->registerValidationConfiguration($config['validation'], $container, $loader, $propertyInfoEnabled);

        $messengerEnabled = $this->readConfigEnabled('messenger', $container, $config['messenger']);

        if ($this->readConfigEnabled('scheduler', $container, $config['scheduler'])) {
            if (!$messengerEnabled) {
                throw new LogicException('Scheduler support cannot be enabled as the Messenger component is not '.(interface_exists(MessageBusInterface::class) ? 'enabled.' : 'installed. Try running "composer require symfony/messenger".'));
            }
            $this->registerSchedulerConfiguration($container, $loader);
        } else {
            $container->removeDefinition('cache.scheduler');
            $container->removeDefinition('console.command.scheduler_debug');
        }

        // messenger depends on validation, and lock being registered
        if ($messengerEnabled) {
            $this->registerMessengerConfiguration($config['messenger'], $container, $loader, $this->readConfigEnabled('validation', $container, $config['validation']), $this->readConfigEnabled('lock', $container, $config['lock']) && ($config['lock']['resources']['default'] ?? false));
        } else {
            $container->removeDefinition('console.command.messenger_consume_messages');
            $container->removeDefinition('console.command.messenger_stats');
            $container->removeDefinition('console.command.messenger_debug');
            $container->removeDefinition('console.command.messenger_stop_workers');
            $container->removeDefinition('console.command.messenger_setup_transports');
            $container->removeDefinition('console.command.messenger_failed_messages_retry');
            $container->removeDefinition('console.command.messenger_failed_messages_show');
            $container->removeDefinition('console.command.messenger_failed_messages_remove');
            $container->removeDefinition('cache.messenger.restart_workers_signal');

            if ($container->hasDefinition('messenger.transport.amqp.factory') && !class_exists(MessengerBridge\Amqp\Transport\AmqpTransportFactory::class)) {
                if (class_exists(AmqpTransportFactory::class)) {
                    $container->getDefinition('messenger.transport.amqp.factory')
                        ->setClass(AmqpTransportFactory::class)
                        ->addTag('messenger.transport_factory');
                } else {
                    $container->removeDefinition('messenger.transport.amqp.factory');
                }
            }

            if ($container->hasDefinition('messenger.transport.redis.factory') && !class_exists(MessengerBridge\Redis\Transport\RedisTransportFactory::class)) {
                if (class_exists(RedisTransportFactory::class)) {
                    $container->getDefinition('messenger.transport.redis.factory')
                        ->setClass(RedisTransportFactory::class)
                        ->addTag('messenger.transport_factory');
                } else {
                    $container->removeDefinition('messenger.transport.redis.factory');
                }
            }
        }

        // notifier depends on messenger, mailer being registered
        if ($this->readConfigEnabled('notifier', $container, $config['notifier'])) {
            $this->registerNotifierConfiguration($config['notifier'], $container, $loader, $this->readConfigEnabled('webhook', $container, $config['webhook']));
        }

        // profiler depends on form, validation, translation, messenger, mailer, http-client, notifier, serializer being registered
        $this->registerProfilerConfiguration($config['profiler'], $container, $loader);

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

        if ($this->readConfigEnabled('remote-event', $container, $config['remote-event'])) {
            $this->registerRemoteEventConfiguration($loader);
        }

        if ($this->readConfigEnabled('html_sanitizer', $container, $config['html_sanitizer'])) {
            if (!class_exists(HtmlSanitizerConfig::class)) {
                throw new LogicException('HtmlSanitizer support cannot be enabled as the HtmlSanitizer component is not installed. Try running "composer require symfony/html-sanitizer".');
            }

            $this->registerHtmlSanitizerConfiguration($config['html_sanitizer'], $container, $loader);
        }

        if (ContainerBuilder::willBeAvailable('symfony/mime', MimeTypes::class, ['symfony/framework-bundle'])) {
            $loader->load('mime_type.php');
        }

        if (ContainerBuilder::willBeAvailable('symfony/object-mapper', ObjectMapperInterface::class, ['symfony/framework-bundle'])) {
            $loader->load('object_mapper.php');
            $container->registerForAutoconfiguration(TransformCallableInterface::class)
                ->addTag('object_mapper.transform_callable');
            $container->registerForAutoconfiguration(ConditionCallableInterface::class)
                ->addTag('object_mapper.condition_callable');
        }

        $container->registerForAutoconfiguration(PackageInterface::class)
            ->addTag('assets.package');
        $container->registerForAutoconfiguration(AssetCompilerInterface::class)
            ->addTag('asset_mapper.compiler');
        $container->registerAttributeForAutoconfiguration(AsCommand::class, static function (ChildDefinition $definition, AsCommand $attribute): void {
            $definition->addTag('console.command', [
                'command' => $attribute->name,
                'description' => $attribute->description,
                'help' => $attribute->help ?? null,
            ]);
        });
        $container->registerForAutoconfiguration(Command::class)
            ->addTag('console.command');
        $container->registerForAutoconfiguration(ResourceCheckerInterface::class)
            ->addTag('config_cache.resource_checker');
        $container->registerForAutoconfiguration(EnvVarLoaderInterface::class)
            ->addTag('container.env_var_loader');
        $container->registerForAutoconfiguration(EnvVarProcessorInterface::class)
            ->addTag('container.env_var_processor');
        $container->registerForAutoconfiguration(CallbackInterface::class)
            ->addTag('container.reversible');
        $container->registerForAutoconfiguration(ServiceLocator::class)
            ->addTag('container.service_locator');
        $container->registerForAutoconfiguration(ServiceSubscriberInterface::class)
            ->addTag('container.service_subscriber');
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
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)
            ->addTag('event_dispatcher.dispatcher');
        $container->registerForAutoconfiguration(EventSubscriberInterface::class)
            ->addTag('kernel.event_subscriber');
        $container->registerForAutoconfiguration(LocaleAwareInterface::class)
            ->addTag('kernel.locale_aware');
        $container->registerForAutoconfiguration(ResetInterface::class)
            ->addTag('kernel.reset', ['method' => 'reset']);

        if (!interface_exists(MarshallerInterface::class)) {
            $container->registerForAutoconfiguration(ResettableInterface::class)
                ->addTag('kernel.reset', ['method' => 'reset']);
        }

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
        $container->registerForAutoconfiguration(BatchHandlerInterface::class)
            ->addTag('messenger.message_handler');
        $container->registerForAutoconfiguration(MessengerTransportFactoryInterface::class)
            ->addTag('messenger.transport_factory');
        $container->registerForAutoconfiguration(MimeTypeGuesserInterface::class)
            ->addTag('mime.mime_type_guesser');
        $container->registerForAutoconfiguration(LoggerAwareInterface::class)
            ->addMethodCall('setLogger', [new Reference('logger')]);

        $container->registerAttributeForAutoconfiguration(AsEventListener::class, static function (ChildDefinition $definition, AsEventListener $attribute, \ReflectionClass|\ReflectionMethod $reflector) {
            $tagAttributes = get_object_vars($attribute);
            if ($reflector instanceof \ReflectionMethod) {
                if (isset($tagAttributes['method'])) {
                    throw new LogicException(\sprintf('AsEventListener attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name));
                }
                $tagAttributes['method'] = $reflector->getName();
            }
            $definition->addTag('kernel.event_listener', $tagAttributes);
        });
        $container->registerAttributeForAutoconfiguration(AsController::class, static function (ChildDefinition $definition, AsController $attribute): void {
            $definition->addTag('controller.service_arguments');
        });
        $container->registerAttributeForAutoconfiguration(Route::class, static function (ChildDefinition $definition, Route $attribute, \ReflectionClass|\ReflectionMethod $reflection): void {
            $definition->addTag('controller.service_arguments')->addTag('routing.controller');
        });
        $container->registerAttributeForAutoconfiguration(AsRemoteEventConsumer::class, static function (ChildDefinition $definition, AsRemoteEventConsumer $attribute): void {
            $definition->addTag('remote_event.consumer', ['consumer' => $attribute->name]);
        });
        $container->registerAttributeForAutoconfiguration(AsMessageHandler::class, static function (ChildDefinition $definition, AsMessageHandler $attribute, \ReflectionClass|\ReflectionMethod $reflector): void {
            $tagAttributes = get_object_vars($attribute);
            $tagAttributes['from_transport'] = $tagAttributes['fromTransport'];
            unset($tagAttributes['fromTransport']);
            if ($reflector instanceof \ReflectionMethod) {
                if (isset($tagAttributes['method'])) {
                    throw new LogicException(\sprintf('AsMessageHandler attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name));
                }
                $tagAttributes['method'] = $reflector->getName();
            }
            $definition->addTag('messenger.message_handler', $tagAttributes);
        });
        $container->registerAttributeForAutoconfiguration(AsTargetedValueResolver::class, static function (ChildDefinition $definition, AsTargetedValueResolver $attribute): void {
            $definition->addTag('controller.targeted_value_resolver', $attribute->name ? ['name' => $attribute->name] : []);
        });
        $container->registerAttributeForAutoconfiguration(AsSchedule::class, static function (ChildDefinition $definition, AsSchedule $attribute): void {
            $definition->addTag('scheduler.schedule_provider', ['name' => $attribute->name]);
        });
        foreach ([AsPeriodicTask::class, AsCronTask::class] as $taskAttributeClass) {
            $container->registerAttributeForAutoconfiguration(
                $taskAttributeClass,
                static function (ChildDefinition $definition, AsPeriodicTask|AsCronTask $attribute, \ReflectionClass|\ReflectionMethod $reflector): void {
                    $tagAttributes = get_object_vars($attribute) + [
                        'trigger' => match (true) {
                            $attribute instanceof AsPeriodicTask => 'every',
                            $attribute instanceof AsCronTask => 'cron',
                        },
                    ];
                    if ($reflector instanceof \ReflectionMethod) {
                        if (isset($tagAttributes['method'])) {
                            throw new LogicException(\sprintf('"%s" attribute cannot declare a method on "%s::%s()".', $attribute::class, $reflector->class, $reflector->name));
                        }
                        $tagAttributes['method'] = $reflector->getName();
                    }
                    $definition->addTag('scheduler.task', $tagAttributes);
                }
            );
        }

        $container->registerForAutoconfiguration(CompilerPassInterface::class)
            ->addTag('container.excluded', ['source' => 'because it\'s a compiler pass']);
        $container->registerForAutoconfiguration(Constraint::class)
            ->addTag('container.excluded', ['source' => 'because it\'s a validation constraint']);
        $container->registerForAutoconfiguration(TestCase::class)
            ->addTag('container.excluded', ['source' => 'because it\'s a test case']);
        $container->registerForAutoconfiguration(\UnitEnum::class)
            ->addTag('container.excluded', ['source' => 'because it\'s an enum']);
        $container->registerAttributeForAutoconfiguration(AsMessage::class, static function (ChildDefinition $definition) {
            $definition->addTag('container.excluded', ['source' => 'because it\'s a messenger message']);
        });
        $container->registerAttributeForAutoconfiguration(\Attribute::class, static function (ChildDefinition $definition) {
            $definition->addTag('container.excluded', ['source' => 'because it\'s a PHP attribute']);
        });
        $container->registerAttributeForAutoconfiguration(Entity::class, static function (ChildDefinition $definition) {
            $definition->addTag('container.excluded', ['source' => 'because it\'s a Doctrine entity']);
        });
        $container->registerAttributeForAutoconfiguration(Embeddable::class, static function (ChildDefinition $definition) {
            $definition->addTag('container.excluded', ['source' => 'because it\'s a Doctrine embeddable']);
        });
        $container->registerAttributeForAutoconfiguration(MappedSuperclass::class, static function (ChildDefinition $definition) {
            $definition->addTag('container.excluded', ['source' => 'because it\'s a Doctrine mapped superclass']);
        });

        $container->registerAttributeForAutoconfiguration(JsonStreamable::class, static function (ChildDefinition $definition, JsonStreamable $attribute) {
            $definition->addTag('json_streamer.streamable', [
                'object' => $attribute->asObject,
                'list' => $attribute->asList,
            ])->addTag('container.excluded', ['source' => 'because it\'s a streamable JSON']);
        });

        if (!$container->getParameter('kernel.debug')) {
            // remove tagged iterator argument for resource checkers
            $container->getDefinition('config_cache_factory')->setArguments([]);
        }

        if (!$config['disallow_search_engine_index']) {
            $container->removeDefinition('disallow_search_engine_index_response_listener');
        }

        $container->registerForAutoconfiguration(RouteLoaderInterface::class)
            ->addTag('routing.route_loader');

        $container->setParameter('container.behavior_describing_tags', [
            'container.do_not_inline',
            'container.service_locator',
            'container.service_subscriber',
            'kernel.event_subscriber',
            'kernel.event_listener',
            'kernel.locale_aware',
            'kernel.reset',
        ]);
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

        if (!class_exists(EnumFormTypeGuesser::class)) {
            $container->removeDefinition('form.type_guesser.enum_type');
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

        $container->setParameter('fragment.renderer.hinclude.global_template', $config['hinclude_default_template']);

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

        if (!class_exists(ProfilerStateChecker::class)) {
            $container->removeDefinition('profiler.state_checker');
            $container->removeDefinition('profiler.is_disabled_state_checker');
        }

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

        if ($this->isInitializedConfigEnabled('messenger')) {
            $loader->load('messenger_debug.php');
        }

        if ($this->isInitializedConfigEnabled('mailer')) {
            $loader->load('mailer_debug.php');
        }

        if ($this->isInitializedConfigEnabled('workflows')) {
            $loader->load('workflow_debug.php');
        }

        if ($this->isInitializedConfigEnabled('http_client')) {
            $loader->load('http_client_debug.php');
        }

        if ($this->isInitializedConfigEnabled('notifier')) {
            $loader->load('notifier_debug.php');
        }

        if (false === $config['collect_serializer_data']) {
            trigger_deprecation('symfony/framework-bundle', '7.3', 'Not setting the "framework.profiler.collect_serializer_data" config option to "true" is deprecated.');
        }

        if ($this->isInitializedConfigEnabled('serializer') && $config['collect_serializer_data']) {
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

        $container->getDefinition('profiler_listener')
            ->addArgument($config['collect_parameter']);

        if (!$container->getParameter('kernel.debug') || !class_exists(CliRequest::class) || !$container->has('debug.stopwatch')) {
            $container->removeDefinition('console_profiler_listener');
        }

        if (!class_exists(CommandDataCollector::class)) {
            $container->removeDefinition('.data_collector.command');
        }
    }

    private function registerWorkflowConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$config['enabled']) {
            $container->removeDefinition('console.command.workflow_dump');

            return;
        }

        if (!class_exists(Workflow\Workflow::class)) {
            throw new LogicException('Workflow support cannot be enabled as the Workflow component is not installed. Try running "composer require symfony/workflow".');
        }

        $loader->load('workflow.php');

        $registryDefinition = $container->getDefinition('workflow.registry');

        foreach ($config['workflows'] as $name => $workflow) {
            $type = $workflow['type'];
            $workflowId = \sprintf('%s.%s', $type, $name);

            // Process Metadata (workflow + places (transition is done in the "create transition" block))
            $metadataStoreDefinition = new Definition(Workflow\Metadata\InMemoryMetadataStore::class, [[], [], null]);
            if ($workflow['metadata']) {
                $metadataStoreDefinition->replaceArgument(0, $workflow['metadata']);
            }
            $placesMetadata = [];
            foreach ($workflow['places'] as $place) {
                if ($place['metadata']) {
                    $placesMetadata[$place['name']] = $place['metadata'];
                }
            }
            if ($placesMetadata) {
                $metadataStoreDefinition->replaceArgument(1, $placesMetadata);
            }

            // Create transitions
            $transitions = [];
            $guardsConfiguration = [];
            $transitionsMetadataDefinition = new Definition(\SplObjectStorage::class);
            // Global transition counter per workflow
            $transitionCounter = 0;
            foreach ($workflow['transitions'] as $transition) {
                foreach (['from', 'to'] as $direction) {
                    foreach ($transition[$direction] as $k => $arc) {
                        $transition[$direction][$k] = new Definition(Arc::class, [$arc['place'], $arc['weight'] ?? 1]);
                    }
                }
                if ('workflow' === $type) {
                    $transitionId = \sprintf('.%s.transition.%s', $workflowId, $transitionCounter++);
                    $container->register($transitionId, Workflow\Transition::class)
                        ->setArguments([$transition['name'], $transition['from'], $transition['to']]);
                    $transitions[] = new Reference($transitionId);
                    if (isset($transition['guard'])) {
                        $eventName = \sprintf('workflow.%s.guard.%s', $name, $transition['name']);
                        $guardsConfiguration[$eventName][] = new Definition(
                            Workflow\EventListener\GuardExpression::class,
                            [new Reference($transitionId), $transition['guard']]
                        );
                    }
                    if ($transition['metadata']) {
                        $transitionsMetadataDefinition->addMethodCall('offsetSet', [
                            new Reference($transitionId),
                            $transition['metadata'],
                        ]);
                    }
                } elseif ('state_machine' === $type) {
                    foreach ($transition['from'] as $from) {
                        foreach ($transition['to'] as $to) {
                            $transitionId = \sprintf('.%s.transition.%s', $workflowId, $transitionCounter++);
                            $container->register($transitionId, Workflow\Transition::class)
                                ->setArguments([$transition['name'], [$from], [$to]]);
                            $transitions[] = new Reference($transitionId);
                            if (isset($transition['guard'])) {
                                $eventName = \sprintf('workflow.%s.guard.%s', $name, $transition['name']);
                                $guardsConfiguration[$eventName][] = new Definition(
                                    Workflow\EventListener\GuardExpression::class,
                                    [new Reference($transitionId), $transition['guard']]
                                );
                            }
                            if ($transition['metadata']) {
                                $transitionsMetadataDefinition->addMethodCall('offsetSet', [
                                    new Reference($transitionId),
                                    $transition['metadata'],
                                ]);
                            }
                        }
                    }
                }
            }
            $metadataStoreDefinition->replaceArgument(2, $transitionsMetadataDefinition);
            $metadataStoreId = \sprintf('%s.metadata_store', $workflowId);
            $container->setDefinition($metadataStoreId, $metadataStoreDefinition);

            // Create places
            $places = array_column($workflow['places'], 'name');
            $initialMarking = $workflow['initial_marking'] ?? [];

            // Create a Definition
            $definitionDefinition = new Definition(Workflow\Definition::class);
            $definitionDefinition->addArgument($places);
            $definitionDefinition->addArgument($transitions);
            $definitionDefinition->addArgument($initialMarking);
            $definitionDefinition->addArgument(new Reference($metadataStoreId));
            $definitionDefinitionId = \sprintf('%s.definition', $workflowId);

            // Create MarkingStore
            $markingStoreDefinition = null;
            if (isset($workflow['marking_store']['type']) || isset($workflow['marking_store']['property'])) {
                $markingStoreDefinition = new ChildDefinition('workflow.marking_store.method');
                $markingStoreDefinition->setArguments([
                    'state_machine' === $type, // single state
                    $workflow['marking_store']['property'] ?? 'marking',
                ]);
            } elseif (isset($workflow['marking_store']['service'])) {
                $markingStoreDefinition = new Reference($workflow['marking_store']['service']);
            }

            // Validation
            $workflow['definition_validators'][] = match ($workflow['type']) {
                'state_machine' => Workflow\Validator\StateMachineValidator::class,
                'workflow' => Workflow\Validator\WorkflowValidator::class,
                default => throw new \LogicException(\sprintf('Invalid workflow type "%s".', $workflow['type'])),
            };

            // Create Workflow
            $workflowDefinition = new ChildDefinition(\sprintf('%s.abstract', $type));
            $workflowDefinition->replaceArgument(0, new Reference($definitionDefinitionId));
            $workflowDefinition->replaceArgument(1, $markingStoreDefinition);
            $workflowDefinition->replaceArgument(3, $name);
            $workflowDefinition->replaceArgument(4, $workflow['events_to_dispatch']);

            $workflowDefinition->addTag('workflow', [
                'name' => $name,
                'metadata' => $workflow['metadata'],
                'definition_validators' => $workflow['definition_validators'],
                'definition_id' => $definitionDefinitionId,
            ]);
            if ('workflow' === $type) {
                $workflowDefinition->addTag('workflow.workflow', ['name' => $name]);
            } elseif ('state_machine' === $type) {
                $workflowDefinition->addTag('workflow.state_machine', ['name' => $name]);
            }

            // Store to container
            $container->setDefinition($workflowId, $workflowDefinition);
            $container->setDefinition($definitionDefinitionId, $definitionDefinition);
            $container->registerAliasForArgument($workflowId, WorkflowInterface::class, $name.'.'.$type, $name);

            // Add workflow to Registry
            if ($workflow['supports']) {
                foreach ($workflow['supports'] as $supportedClassName) {
                    $strategyDefinition = new Definition(Workflow\SupportStrategy\InstanceOfSupportStrategy::class, [$supportedClassName]);
                    $registryDefinition->addMethodCall('addWorkflow', [new Reference($workflowId), $strategyDefinition]);
                }
            } elseif (isset($workflow['support_strategy'])) {
                $registryDefinition->addMethodCall('addWorkflow', [new Reference($workflowId), new Reference($workflow['support_strategy'])]);
            }

            // Enable the AuditTrail
            if ($workflow['audit_trail']['enabled']) {
                $listener = new Definition(Workflow\EventListener\AuditTrailListener::class);
                $listener->addTag('monolog.logger', ['channel' => 'workflow']);
                $listener->addTag('kernel.event_listener', ['event' => \sprintf('workflow.%s.leave', $name), 'method' => 'onLeave']);
                $listener->addTag('kernel.event_listener', ['event' => \sprintf('workflow.%s.transition', $name), 'method' => 'onTransition']);
                $listener->addTag('kernel.event_listener', ['event' => \sprintf('workflow.%s.enter', $name), 'method' => 'onEnter']);
                $listener->addArgument(new Reference('logger'));
                $container->setDefinition(\sprintf('.%s.listener.audit_trail', $workflowId), $listener);
            }

            // Add Guard Listener
            if ($guardsConfiguration) {
                if (!class_exists(ExpressionLanguage::class)) {
                    throw new LogicException('Cannot guard workflows as the ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
                }

                if (!class_exists(AuthenticationEvents::class)) {
                    throw new LogicException('Cannot guard workflows as the Security component is not installed. Try running "composer require symfony/security-core".');
                }

                $guard = new Definition(Workflow\EventListener\GuardListener::class);

                $guard->setArguments([
                    $guardsConfiguration,
                    new Reference('workflow.security.expression_language'),
                    new Reference('security.token_storage'),
                    new Reference('security.authorization_checker'),
                    new Reference('security.authentication.trust_resolver'),
                    new Reference('security.role_hierarchy'),
                    new Reference('validator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ]);
                foreach ($guardsConfiguration as $eventName => $config) {
                    $guard->addTag('kernel.event_listener', ['event' => $eventName, 'method' => 'onTransition']);
                }

                $container->setDefinition(\sprintf('.%s.listener.guard', $workflowId), $guard);
                $container->setParameter('workflow.has_guard_listeners', true);
            }
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

    private function registerRouterConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader, array $enabledLocales = []): void
    {
        if (!$this->readConfigEnabled('router', $container, $config)) {
            $container->removeDefinition('console.command.router_debug');
            $container->removeDefinition('console.command.router_match');
            $container->removeDefinition('messenger.middleware.router_context');

            return;
        }
        if (!class_exists(RouterContextMiddleware::class)) {
            $container->removeDefinition('messenger.middleware.router_context');
        }

        $loader->load('routing.php');

        if (!class_exists(RoutingXmlFileLoader::class)) {
            $container->removeDefinition('routing.loader.xml');
        }

        if (!class_exists(AttributeServicesLoader::class)) {
            $container->removeDefinition('routing.loader.attribute.services');
        }

        if ($config['utf8']) {
            $container->getDefinition('routing.loader')->replaceArgument(1, ['utf8' => true]);
        }

        if ($enabledLocales) {
            $enabledLocales = implode('|', array_map('preg_quote', $enabledLocales));
            $container->getDefinition('routing.loader')->replaceArgument(2, ['_locale' => $enabledLocales]);
        }

        if (!ContainerBuilder::willBeAvailable('symfony/expression-language', ExpressionLanguage::class, ['symfony/framework-bundle', 'symfony/routing'])) {
            $container->removeDefinition('router.expression_language_provider');
        }

        $container->setParameter('router.resource', $config['resource']);
        $container->setParameter('router.cache_dir', $config['cache_dir']);
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
            $container->getDefinition('router.request_context')
                ->replaceArgument(0, $config['default_uri']);
        }
    }

    private function registerSessionConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('session.php');

        // session storage
        $container->setAlias('session.storage.factory', $config['storage_factory_id']);

        $options = ['cache_limiter' => '0'];
        foreach (['name', 'cookie_lifetime', 'cookie_path', 'cookie_domain', 'cookie_secure', 'cookie_httponly', 'cookie_samesite', 'use_cookies', 'gc_maxlifetime', 'gc_probability', 'gc_divisor', 'sid_length', 'sid_bits_per_character'] as $key) {
            if (isset($config[$key])) {
                $options[$key] = $config[$key];
            }
        }

        if ('auto' === ($options['cookie_secure'] ?? null)) {
            $container->getDefinition('session.storage.factory.native')->replaceArgument(3, true);
            $container->getDefinition('session.storage.factory.php_bridge')->replaceArgument(2, true);
        }

        $container->setParameter('session.storage.options', $options);

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
                $id = '.cache_connection.'.ContainerBuilder::hash($config['handler_id']);

                $container->getDefinition('session.abstract_handler')
                    ->replaceArgument(0, $container->hasDefinition($id) ? new Reference($id) : $config['handler_id']);

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
            ->getDefinition('asset_mapper.importmap.config_reader')
            ->replaceArgument(0, $config['importmap_path'])
        ;

        $container
            ->getDefinition('asset_mapper.importmap.renderer')
            ->replaceArgument(3, $config['importmap_polyfill'])
            ->replaceArgument(4, $config['importmap_script_attributes'])
        ;

        if (interface_exists(CompressorInterface::class)) {
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
        } else {
            $container->removeDefinition('asset_mapper.compressor');
            $container->removeDefinition('asset_mapper.assets.command.compress');
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

            return;
        }

        $loader->load('translation.php');

        if (!ContainerBuilder::willBeAvailable('symfony/translation', LocaleSwitcher::class, ['symfony/framework-bundle'])) {
            $container->removeDefinition('translation.locale_switcher');
        }

        // don't use ContainerBuilder::willBeAvailable() as these are not needed in production
        if (interface_exists(Parser::class) && class_exists(PhpAstExtractor::class)) {
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
                    ->filter(fn (\SplFileInfo $file) => 2 <= substr_count($file->getBasename(), '.') && preg_match('/\.\w+$/', $file->getBasename()))
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
                        'scanned_directories' => array_map(fn ($dir) => str_starts_with($dir, $projectDir.'/') ? substr($dir, 1 + \strlen($projectDir)) : $dir, $scannedDirectories),
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
            TranslationBridge\Crowdin\CrowdinProviderFactory::class => 'translation.provider_factory.crowdin',
            TranslationBridge\Loco\LocoProviderFactory::class => 'translation.provider_factory.loco',
            TranslationBridge\Lokalise\LokaliseProviderFactory::class => 'translation.provider_factory.lokalise',
            TranslationBridge\Phrase\PhraseProviderFactory::class => 'translation.provider_factory.phrase',
        ];

        $parentPackages = ['symfony/framework-bundle', 'symfony/translation', 'symfony/http-client'];

        foreach ($classToServices as $class => $service) {
            $package = substr($service, \strlen('translation.provider_factory.'));

            if (!$container->hasDefinition('http_client') || !ContainerBuilder::willBeAvailable(\sprintf('symfony/%s-translation-provider', $package), $class, $parentPackages)) {
                $container->removeDefinition($service);
            }
        }

        if (!$config['providers']) {
            return;
        }

        $locales = $enabledLocales;

        foreach ($config['providers'] as $provider) {
            if ($provider['locales']) {
                $locales += $provider['locales'];
            }
        }

        $locales = array_unique($locales);

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

            return;
        }

        if (!class_exists(Validation::class)) {
            throw new LogicException('Validation support cannot be enabled as the Validator component is not installed. Try running "composer require symfony/validator".');
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
        if (class_exists(ValidatorAttributeMetadataPass::class) && (!($config['enable_attributes'] ?? false) || !$container->getParameter('kernel.debug')) && trait_exists(ArgumentTrait::class)) {
            // The $reflector argument hints at where the attribute could be used
            $container->registerAttributeForAutoconfiguration(Constraint::class, static function (ChildDefinition $definition, Constraint $attribute, \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflector) {
                $definition->addTag('validator.attribute_metadata')
                    ->addTag('container.excluded', ['source' => 'because it\'s a validator constraint extension']);
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
        $fileRecorder = function ($extension, $path) use (&$files) {
            $files['yaml' === $extension ? 'yml' : $extension][] = $path;
        };

        if (!ContainerBuilder::willBeAvailable('symfony/form', Form::class, ['symfony/framework-bundle', 'symfony/validator'])) {
            $container->removeDefinition('validator.form.attribute_metadata');
        } elseif (!($r = new \ReflectionClass(Form::class))->getAttributes(Traverse::class) || !class_exists(ValidatorAttributeMetadataPass::class)) {
            // BC with symfony/form & symfony/validator < 7.4
            $fileRecorder('xml', \dirname($r->getFileName()).'/Resources/config/validation.xml');
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

    private function registerMappingFilesFromDir(string $dir, callable $fileRecorder): void
    {
        foreach (Finder::create()->followLinks()->files()->in($dir)->name('/\.(xml|ya?ml)$/')->sortByName() as $file) {
            $fileRecorder($file->getExtension(), $file->getRealPath());
        }
    }

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

    private function registerPropertyAccessConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$this->readConfigEnabled('property_access', $container, $config)) {
            return;
        }

        $loader->load('property_access.php');

        $magicMethods = PropertyAccessor::DISALLOW_MAGIC_METHODS;
        $magicMethods |= $config['magic_call'] ? PropertyAccessor::MAGIC_CALL : 0;
        $magicMethods |= $config['magic_get'] ? PropertyAccessor::MAGIC_GET : 0;
        $magicMethods |= $config['magic_set'] ? PropertyAccessor::MAGIC_SET : 0;

        $throw = PropertyAccessor::DO_NOT_THROW;
        $throw |= $config['throw_exception_on_invalid_index'] ? PropertyAccessor::THROW_ON_INVALID_INDEX : 0;
        $throw |= $config['throw_exception_on_invalid_property_path'] ? PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH : 0;

        $container
            ->getDefinition('property_accessor')
            ->replaceArgument(0, $magicMethods)
            ->replaceArgument(1, $throw)
        ;
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
            if (!preg_match('/^(?:[-.\w\\\\]*+:)*+\w++$/', $config['decryption_env_var'])) {
                throw new InvalidArgumentException(\sprintf('Invalid value "%s" set as "decryption_env_var": only "word" characters are allowed.', $config['decryption_env_var']));
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

            return;
        }

        $container->getDefinition('security.csrf.same_origin_token_manager')
            ->replaceArgument(3, $config['stateless_token_ids'])
            ->replaceArgument(4, $config['check_header'])
            ->replaceArgument(5, $config['cookie_name']);

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

        if (!$this->isInitializedConfigEnabled('property_access')) {
            $container->removeAlias('serializer.property_accessor');
            $container->removeDefinition('serializer.normalizer.object');
        }

        if (!class_exists(Yaml::class)) {
            $container->removeDefinition('serializer.encoder.yaml');
        }

        if (!$this->isInitializedConfigEnabled('property_access')) {
            $container->removeDefinition('serializer.denormalizer.unwrapping');
        }

        if (!class_exists(Headers::class)) {
            $container->removeDefinition('serializer.normalizer.mime_message');
        }

        // BC layer Serializer < 7.3
        if (!class_exists(NumberNormalizer::class)) {
            $container->removeDefinition('serializer.normalizer.number');
        }

        // BC layer Serializer < 7.2
        if (!class_exists(SnakeCaseToCamelCaseNameConverter::class)) {
            $container->removeDefinition('serializer.name_converter.snake_case_to_camel_case');
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
        if (class_exists(SerializerAttributeMetadataPass::class) && (!($config['enable_attributes'] ?? false) || !$container->getParameter('kernel.debug'))) {
            // The $reflector argument hints at where the attribute could be used
            $configurator = static function (ChildDefinition $definition, object $attribute, \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflector) {
                $definition->addTag('serializer.attribute_metadata')
                    ->addTag('container.excluded', ['source' => 'because it\'s a serializer metadata extension']);
            };
            $container->registerAttributeForAutoconfiguration(SerializerMapping\Context::class, $configurator);
            $container->registerAttributeForAutoconfiguration(SerializerMapping\Groups::class, $configurator);

            $configurator = static function (ChildDefinition $definition, object $attribute, \ReflectionMethod|\ReflectionProperty $reflector) {
                $definition->addTag('serializer.attribute_metadata')
                    ->addTag('container.excluded', ['source' => 'because it\'s a serializer metadata extension']);
            };
            $container->registerAttributeForAutoconfiguration(SerializerMapping\Ignore::class, $configurator);
            $container->registerAttributeForAutoconfiguration(SerializerMapping\MaxDepth::class, $configurator);
            $container->registerAttributeForAutoconfiguration(SerializerMapping\SerializedName::class, $configurator);
            $container->registerAttributeForAutoconfiguration(SerializerMapping\SerializedPath::class, $configurator);

            $container->registerAttributeForAutoconfiguration(SerializerMapping\DiscriminatorMap::class, static function (ChildDefinition $definition) {
                $definition->addTag('serializer.attribute_metadata')
                    ->addTag('container.excluded', ['source' => 'because it\'s a serializer metadata extension']);
            });
        }

        if (($config['enable_attributes'] ?? false) || class_exists(SerializerAttributeMetadataPass::class)) {
            $serializerLoaders[] = new Reference('serializer.mapping.attribute_loader');

            $container->getDefinition('serializer.mapping.attribute_loader')
                ->replaceArgument(0, $config['enable_attributes'] ?? false);
        } else {
            // BC with symfony/serializer < 7.4
            $container->removeDefinition('serializer.mapping.attribute_services_loader');
        }

        $fileRecorder = function ($extension, $path) use (&$serializerLoaders) {
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

    private function registerJsonStreamerConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!class_exists(JsonStreamWriter::class)) {
            throw new LogicException('JsonStreamer support cannot be enabled as the JsonStreamer component is not installed. Try running "composer require symfony/json-streamer".');
        }

        $container->registerForAutoconfiguration(ValueTransformerInterface::class)
            ->addTag('json_streamer.value_transformer');

        $loader->load('json_streamer.php');

        $container->registerAliasForArgument('json_streamer.stream_writer', StreamWriterInterface::class, 'json.stream_writer');
        $container->registerAliasForArgument('json_streamer.stream_reader', StreamReaderInterface::class, 'json.stream_reader');

        $container->setParameter('.json_streamer.stream_writers_dir', '%kernel.cache_dir%/json_streamer/stream_writer');
        $container->setParameter('.json_streamer.stream_readers_dir', '%kernel.cache_dir%/json_streamer/stream_reader');
        $container->setParameter('.json_streamer.lazy_ghosts_dir', '%kernel.cache_dir%/json_streamer/lazy_ghost');

        if (\PHP_VERSION_ID >= 80400) {
            $container->removeDefinition('.json_streamer.cache_warmer.lazy_ghost');
        }
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

    private function registerTypeInfoConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!class_exists(Type::class)) {
            throw new LogicException('TypeInfo support cannot be enabled as the TypeInfo component is not installed. Try running "composer require symfony/type-info".');
        }

        $loader->load('type_info.php');

        if (ContainerBuilder::willBeAvailable('phpstan/phpdoc-parser', PhpDocParser::class, ['symfony/framework-bundle', 'symfony/type-info'])) {
            $container->register('type_info.resolver.string', StringTypeResolver::class)
                ->setArguments([null, null, $config['aliases']]);

            $container->register('type_info.resolver.reflection_parameter.phpdoc_aware', PhpDocAwareReflectionTypeResolver::class)
                ->setArguments([new Reference('type_info.resolver.reflection_parameter'), new Reference('type_info.resolver.string'), new Reference('type_info.type_context_factory')]);
            $container->register('type_info.resolver.reflection_property.phpdoc_aware', PhpDocAwareReflectionTypeResolver::class)
                ->setArguments([new Reference('type_info.resolver.reflection_property'), new Reference('type_info.resolver.string'), new Reference('type_info.type_context_factory')]);
            $container->register('type_info.resolver.reflection_return.phpdoc_aware', PhpDocAwareReflectionTypeResolver::class)
                ->setArguments([new Reference('type_info.resolver.reflection_return'), new Reference('type_info.resolver.string'), new Reference('type_info.type_context_factory')]);

            /** @var ServiceLocatorArgument $resolversLocator */
            $resolversLocator = $container->getDefinition('type_info.resolver')->getArgument(0);
            $resolversLocator->setValues([
                'string' => new Reference('type_info.resolver.string'),
                \ReflectionParameter::class => new Reference('type_info.resolver.reflection_parameter.phpdoc_aware'),
                \ReflectionProperty::class => new Reference('type_info.resolver.reflection_property.phpdoc_aware'),
                \ReflectionFunctionAbstract::class => new Reference('type_info.resolver.reflection_return.phpdoc_aware'),
            ] + $resolversLocator->getValues());

            $container->getDefinition('type_info.type_context_factory')->replaceArgument(1, $config['aliases']);
        }
    }

    private function registerLockConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('lock.php');

        // BC layer Lock < 7.4
        if (!interface_exists(DenormalizerInterface::class) || !class_exists(LockKeyNormalizer::class)) {
            $container->removeDefinition('serializer.normalizer.lock_key');
        }

        foreach ($config['resources'] as $resourceName => $resourceStores) {
            if (0 === \count($resourceStores)) {
                continue;
            }

            // Generate stores
            $storeDefinitions = [];
            foreach ($resourceStores as $resourceStore) {
                $usedEnvs = [];
                $storeDsn = $container->resolveEnvPlaceholders($resourceStore, null, $usedEnvs);
                if (!$usedEnvs && !str_contains($resourceStore, ':') && !\in_array($resourceStore, ['flock', 'semaphore', 'in-memory', 'null'], true)) {
                    $resourceStore = new Reference($resourceStore);
                }
                $storeDefinition = new Definition(PersistingStoreInterface::class);
                $storeDefinition
                    ->setFactory([StoreFactory::class, 'createStore'])
                    ->setArguments([$resourceStore])
                    ->addTag('lock.store');

                $container->setDefinition($storeDefinitionId = '.lock.'.$resourceName.'.store.'.$container->hash($storeDsn), $storeDefinition);

                $storeDefinition = new Reference($storeDefinitionId);

                $storeDefinitions[] = $storeDefinition;
            }

            // Wrap array of stores with CombinedStore
            if (\count($storeDefinitions) > 1) {
                $combinedDefinition = new ChildDefinition('lock.store.combined.abstract');
                $combinedDefinition->replaceArgument(0, $storeDefinitions);
                $container->setDefinition($storeDefinitionId = '.lock.'.$resourceName.'.store.'.$container->hash($resourceStores), $combinedDefinition);
            }

            // Generate factories for each resource
            $factoryDefinition = new ChildDefinition('lock.factory.abstract');
            $factoryDefinition->replaceArgument(0, new Reference($storeDefinitionId));
            $container->setDefinition('lock.'.$resourceName.'.factory', $factoryDefinition);

            // provide alias for default resource
            if ('default' === $resourceName) {
                $container->setAlias('lock.factory', new Alias('lock.'.$resourceName.'.factory', false));
                $container->setAlias(LockFactory::class, new Alias('lock.factory', false));
            } else {
                $container->registerAliasForArgument('lock.'.$resourceName.'.factory', LockFactory::class, $resourceName.'.lock.factory', $resourceName);
            }
        }
    }

    private function registerSemaphoreConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('semaphore.php');

        foreach ($config['resources'] as $resourceName => $resourceStore) {
            $storeDsn = $container->resolveEnvPlaceholders($resourceStore, null, $usedEnvs);
            if (!$usedEnvs && !str_contains($resourceStore, '://')) {
                $resourceStore = new Reference($resourceStore);
            }
            $storeDefinition = new Definition(SemaphoreStoreInterface::class);
            $storeDefinition->setFactory([SemaphoreStoreFactory::class, 'createStore']);
            $storeDefinition->setArguments([$resourceStore]);

            $container->setDefinition($storeDefinitionId = '.semaphore.'.$resourceName.'.store.'.$container->hash($storeDsn), $storeDefinition);

            // Generate factories for each resource
            $factoryDefinition = new ChildDefinition('semaphore.factory.abstract');
            $factoryDefinition->replaceArgument(0, new Reference($storeDefinitionId));
            $container->setDefinition('semaphore.'.$resourceName.'.factory', $factoryDefinition);

            // Generate services for semaphore instances
            $semaphoreDefinition = new Definition(Semaphore::class);
            $semaphoreDefinition->setFactory([new Reference('semaphore.'.$resourceName.'.factory'), 'createSemaphore']);
            $semaphoreDefinition->setArguments([$resourceName]);

            // provide alias for default resource
            if ('default' === $resourceName) {
                $container->setAlias('semaphore.factory', new Alias('semaphore.'.$resourceName.'.factory', false));
                $container->setAlias(SemaphoreFactory::class, new Alias('semaphore.factory', false));
            } else {
                $container->registerAliasForArgument('semaphore.'.$resourceName.'.factory', SemaphoreFactory::class, $resourceName.'.semaphore.factory', $resourceName);
            }
        }
    }

    private function registerSchedulerConfiguration(ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!class_exists(SchedulerTransportFactory::class)) {
            throw new LogicException('Scheduler support cannot be enabled as the Scheduler component is not installed. Try running "composer require symfony/scheduler".');
        }

        $loader->load('scheduler.php');

        if (!$this->hasConsole()) {
            $container->removeDefinition('console.command.scheduler_debug');
        }

        // BC layer Scheduler < 7.3
        if (!ContainerBuilder::willBeAvailable('symfony/serializer', DenormalizerInterface::class, ['symfony/framework-bundle', 'symfony/scheduler']) || !class_exists(SchedulerTriggerNormalizer::class)) {
            $container->removeDefinition('serializer.normalizer.scheduler_trigger');
        }
    }

    private function registerMessengerConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader, bool $validationEnabled, bool $lockEnabled): void
    {
        if (!interface_exists(MessageBusInterface::class)) {
            throw new LogicException('Messenger support cannot be enabled as the Messenger component is not installed. Try running "composer require symfony/messenger".');
        }

        if (!$this->hasConsole()) {
            $container->removeDefinition('console.command.messenger_stats');
        }

        $loader->load('messenger.php');

        if (!interface_exists(DenormalizerInterface::class)) {
            $container->removeDefinition('serializer.normalizer.flatten_exception');
        }

        if (!class_exists(ResetMemoryUsageListener::class)) {
            $container->removeDefinition('messenger.listener.reset_memory_usage');
        }

        if (ContainerBuilder::willBeAvailable('symfony/amqp-messenger', MessengerBridge\Amqp\Transport\AmqpTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.amqp.factory')->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable('symfony/redis-messenger', MessengerBridge\Redis\Transport\RedisTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.redis.factory')->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable('symfony/amazon-sqs-messenger', MessengerBridge\AmazonSqs\Transport\AmazonSqsTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.sqs.factory')->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable('symfony/beanstalkd-messenger', MessengerBridge\Beanstalkd\Transport\BeanstalkdTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.beanstalkd.factory')->addTag('messenger.transport_factory');
        }

        if ($config['stop_worker_on_signals'] && $this->hasConsole()) {
            $container->getDefinition('console.command.messenger_consume_messages')
                ->replaceArgument(8, $config['stop_worker_on_signals']);
            $container->getDefinition('console.command.messenger_failed_messages_retry')
                ->replaceArgument(6, $config['stop_worker_on_signals']);
        }

        if (null === $config['default_bus'] && 1 === \count($config['buses'])) {
            $config['default_bus'] = key($config['buses']);
        }

        $defaultMiddleware = [
            'before' => [
                ['id' => 'add_bus_name_stamp_middleware'],
                ['id' => 'reject_redelivered_message_middleware'],
                ['id' => 'dispatch_after_current_bus'],
                ['id' => 'failed_message_processing_middleware'],
            ],
            'after' => [
                ['id' => 'send_message'],
                ['id' => 'handle_message'],
            ],
        ];

        if (class_exists(AddDefaultStampsMiddleware::class)) {
            array_unshift($defaultMiddleware['before'], ['id' => 'add_default_stamps_middleware']);
        }

        if ($lockEnabled && class_exists(DeduplicateMiddleware::class) && class_exists(LockFactory::class)) {
            $defaultMiddleware['before'][] = ['id' => 'deduplicate_middleware'];
        } else {
            $container->removeDefinition('messenger.middleware.deduplicate_middleware');
        }

        foreach ($config['buses'] as $busId => $bus) {
            $middleware = $bus['middleware'];

            if ($bus['default_middleware']['enabled']) {
                $defaultMiddleware['after'][0]['arguments'] = [$bus['default_middleware']['allow_no_senders']];
                $defaultMiddleware['after'][1]['arguments'] = [$bus['default_middleware']['allow_no_handlers']];

                $middleware = array_merge($defaultMiddleware['before'], $middleware, $defaultMiddleware['after']);
            }

            foreach ($middleware as $key => $middlewareItem) {
                if (!$validationEnabled && \in_array($middlewareItem['id'], ['validation', 'messenger.middleware.validation'], true)) {
                    throw new LogicException('The Validation middleware is only available when the Validator component is installed and enabled. Try running "composer require symfony/validator".');
                }

                // argument to add_bus_name_stamp_middleware
                if ('add_bus_name_stamp_middleware' === $middlewareItem['id']) {
                    $middleware[$key]['arguments'] = [$busId];
                }
            }

            if ($container->getParameter('kernel.debug') && class_exists(Stopwatch::class)) {
                array_unshift($middleware, ['id' => 'traceable', 'arguments' => [$busId]]);
            }

            $container->setParameter($busId.'.middleware', $middleware);
            $container->register($busId, MessageBus::class)->addArgument([])->addTag('messenger.bus');

            if ($busId === $config['default_bus']) {
                $container->setAlias('messenger.default_bus', $busId)->setPublic(true);
                $container->setAlias(MessageBusInterface::class, $busId);
            } else {
                $container->registerAliasForArgument($busId, MessageBusInterface::class);
            }
        }

        if (empty($config['transports'])) {
            $container->removeDefinition('messenger.transport.symfony_serializer');
            $container->removeDefinition('messenger.transport.amqp.factory');
            $container->removeDefinition('messenger.transport.redis.factory');
            $container->removeDefinition('messenger.transport.sqs.factory');
            $container->removeDefinition('messenger.transport.beanstalkd.factory');
            $container->removeAlias(SerializerInterface::class);
        } else {
            $container->getDefinition('messenger.transport.symfony_serializer')
                ->replaceArgument(1, $config['serializer']['symfony_serializer']['format'])
                ->replaceArgument(2, $config['serializer']['symfony_serializer']['context']);
            $container->setAlias('messenger.default_serializer', $config['serializer']['default_serializer']);
        }

        $failureTransports = [];
        if ($config['failure_transport']) {
            if (!isset($config['transports'][$config['failure_transport']])) {
                throw new LogicException(\sprintf('Invalid Messenger configuration: the failure transport "%s" is not a valid transport or service id.', $config['failure_transport']));
            }

            $container->setAlias('messenger.failure_transports.default', 'messenger.transport.'.$config['failure_transport']);
            $failureTransports[] = $config['failure_transport'];
        }

        $failureTransportsByName = [];
        foreach ($config['transports'] as $name => $transport) {
            if ($transport['failure_transport']) {
                $failureTransports[] = $transport['failure_transport'];
                $failureTransportsByName[$name] = $transport['failure_transport'];
            } elseif ($config['failure_transport']) {
                $failureTransportsByName[$name] = $config['failure_transport'];
            }
        }

        $senderAliases = [];
        $transportRetryReferences = [];
        $transportRateLimiterReferences = [];
        $serializerIds = [];
        foreach ($config['transports'] as $name => $transport) {
            $serializerId = $transport['serializer'] ?? 'messenger.default_serializer';
            $tags = [
                'alias' => $name,
                'is_failure_transport' => \in_array($name, $failureTransports, true),
            ];
            if (str_starts_with($transport['dsn'], 'sync://')) {
                $tags['is_consumable'] = false;
            }
            $transportDefinition = (new Definition(TransportInterface::class))
                ->setFactory([new Reference('messenger.transport_factory'), 'createTransport'])
                ->setArguments([$transport['dsn'], $transport['options'] + ['transport_name' => $name], new Reference($serializerId)])
                ->addTag('messenger.receiver', $tags)
            ;
            $container->setDefinition($transportId = 'messenger.transport.'.$name, $transportDefinition);
            $senderAliases[$name] = $transportId;
            $serializerIds[$transportId] = $serializerId;

            if (null !== $transport['retry_strategy']['service']) {
                $transportRetryReferences[$name] = new Reference($transport['retry_strategy']['service']);
            } else {
                $retryServiceId = \sprintf('messenger.retry.multiplier_retry_strategy.%s', $name);
                $retryDefinition = new ChildDefinition('messenger.retry.abstract_multiplier_retry_strategy');
                $retryDefinition
                    ->replaceArgument(0, $transport['retry_strategy']['max_retries'])
                    ->replaceArgument(1, $transport['retry_strategy']['delay'])
                    ->replaceArgument(2, $transport['retry_strategy']['multiplier'])
                    ->replaceArgument(3, $transport['retry_strategy']['max_delay'])
                    ->replaceArgument(4, $transport['retry_strategy']['jitter']);
                $container->setDefinition($retryServiceId, $retryDefinition);

                $transportRetryReferences[$name] = new Reference($retryServiceId);
            }

            if ($transport['rate_limiter']) {
                if (!interface_exists(LimiterInterface::class)) {
                    throw new LogicException('Rate limiter cannot be used within Messenger as the RateLimiter component is not installed. Try running "composer require symfony/rate-limiter".');
                }

                $transportRateLimiterReferences[$name] = new Reference('limiter.'.$transport['rate_limiter']);
            }
        }

        $senderReferences = [];
        foreach ($senderAliases as $alias => $transportId) {
            $senderReferences[$alias] = new Reference($transportId);
        }
        foreach ($senderAliases as $transportId) {
            $senderReferences[$transportId] = new Reference($transportId);
        }

        foreach ($config['transports'] as $name => $transport) {
            if ($transport['failure_transport']) {
                if (!isset($senderReferences[$transport['failure_transport']])) {
                    throw new LogicException(\sprintf('Invalid Messenger configuration: the failure transport "%s" is not a valid transport or service id.', $transport['failure_transport']));
                }
            }
        }

        $failureTransportReferencesByTransportName = array_map(static fn ($failureTransportName) => $senderReferences[$failureTransportName], $failureTransportsByName);

        $messageToSendersMapping = [];
        foreach ($config['routing'] as $message => $messageConfiguration) {
            if ('*' !== $message && !class_exists($message) && !interface_exists($message, false) && !preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+\\\\)++\*$/', $message)) {
                if (str_contains($message, '*')) {
                    throw new LogicException(\sprintf('Invalid Messenger routing configuration: invalid namespace "%s" wildcard.', $message));
                }

                throw new LogicException(\sprintf('Invalid Messenger routing configuration: class or interface "%s" not found.', $message));
            }

            // make sure senderAliases contains all senders
            foreach ($messageConfiguration['senders'] as $sender) {
                if (!isset($senderReferences[$sender])) {
                    throw new LogicException(\sprintf('Invalid Messenger routing configuration: the "%s" class is being routed to a sender called "%s". This is not a valid transport or service id.', $message, $sender));
                }
            }

            $messageToSendersMapping[$message] = $messageConfiguration['senders'];
        }

        $sendersServiceLocator = ServiceLocatorTagPass::register($container, $senderReferences);

        $container->getDefinition('messenger.senders_locator')
            ->replaceArgument(0, $messageToSendersMapping)
            ->replaceArgument(1, $sendersServiceLocator)
        ;

        $messageToSerializersMapping = [];
        foreach ($messageToSendersMapping as $message => $senders) {
            foreach ($senders as $sender) {
                $serializerId = $serializerIds[$senderAliases[$sender] ?? $sender];
                $messageToSerializersMapping[$message][$serializerId] = $serializerId;
            }
            $messageToSerializersMapping[$message] = array_keys($messageToSerializersMapping[$message]);
        }

        $container->getDefinition('messenger.signing_serializer')
            ->replaceArgument(2, $messageToSerializersMapping);

        $container->getDefinition('messenger.retry.send_failed_message_for_retry_listener')
            ->replaceArgument(0, $sendersServiceLocator)
        ;

        $container->getDefinition('messenger.retry_strategy_locator')
            ->replaceArgument(0, $transportRetryReferences);

        if (!$transportRateLimiterReferences) {
            $container->removeDefinition('messenger.rate_limiter_locator');
        } else {
            $container->getDefinition('messenger.rate_limiter_locator')
                ->replaceArgument(0, $transportRateLimiterReferences);
        }

        if (\count($failureTransports) > 0) {
            if ($this->hasConsole()) {
                $container->getDefinition('console.command.messenger_failed_messages_retry')
                    ->replaceArgument(0, $config['failure_transport']);
                $container->getDefinition('console.command.messenger_failed_messages_show')
                    ->replaceArgument(0, $config['failure_transport']);
                $container->getDefinition('console.command.messenger_failed_messages_remove')
                    ->replaceArgument(0, $config['failure_transport']);
            }

            $failureTransportsByTransportNameServiceLocator = ServiceLocatorTagPass::register($container, $failureTransportReferencesByTransportName);
            $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')
                ->replaceArgument(0, $failureTransportsByTransportNameServiceLocator);
        } else {
            $container->removeDefinition('messenger.failure.send_failed_message_to_failure_transport_listener');
            $container->removeDefinition('console.command.messenger_failed_messages_retry');
            $container->removeDefinition('console.command.messenger_failed_messages_show');
            $container->removeDefinition('console.command.messenger_failed_messages_remove');
        }

        if (!$container->hasDefinition('console.command.messenger_consume_messages')) {
            $container->removeDefinition('messenger.listener.reset_services');
        }
    }

    private function registerCacheConfiguration(array $config, ContainerBuilder $container): void
    {
        $version = new Parameter('container.build_id');
        $container->getDefinition('cache.adapter.apcu')->replaceArgument(2, $version);
        $container->getDefinition('cache.adapter.system')->replaceArgument(2, $version);
        $container->getDefinition('cache.adapter.filesystem')->replaceArgument(2, $config['directory']);

        if (isset($config['prefix_seed'])) {
            $container->setParameter('cache.prefix.seed', $config['prefix_seed']);
        }
        if ($container->hasParameter('cache.prefix.seed')) {
            // Inline any env vars referenced in the parameter
            $container->setParameter('cache.prefix.seed', $container->resolveEnvPlaceholders($container->getParameter('cache.prefix.seed'), true));
        }
        foreach (['psr6', 'redis', 'valkey', 'memcached', 'doctrine_dbal', 'pdo'] as $name) {
            if (isset($config[$name = 'default_'.$name.'_provider'])) {
                $container->setAlias('cache.'.$name, new Alias(CachePoolPass::getServiceProvider($container, $config[$name]), false));
            }
        }
        foreach (['app', 'system'] as $name) {
            $config['pools']['cache.'.$name] = [
                'adapters' => [$config[$name]],
                'public' => true,
                'tags' => false,
            ];
        }
        $redisTagAwareAdapters = [['cache.adapter.redis_tag_aware'], ['cache.adapter.valkey_tag_aware']];
        foreach ($config['pools'] as $name => $pool) {
            $pool['adapters'] = $pool['adapters'] ?: ['cache.app'];

            $isRedisTagAware = \in_array($pool['adapters'], $redisTagAwareAdapters, true);
            foreach ($pool['adapters'] as $provider => $adapter) {
                if (\in_array($config['pools'][$adapter]['adapters'] ?? null, $redisTagAwareAdapters, true)) {
                    $isRedisTagAware = true;
                } elseif ($config['pools'][$adapter]['tags'] ?? false) {
                    $pool['adapters'][$provider] = $adapter = '.'.$adapter.'.inner';
                }
            }

            if (1 === \count($pool['adapters'])) {
                if (!isset($pool['provider']) && !\is_int($provider)) {
                    $pool['provider'] = $provider;
                }
                $definition = new ChildDefinition($adapter);
            } else {
                $definition = new Definition(ChainAdapter::class, [$pool['adapters'], 0]);
                $pool['reset'] = 'reset';
            }

            if ($isRedisTagAware && 'cache.app' === $name) {
                $container->setAlias('cache.app.taggable', $name);
                $definition->addTag('cache.taggable', ['pool' => $name]);
            } elseif ($isRedisTagAware) {
                $tagAwareId = $name;
                $container->setAlias('.'.$name.'.inner', $name);
                $definition->addTag('cache.taggable', ['pool' => $name]);
            } elseif ($pool['tags']) {
                if (true !== $pool['tags'] && ($config['pools'][$pool['tags']]['tags'] ?? false)) {
                    $pool['tags'] = '.'.$pool['tags'].'.inner';
                }
                $container->register($name, TagAwareAdapter::class)
                    ->addArgument(new Reference('.'.$name.'.inner'))
                    ->addArgument(true !== $pool['tags'] ? new Reference($pool['tags']) : null)
                    ->addMethodCall('setLogger', [new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)])
                    ->setPublic($pool['public'])
                    ->addTag('cache.taggable', ['pool' => $name])
                    ->addTag('monolog.logger', ['channel' => 'cache']);

                $pool['name'] = $tagAwareId = $name;
                $pool['public'] = false;
                $name = '.'.$name.'.inner';
            } elseif (!\in_array($name, ['cache.app', 'cache.system'], true)) {
                $tagAwareId = '.'.$name.'.taggable';
                $container->register($tagAwareId, TagAwareAdapter::class)
                    ->addArgument(new Reference($name))
                    ->addTag('cache.taggable', ['pool' => $name])
                ;
            }

            if (!\in_array($name, ['cache.app', 'cache.system'], true)) {
                $container->registerAliasForArgument($tagAwareId, TagAwareCacheInterface::class, $pool['name'] ?? $name);
                $container->registerAliasForArgument($name, CacheInterface::class, $pool['name'] ?? $name);
                $container->registerAliasForArgument($name, CacheItemPoolInterface::class, $pool['name'] ?? $name);

                if (interface_exists(NamespacedPoolInterface::class)) {
                    $container->registerAliasForArgument($name, NamespacedPoolInterface::class, $pool['name'] ?? $name);
                }
            }

            $definition->setPublic($pool['public']);
            unset($pool['adapters'], $pool['public'], $pool['tags']);

            $definition->addTag('cache.pool', $pool);
            $container->setDefinition($name, $definition);
        }

        if (class_exists(PropertyAccessor::class)) {
            $propertyAccessDefinition = $container->register('cache.property_access', AdapterInterface::class);

            if (!$container->getParameter('kernel.debug')) {
                $propertyAccessDefinition->setFactory([PropertyAccessor::class, 'createCache']);
                $propertyAccessDefinition->setArguments(['', 0, $version, new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]);
                $propertyAccessDefinition->addTag('cache.pool', ['clearer' => 'cache.system_clearer']);
                $propertyAccessDefinition->addTag('monolog.logger', ['channel' => 'cache']);
            } else {
                $propertyAccessDefinition->setClass(ArrayAdapter::class);
                $propertyAccessDefinition->setArguments([0, false]);
            }
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

        if (!class_exists(PingWebhookMessageHandler::class)) {
            $container->removeDefinition('http_client.messenger.ping_webhook_handler');
        }

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

            // This "transport" service is decorated in the following order:
            // 1. ThrottlingHttpClient (30) -> throttles requests
            // 2. UriTemplateHttpClient (25) -> expands URI templates
            // 3. ScopingHttpClient (20) -> resolves relative URLs and applies scope configuration
            // 4. CachingHttpClient (15) -> caches responses
            // 5. RetryableHttpClient (10) -> retries requests
            // 6. TraceableHttpClient (5) -> traces requests
            $container->register($name, HttpClientInterface::class)
                ->setFactory('current')
                ->setArguments([[new Reference('http_client.transport')]])
                ->addTag('http_client.client')
            ;

            $scopingDefinition = $container->register($name.'.scoping', ScopingHttpClient::class)
                ->setDecoratedService($name, null, 20)
                ->addTag('kernel.reset', ['method' => 'reset', 'on_invalid' => 'ignore']);

            if (null === $scope) {
                $baseUri = $scopeConfig['base_uri'];
                unset($scopeConfig['base_uri']);

                $scopingDefinition
                    ->setFactory([ScopingHttpClient::class, 'forBaseUri'])
                    ->setArguments([new Reference('.inner'), $baseUri, $scopeConfig]);
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
                ->setDecoratedService($name, null, 25)
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

        if ($responseFactoryId = $config['mock_response_factory'] ?? null) {
            $container->register('http_client.mock_client', MockHttpClient::class)
                ->setDecoratedService('http_client.transport', null, -10)
                ->setArguments([new Reference($responseFactoryId)]);
        }
    }

    private function registerCachingHttpClient(array $options, array $defaultOptions, string $name, ContainerBuilder $container): void
    {
        if (!class_exists(ChunkCacheItemNotFoundException::class)) {
            throw new LogicException('Caching cannot be enabled as version 7.4+ of the HttpClient component is required.');
        }

        $container
            ->register($name.'.caching', CachingHttpClient::class)
            ->setDecoratedService($name, null, 15)
            ->setArguments([
                new Reference('.inner'),
                new Reference($options['cache_pool']),
                $defaultOptions,
                $options['shared'],
                $options['max_ttl'],
            ]);
    }

    private function registerThrottlingHttpClient(string $rateLimiter, string $name, ContainerBuilder $container): void
    {
        if (!class_exists(ThrottlingHttpClient::class)) {
            throw new LogicException('Rate limiter support cannot be enabled as version 7.1+ of the HttpClient component is required.');
        }

        if (!$this->isInitializedConfigEnabled('rate_limiter')) {
            throw new LogicException('Rate limiter cannot be used within HttpClient as the RateLimiter component is not enabled.');
        }

        $container->register($name.'.throttling.limiter', LimiterInterface::class)
            ->setFactory([new Reference('limiter.'.$rateLimiter), 'create']);

        $container
            ->register($name.'.throttling', ThrottlingHttpClient::class)
            ->setDecoratedService($name, null, 30)
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

        $container
            ->register($name.'.retryable', RetryableHttpClient::class)
            ->setDecoratedService($name, null, 10)
            ->setArguments([new Reference('.inner'), $retryStrategy, $options['max_retries'], new Reference('logger')])
            ->addTag('monolog.logger', ['channel' => 'http_client']);
    }

    private function registerMailerConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader, bool $webhookEnabled): void
    {
        if (!class_exists(Mailer::class)) {
            throw new LogicException('Mailer support cannot be enabled as the component is not installed. Try running "composer require symfony/mailer".');
        }

        $loader->load('mailer.php');
        $loader->load('mailer_transports.php');
        if (!\count($config['transports']) && null === $config['dsn']) {
            $config['dsn'] = 'smtp://null';
        }
        $transports = $config['dsn'] ? ['main' => $config['dsn']] : $config['transports'];
        $container->getDefinition('mailer.transports')->setArgument(0, $transports);

        $mailer = $container->getDefinition('mailer.mailer');
        if (false === $messageBus = $config['message_bus']) {
            $mailer->replaceArgument(1, null);
        } else {
            $mailer->replaceArgument(1, $messageBus ? new Reference($messageBus) : new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }

        $classToServices = [
            MailerBridge\AhaSend\Transport\AhaSendTransportFactory::class => 'mailer.transport_factory.ahasend',
            MailerBridge\Azure\Transport\AzureTransportFactory::class => 'mailer.transport_factory.azure',
            MailerBridge\Brevo\Transport\BrevoTransportFactory::class => 'mailer.transport_factory.brevo',
            MailerBridge\Google\Transport\GmailTransportFactory::class => 'mailer.transport_factory.gmail',
            MailerBridge\Infobip\Transport\InfobipTransportFactory::class => 'mailer.transport_factory.infobip',
            MailerBridge\MailerSend\Transport\MailerSendTransportFactory::class => 'mailer.transport_factory.mailersend',
            MailerBridge\Mailgun\Transport\MailgunTransportFactory::class => 'mailer.transport_factory.mailgun',
            MailerBridge\Mailjet\Transport\MailjetTransportFactory::class => 'mailer.transport_factory.mailjet',
            MailerBridge\Mailomat\Transport\MailomatTransportFactory::class => 'mailer.transport_factory.mailomat',
            MailerBridge\MailPace\Transport\MailPaceTransportFactory::class => 'mailer.transport_factory.mailpace',
            MailerBridge\Mailchimp\Transport\MandrillTransportFactory::class => 'mailer.transport_factory.mailchimp',
            MailerBridge\MicrosoftGraph\Transport\MicrosoftGraphTransportFactory::class => 'mailer.transport_factory.microsoftgraph',
            MailerBridge\Postal\Transport\PostalTransportFactory::class => 'mailer.transport_factory.postal',
            MailerBridge\Postmark\Transport\PostmarkTransportFactory::class => 'mailer.transport_factory.postmark',
            MailerBridge\Mailtrap\Transport\MailtrapTransportFactory::class => 'mailer.transport_factory.mailtrap',
            MailerBridge\Resend\Transport\ResendTransportFactory::class => 'mailer.transport_factory.resend',
            MailerBridge\Scaleway\Transport\ScalewayTransportFactory::class => 'mailer.transport_factory.scaleway',
            MailerBridge\Sendgrid\Transport\SendgridTransportFactory::class => 'mailer.transport_factory.sendgrid',
            MailerBridge\Amazon\Transport\SesTransportFactory::class => 'mailer.transport_factory.amazon',
            MailerBridge\Sweego\Transport\SweegoTransportFactory::class => 'mailer.transport_factory.sweego',
        ];

        foreach ($classToServices as $class => $service) {
            $package = substr($service, \strlen('mailer.transport_factory.'));

            if (!ContainerBuilder::willBeAvailable(\sprintf('symfony/%s-mailer', 'gmail' === $package ? 'google' : $package), $class, ['symfony/framework-bundle', 'symfony/mailer'])) {
                $container->removeDefinition($service);
            }
        }

        if ($webhookEnabled) {
            $webhookRequestParsers = [
                MailerBridge\AhaSend\Webhook\AhaSendRequestParser::class => 'mailer.webhook.request_parser.ahasend',
                MailerBridge\Brevo\Webhook\BrevoRequestParser::class => 'mailer.webhook.request_parser.brevo',
                MailerBridge\MailerSend\Webhook\MailerSendRequestParser::class => 'mailer.webhook.request_parser.mailersend',
                MailerBridge\Mailchimp\Webhook\MailchimpRequestParser::class => 'mailer.webhook.request_parser.mailchimp',
                MailerBridge\Mailgun\Webhook\MailgunRequestParser::class => 'mailer.webhook.request_parser.mailgun',
                MailerBridge\Mailjet\Webhook\MailjetRequestParser::class => 'mailer.webhook.request_parser.mailjet',
                MailerBridge\Mailomat\Webhook\MailomatRequestParser::class => 'mailer.webhook.request_parser.mailomat',
                MailerBridge\Postmark\Webhook\PostmarkRequestParser::class => 'mailer.webhook.request_parser.postmark',
                MailerBridge\Mailtrap\Webhook\MailtrapRequestParser::class => 'mailer.webhook.request_parser.mailtrap',
                MailerBridge\Resend\Webhook\ResendRequestParser::class => 'mailer.webhook.request_parser.resend',
                MailerBridge\Sendgrid\Webhook\SendgridRequestParser::class => 'mailer.webhook.request_parser.sendgrid',
                MailerBridge\Sweego\Webhook\SweegoRequestParser::class => 'mailer.webhook.request_parser.sweego',
            ];

            foreach ($webhookRequestParsers as $class => $service) {
                $package = substr($service, \strlen('mailer.webhook.request_parser.'));

                if (!ContainerBuilder::willBeAvailable(\sprintf('symfony/%s-mailer', 'gmail' === $package ? 'google' : $package), $class, ['symfony/framework-bundle', 'symfony/mailer'])) {
                    $container->removeDefinition($service);
                }
            }
        }

        $envelopeListener = $container->getDefinition('mailer.envelope_listener');
        $envelopeListener->setArgument(0, $config['envelope']['sender'] ?? null);
        $envelopeListener->setArgument(1, $config['envelope']['recipients'] ?? null);
        $envelopeListener->setArgument(2, $config['envelope']['allowed_recipients'] ?? []);

        if ($config['headers']) {
            $headers = new Definition(Headers::class);
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

        if (!class_exists(MessengerTransportListener::class)) {
            $container->removeDefinition('mailer.messenger_transport_listener');
        }

        if ($config['dkim_signer']['enabled']) {
            if (!class_exists(DkimSignedMessageListener::class)) {
                throw new LogicException('DKIM signed messages support cannot be enabled as this version of the Mailer component does not support it.');
            }
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
            if (!class_exists(SmimeSignedMessageListener::class)) {
                throw new LogicException('SMIME signed messages support cannot be enabled as this version of the Mailer component does not support it.');
            }
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
            if (!class_exists(SmimeEncryptedMessageListener::class)) {
                throw new LogicException('S/MIME encrypted messages support cannot be enabled as this version of the Mailer component does not support it.');
            }
            $container->setAlias('mailer.smime_encrypter.repository', $config['smime_encrypter']['repository']);
            $container->setParameter('mailer.smime_encrypter.cipher', $config['smime_encrypter']['cipher']);
        } else {
            $container->removeDefinition('mailer.smime_encrypter.listener');
        }

        if ($webhookEnabled) {
            $loader->load('mailer_webhook.php');
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

        if ($this->isInitializedConfigEnabled('messenger')) {
            if ($config['notification_on_failed_messages']) {
                $container->getDefinition('notifier.failed_message_listener')->addTag('kernel.event_subscriber');
            }

            // as we have a bus, the channels don't need the transports
            $container->getDefinition('notifier.channel.chat')->setArgument(0, null);
            if ($container->hasDefinition('notifier.channel.email')) {
                $container->getDefinition('notifier.channel.email')->setArgument(0, null);
            }
            $container->getDefinition('notifier.channel.sms')->setArgument(0, null);
            $container->getDefinition('notifier.channel.push')->setArgument(0, null);
            $container->getDefinition('notifier.channel.desktop')->setArgument(0, null);
        }

        $container->getDefinition('notifier.channel_policy')->setArgument(0, $config['channel_policy']);

        $container->registerForAutoconfiguration(NotifierTransportFactoryInterface::class)
            ->addTag('chatter.transport_factory');

        $container->registerForAutoconfiguration(NotifierTransportFactoryInterface::class)
            ->addTag('texter.transport_factory');

        $classToServices = [
            NotifierBridge\AllMySms\AllMySmsTransportFactory::class => 'notifier.transport_factory.all-my-sms',
            NotifierBridge\AmazonSns\AmazonSnsTransportFactory::class => 'notifier.transport_factory.amazon-sns',
            NotifierBridge\Bandwidth\BandwidthTransportFactory::class => 'notifier.transport_factory.bandwidth',
            NotifierBridge\Bluesky\BlueskyTransportFactory::class => 'notifier.transport_factory.bluesky',
            NotifierBridge\Brevo\BrevoTransportFactory::class => 'notifier.transport_factory.brevo',
            NotifierBridge\Chatwork\ChatworkTransportFactory::class => 'notifier.transport_factory.chatwork',
            NotifierBridge\Clickatell\ClickatellTransportFactory::class => 'notifier.transport_factory.clickatell',
            NotifierBridge\ClickSend\ClickSendTransportFactory::class => 'notifier.transport_factory.click-send',
            NotifierBridge\ContactEveryone\ContactEveryoneTransportFactory::class => 'notifier.transport_factory.contact-everyone',
            NotifierBridge\Discord\DiscordTransportFactory::class => 'notifier.transport_factory.discord',
            NotifierBridge\Engagespot\EngagespotTransportFactory::class => 'notifier.transport_factory.engagespot',
            NotifierBridge\Esendex\EsendexTransportFactory::class => 'notifier.transport_factory.esendex',
            NotifierBridge\Expo\ExpoTransportFactory::class => 'notifier.transport_factory.expo',
            NotifierBridge\Firebase\FirebaseTransportFactory::class => 'notifier.transport_factory.firebase',
            NotifierBridge\FortySixElks\FortySixElksTransportFactory::class => 'notifier.transport_factory.forty-six-elks',
            NotifierBridge\FreeMobile\FreeMobileTransportFactory::class => 'notifier.transport_factory.free-mobile',
            NotifierBridge\GatewayApi\GatewayApiTransportFactory::class => 'notifier.transport_factory.gateway-api',
            NotifierBridge\GoIp\GoIpTransportFactory::class => 'notifier.transport_factory.go-ip',
            NotifierBridge\GoogleChat\GoogleChatTransportFactory::class => 'notifier.transport_factory.google-chat',
            NotifierBridge\Infobip\InfobipTransportFactory::class => 'notifier.transport_factory.infobip',
            NotifierBridge\Iqsms\IqsmsTransportFactory::class => 'notifier.transport_factory.iqsms',
            NotifierBridge\Isendpro\IsendproTransportFactory::class => 'notifier.transport_factory.isendpro',
            NotifierBridge\JoliNotif\JoliNotifTransportFactory::class => 'notifier.transport_factory.joli-notif',
            NotifierBridge\KazInfoTeh\KazInfoTehTransportFactory::class => 'notifier.transport_factory.kaz-info-teh',
            NotifierBridge\LightSms\LightSmsTransportFactory::class => 'notifier.transport_factory.light-sms',
            NotifierBridge\LineBot\LineBotTransportFactory::class => 'notifier.transport_factory.line-bot',
            NotifierBridge\LineNotify\LineNotifyTransportFactory::class => 'notifier.transport_factory.line-notify',
            NotifierBridge\LinkedIn\LinkedInTransportFactory::class => 'notifier.transport_factory.linked-in',
            NotifierBridge\Lox24\Lox24TransportFactory::class => 'notifier.transport_factory.lox24',
            NotifierBridge\Mailjet\MailjetTransportFactory::class => 'notifier.transport_factory.mailjet',
            NotifierBridge\Mastodon\MastodonTransportFactory::class => 'notifier.transport_factory.mastodon',
            NotifierBridge\Matrix\MatrixTransportFactory::class => 'notifier.transport_factory.matrix',
            NotifierBridge\Mattermost\MattermostTransportFactory::class => 'notifier.transport_factory.mattermost',
            NotifierBridge\Mercure\MercureTransportFactory::class => 'notifier.transport_factory.mercure',
            NotifierBridge\MessageBird\MessageBirdTransportFactory::class => 'notifier.transport_factory.message-bird',
            NotifierBridge\MessageMedia\MessageMediaTransportFactory::class => 'notifier.transport_factory.message-media',
            NotifierBridge\MicrosoftTeams\MicrosoftTeamsTransportFactory::class => 'notifier.transport_factory.microsoft-teams',
            NotifierBridge\Mobyt\MobytTransportFactory::class => 'notifier.transport_factory.mobyt',
            NotifierBridge\Novu\NovuTransportFactory::class => 'notifier.transport_factory.novu',
            NotifierBridge\Ntfy\NtfyTransportFactory::class => 'notifier.transport_factory.ntfy',
            NotifierBridge\Octopush\OctopushTransportFactory::class => 'notifier.transport_factory.octopush',
            NotifierBridge\OneSignal\OneSignalTransportFactory::class => 'notifier.transport_factory.one-signal',
            NotifierBridge\OrangeSms\OrangeSmsTransportFactory::class => 'notifier.transport_factory.orange-sms',
            NotifierBridge\OvhCloud\OvhCloudTransportFactory::class => 'notifier.transport_factory.ovh-cloud',
            NotifierBridge\PagerDuty\PagerDutyTransportFactory::class => 'notifier.transport_factory.pager-duty',
            NotifierBridge\Plivo\PlivoTransportFactory::class => 'notifier.transport_factory.plivo',
            NotifierBridge\Primotexto\PrimotextoTransportFactory::class => 'notifier.transport_factory.primotexto',
            NotifierBridge\Pushover\PushoverTransportFactory::class => 'notifier.transport_factory.pushover',
            NotifierBridge\Pushy\PushyTransportFactory::class => 'notifier.transport_factory.pushy',
            NotifierBridge\Redlink\RedlinkTransportFactory::class => 'notifier.transport_factory.redlink',
            NotifierBridge\RingCentral\RingCentralTransportFactory::class => 'notifier.transport_factory.ring-central',
            NotifierBridge\RocketChat\RocketChatTransportFactory::class => 'notifier.transport_factory.rocket-chat',
            NotifierBridge\Sendberry\SendberryTransportFactory::class => 'notifier.transport_factory.sendberry',
            NotifierBridge\Sipgate\SipgateTransportFactory::class => 'notifier.transport_factory.sipgate',
            NotifierBridge\SimpleTextin\SimpleTextinTransportFactory::class => 'notifier.transport_factory.simple-textin',
            NotifierBridge\Sevenio\SevenIoTransportFactory::class => 'notifier.transport_factory.sevenio',
            NotifierBridge\Sinch\SinchTransportFactory::class => 'notifier.transport_factory.sinch',
            NotifierBridge\Slack\SlackTransportFactory::class => 'notifier.transport_factory.slack',
            NotifierBridge\Sms77\Sms77TransportFactory::class => 'notifier.transport_factory.sms77',
            NotifierBridge\Smsapi\SmsapiTransportFactory::class => 'notifier.transport_factory.smsapi',
            NotifierBridge\SmsBiuras\SmsBiurasTransportFactory::class => 'notifier.transport_factory.sms-biuras',
            NotifierBridge\Smsbox\SmsboxTransportFactory::class => 'notifier.transport_factory.smsbox',
            NotifierBridge\Smsc\SmscTransportFactory::class => 'notifier.transport_factory.smsc',
            NotifierBridge\SmsFactor\SmsFactorTransportFactory::class => 'notifier.transport_factory.sms-factor',
            NotifierBridge\Smsmode\SmsmodeTransportFactory::class => 'notifier.transport_factory.smsmode',
            NotifierBridge\SmsSluzba\SmsSluzbaTransportFactory::class => 'notifier.transport_factory.sms-sluzba',
            NotifierBridge\Smsense\SmsenseTransportFactory::class => 'notifier.transport_factory.smsense',
            NotifierBridge\SpotHit\SpotHitTransportFactory::class => 'notifier.transport_factory.spot-hit',
            NotifierBridge\Sweego\SweegoTransportFactory::class => 'notifier.transport_factory.sweego',
            NotifierBridge\Telegram\TelegramTransportFactory::class => 'notifier.transport_factory.telegram',
            NotifierBridge\Telnyx\TelnyxTransportFactory::class => 'notifier.transport_factory.telnyx',
            NotifierBridge\Termii\TermiiTransportFactory::class => 'notifier.transport_factory.termii',
            NotifierBridge\TurboSms\TurboSmsTransportFactory::class => 'notifier.transport_factory.turbo-sms',
            NotifierBridge\Twilio\TwilioTransportFactory::class => 'notifier.transport_factory.twilio',
            NotifierBridge\Twitter\TwitterTransportFactory::class => 'notifier.transport_factory.twitter',
            NotifierBridge\Unifonic\UnifonicTransportFactory::class => 'notifier.transport_factory.unifonic',
            NotifierBridge\Vonage\VonageTransportFactory::class => 'notifier.transport_factory.vonage',
            NotifierBridge\Yunpian\YunpianTransportFactory::class => 'notifier.transport_factory.yunpian',
            NotifierBridge\Zendesk\ZendeskTransportFactory::class => 'notifier.transport_factory.zendesk',
            NotifierBridge\Zulip\ZulipTransportFactory::class => 'notifier.transport_factory.zulip',
        ];

        $parentPackages = ['symfony/framework-bundle', 'symfony/notifier'];

        foreach ($classToServices as $class => $service) {
            $package = substr($service, \strlen('notifier.transport_factory.'));

            if (!ContainerBuilder::willBeAvailable(\sprintf('symfony/%s-notifier', $package), $class, $parentPackages)) {
                $container->removeDefinition($service);
            }
        }

        if (ContainerBuilder::willBeAvailable('symfony/mercure-notifier', NotifierBridge\Mercure\MercureTransportFactory::class, $parentPackages) && ContainerBuilder::willBeAvailable('symfony/mercure-bundle', MercureBundle::class, $parentPackages) && \in_array(MercureBundle::class, $container->getParameter('kernel.bundles'), true)) {
            $container->getDefinition($classToServices[NotifierBridge\Mercure\MercureTransportFactory::class])
                ->replaceArgument(0, new Reference(HubRegistry::class))
                ->replaceArgument(1, new Reference('event_dispatcher', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->addArgument(new Reference('http_client', ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        } elseif (ContainerBuilder::willBeAvailable('symfony/mercure-notifier', NotifierBridge\Mercure\MercureTransportFactory::class, $parentPackages)) {
            $container->removeDefinition($classToServices[NotifierBridge\Mercure\MercureTransportFactory::class]);
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
            $container->getDefinition($classToServices[NotifierBridge\Bluesky\BlueskyTransportFactory::class])
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
                NotifierBridge\Lox24\Webhook\Lox24RequestParser::class => 'notifier.webhook.request_parser.lox24',
                NotifierBridge\Smsbox\Webhook\SmsboxRequestParser::class => 'notifier.webhook.request_parser.smsbox',
                NotifierBridge\Sweego\Webhook\SweegoRequestParser::class => 'notifier.webhook.request_parser.sweego',
                NotifierBridge\Twilio\Webhook\TwilioRequestParser::class => 'notifier.webhook.request_parser.twilio',
                NotifierBridge\Vonage\Webhook\VonageRequestParser::class => 'notifier.webhook.request_parser.vonage',
            ];

            foreach ($webhookRequestParsers as $class => $service) {
                $package = substr($service, \strlen('notifier.webhook.request_parser.'));

                if (!ContainerBuilder::willBeAvailable(\sprintf('symfony/%s-notifier', $package), $class, ['symfony/framework-bundle', 'symfony/notifier'])) {
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
    }

    private function registerRemoteEventConfiguration(PhpFileLoader $loader): void
    {
        if (!class_exists(RemoteEvent::class)) {
            throw new LogicException('RemoteEvent support cannot be enabled as the component is not installed. Try running "composer require symfony/remote-event".');
        }

        $loader->load('remote_event.php');
    }

    private function registerRateLimiterConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('rate_limiter.php');

        $limiters = [];
        $compoundLimiters = [];

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
                $limiterConfig['lock_factory'] = $this->isInitializedConfigEnabled('lock') ? 'lock.factory' : null;
            }

            if (null !== $limiterConfig['lock_factory']) {
                if (!interface_exists(LockInterface::class)) {
                    throw new LogicException(\sprintf('Rate limiter "%s" requires the Lock component to be installed. Try running "composer require symfony/lock".', $name));
                }

                if (!$this->isInitializedConfigEnabled('lock')) {
                    throw new LogicException(\sprintf('Rate limiter "%s" requires the Lock component to be configured.', $name));
                }

                $limiter->replaceArgument(2, new Reference($limiterConfig['lock_factory']));
            }
            unset($limiterConfig['lock_factory']);

            if (null === $storageId = $limiterConfig['storage_service'] ?? null) {
                $container->register($storageId = 'limiter.storage.'.$name, CacheStorage::class)->addArgument(new Reference($limiterConfig['cache_pool']));
            }

            $limiter->replaceArgument(1, new Reference($storageId));
            unset($limiterConfig['storage_service'], $limiterConfig['cache_pool']);

            $limiterConfig['id'] = $name;
            $limiter->replaceArgument(0, $limiterConfig);

            $factoryAlias = $container->registerAliasForArgument($limiterId, RateLimiterFactory::class, $name.'.limiter');

            if (interface_exists(RateLimiterFactoryInterface::class)) {
                $container->registerAliasForArgument($limiterId, RateLimiterFactoryInterface::class, $name.'.limiter', $name);

                $factoryAlias->setDeprecated('symfony/framework-bundle', '7.3', 'The "%alias_id%" autowiring alias is deprecated and will be removed in 8.0, use "RateLimiterFactoryInterface" instead.');
                $container->getAlias(\sprintf('.%s $%s.limiter', RateLimiterFactory::class, $name))
                    ->setDeprecated('symfony/framework-bundle', '7.3', 'The "%alias_id%" autowiring alias is deprecated and will be removed in 8.0, use "RateLimiterFactoryInterface" instead.');
            }
        }

        if ($compoundLimiters && !class_exists(CompoundRateLimiterFactory::class)) {
            throw new LogicException('Configuring compound rate limiters is only available in symfony/rate-limiter 7.3+.');
        }

        foreach ($compoundLimiters as $name => $limiterConfig) {
            if (!$limiterConfig['limiters']) {
                throw new LogicException(\sprintf('Compound rate limiter "%s" requires at least one sub-limiter.', $name));
            }

            if (array_diff($limiterConfig['limiters'], $limiters)) {
                throw new LogicException(\sprintf('Compound rate limiter "%s" requires at least one sub-limiter to be configured.', $name));
            }

            $container->register($limiterId = 'limiter.'.$name, CompoundRateLimiterFactory::class)
                ->addTag('rate_limiter', ['name' => $name])
                ->addArgument(new IteratorArgument(array_map(
                    static fn (string $name) => new Reference('limiter.'.$name),
                    $limiterConfig['limiters']
                )))
            ;

            $container->registerAliasForArgument($limiterId, RateLimiterFactoryInterface::class, $name.'.limiter', $name);
        }
    }

    private function registerUidConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('uid.php');

        $container->getDefinition('uuid.factory')
            ->setArguments([
                $config['default_uuid_version'],
                $config['time_based_uuid_version'],
                $config['name_based_uuid_version'],
                UuidV4::class,
                $config['time_based_uuid_node'] ?? null,
                $config['name_based_uuid_namespace'] ?? null,
            ])
        ;

        if (isset($config['name_based_uuid_namespace'])) {
            $container->getDefinition('name_based_uuid.factory')
                ->setArguments([$config['name_based_uuid_namespace']]);
        }
    }

    private function registerHtmlSanitizerConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('html_sanitizer.php');

        foreach ($config['sanitizers'] as $sanitizerName => $sanitizerConfig) {
            $configId = 'html_sanitizer.config.'.$sanitizerName;
            $def = $container->register($configId, HtmlSanitizerConfig::class);

            // Base
            if ($sanitizerConfig['allow_safe_elements']) {
                $def->addMethodCall('allowSafeElements', [], true);
            }

            if ($sanitizerConfig['allow_static_elements']) {
                $def->addMethodCall('allowStaticElements', [], true);
            }

            // Configures elements
            foreach ($sanitizerConfig['allow_elements'] as $element => $attributes) {
                $def->addMethodCall('allowElement', [$element, $attributes], true);
            }

            foreach ($sanitizerConfig['block_elements'] as $element) {
                $def->addMethodCall('blockElement', [$element], true);
            }

            foreach ($sanitizerConfig['drop_elements'] as $element) {
                $def->addMethodCall('dropElement', [$element], true);
            }

            // Configures attributes
            foreach ($sanitizerConfig['allow_attributes'] as $attribute => $elements) {
                $def->addMethodCall('allowAttribute', [$attribute, $elements], true);
            }

            foreach ($sanitizerConfig['drop_attributes'] as $attribute => $elements) {
                $def->addMethodCall('dropAttribute', [$attribute, $elements], true);
            }

            // Force attributes
            foreach ($sanitizerConfig['force_attributes'] as $element => $attributes) {
                foreach ($attributes as $attrName => $attrValue) {
                    $def->addMethodCall('forceAttribute', [$element, $attrName, $attrValue], true);
                }
            }

            // Settings
            $def->addMethodCall('forceHttpsUrls', [$sanitizerConfig['force_https_urls']], true);
            if ($sanitizerConfig['allowed_link_schemes']) {
                $def->addMethodCall('allowLinkSchemes', [$sanitizerConfig['allowed_link_schemes']], true);
            }
            $def->addMethodCall('allowLinkHosts', [$sanitizerConfig['allowed_link_hosts']], true);
            $def->addMethodCall('allowRelativeLinks', [$sanitizerConfig['allow_relative_links']], true);
            if ($sanitizerConfig['allowed_media_schemes']) {
                $def->addMethodCall('allowMediaSchemes', [$sanitizerConfig['allowed_media_schemes']], true);
            }
            $def->addMethodCall('allowMediaHosts', [$sanitizerConfig['allowed_media_hosts']], true);
            $def->addMethodCall('allowRelativeMedias', [$sanitizerConfig['allow_relative_medias']], true);

            // Custom attribute sanitizers
            foreach ($sanitizerConfig['with_attribute_sanitizers'] as $serviceName) {
                $def->addMethodCall('withAttributeSanitizer', [new Reference($serviceName)], true);
            }

            foreach ($sanitizerConfig['without_attribute_sanitizers'] as $serviceName) {
                $def->addMethodCall('withoutAttributeSanitizer', [new Reference($serviceName)], true);
            }

            if ($sanitizerConfig['max_input_length']) {
                $def->addMethodCall('withMaxInputLength', [$sanitizerConfig['max_input_length']], true);
            }

            // Create the sanitizer and link its config
            $sanitizerId = 'html_sanitizer.sanitizer.'.$sanitizerName;
            $container->register($sanitizerId, HtmlSanitizer::class)
                ->addTag('html_sanitizer', ['sanitizer' => $sanitizerName])
                ->addArgument(new Reference($configId));

            if ('default' !== $sanitizerName) {
                $container->registerAliasForArgument($sanitizerId, HtmlSanitizerInterface::class, $sanitizerName);
            }
        }
    }

    /**
     * @deprecated since Symfony 7.4, to be removed in Symfony 8.0 together with XML support.
     */
    public function getXsdValidationBasePath(): string|false
    {
        return \dirname(__DIR__).'/Resources/config/schema';
    }

    /**
     * @deprecated since Symfony 7.4, to be removed in Symfony 8.0 together with XML support.
     */
    public function getNamespace(): string
    {
        return 'http://symfony.com/schema/dic/symfony';
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
