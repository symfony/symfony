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

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\Reader;
use Http\Client\HttpClient;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerAwareInterface;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Bridge\Twig\Extension\CsrfExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Routing\AnnotatedRouteControllerLoader;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Bundle\FullStack;
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\ResourceCheckerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\EnvVarLoaderInterface;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\StoreFactory;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransportFactory;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseTransportFactory;
use Symfony\Component\Notifier\Bridge\FreeMobile\FreeMobileTransportFactory;
use Symfony\Component\Notifier\Bridge\Mattermost\MattermostTransportFactory;
use Symfony\Component\Notifier\Bridge\Nexmo\NexmoTransportFactory;
use Symfony\Component\Notifier\Bridge\OvhCloud\OvhCloudTransportFactory;
use Symfony\Component\Notifier\Bridge\RocketChat\RocketChatTransportFactory;
use Symfony\Component\Notifier\Bridge\Sinch\SinchTransportFactory;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransportFactory;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransportFactory;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransportFactory;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Recipient\AdminRecipient;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfoExtractorInterface;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\Loader\AnnotationFileLoader;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\String\LazyString;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Translation\Command\XliffLintCommand as BaseXliffLintCommand;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Mapping\Loader\PropertyInfoLoader;
use Symfony\Component\Validator\ObjectInitializerInterface;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\Workflow;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Yaml\Command\LintCommand as BaseYamlLintCommand;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * FrameworkExtension.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jeremy Mikola <jmikola@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class FrameworkExtension extends Extension
{
    private $formConfigEnabled = false;
    private $translationConfigEnabled = false;
    private $sessionConfigEnabled = false;
    private $annotationsConfigEnabled = false;
    private $validatorConfigEnabled = false;
    private $messengerConfigEnabled = false;
    private $mailerConfigEnabled = false;
    private $httpClientConfigEnabled = false;

    /**
     * Responds to the app.config configuration parameter.
     *
     * @throws LogicException
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));

        $phpLoader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));

        $phpLoader->load('web.php');
        $loader->load('services.xml');
        $phpLoader->load('fragment_renderer.php');
        $phpLoader->load('error_renderer.php');

        if (interface_exists(PsrEventDispatcherInterface::class)) {
            $container->setAlias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        }

        $container->registerAliasForArgument('parameter_bag', PsrContainerInterface::class);

        if (class_exists(Application::class)) {
            $loader->load('console.xml');

            if (!class_exists(BaseXliffLintCommand::class)) {
                $container->removeDefinition('console.command.xliff_lint');
            }
            if (!class_exists(BaseYamlLintCommand::class)) {
                $container->removeDefinition('console.command.yaml_lint');
            }
        }

        // Load Cache configuration first as it is used by other components
        $loader->load('cache.xml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $this->annotationsConfigEnabled = $this->isConfigEnabled($container, $config['annotations']);
        $this->translationConfigEnabled = $this->isConfigEnabled($container, $config['translator']);

        // A translator must always be registered (as support is included by
        // default in the Form and Validator component). If disabled, an identity
        // translator will be used and everything will still work as expected.
        if ($this->isConfigEnabled($container, $config['translator']) || $this->isConfigEnabled($container, $config['form']) || $this->isConfigEnabled($container, $config['validation'])) {
            if (!class_exists('Symfony\Component\Translation\Translator') && $this->isConfigEnabled($container, $config['translator'])) {
                throw new LogicException('Translation support cannot be enabled as the Translation component is not installed. Try running "composer require symfony/translation".');
            }

            if (class_exists(Translator::class)) {
                $phpLoader->load('identity_translator.php');
            }
        }

        // If the slugger is used but the String component is not available, we should throw an error
        if (!interface_exists(SluggerInterface::class)) {
            $container->register('slugger', 'stdClass')
                ->addError('You cannot use the "slugger" service since the String component is not installed. Try running "composer require symfony/string".');
        } else {
            if (!interface_exists(LocaleAwareInterface::class)) {
                $container->register('slugger', 'stdClass')
                    ->addError('You cannot use the "slugger" service since the Translation contracts are not installed. Try running "composer require symfony/translation".');
            }

            if (!\extension_loaded('intl') && !\defined('PHPUNIT_COMPOSER_INSTALL')) {
                trigger_deprecation('', '', 'Please install the "intl" PHP extension for best performance.');
            }
        }

        if (isset($config['secret'])) {
            $container->setParameter('kernel.secret', $config['secret']);
        }

        $container->setParameter('kernel.http_method_override', $config['http_method_override']);
        $container->setParameter('kernel.trusted_hosts', $config['trusted_hosts']);
        $container->setParameter('kernel.default_locale', $config['default_locale']);
        $container->setParameter('kernel.error_controller', $config['error_controller']);

        if (!$container->hasParameter('debug.file_link_format')) {
            $links = [
                'textmate' => 'txmt://open?url=file://%%f&line=%%l',
                'macvim' => 'mvim://open?url=file://%%f&line=%%l',
                'emacs' => 'emacs://open?url=file://%%f&line=%%l',
                'sublime' => 'subl://open?url=file://%%f&line=%%l',
                'phpstorm' => 'phpstorm://open?file=%%f&line=%%l',
                'atom' => 'atom://core/open/file?filename=%%f&line=%%l',
                'vscode' => 'vscode://file/%%f:%%l',
            ];
            $ide = $config['ide'];
            // mark any env vars found in the ide setting as used
            $container->resolveEnvPlaceholders($ide);

            $container->setParameter('debug.file_link_format', str_replace('%', '%%', ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format')) ?: (isset($links[$ide]) ? $links[$ide] : $ide));
        }

        if (!empty($config['test'])) {
            $phpLoader->load('test.php');

            if (!class_exists(AbstractBrowser::class)) {
                $container->removeDefinition('test.client');
            }
        }

        // register cache before session so both can share the connection services
        $this->registerCacheConfiguration($config['cache'], $container);

        if ($this->isConfigEnabled($container, $config['session'])) {
            if (!\extension_loaded('session')) {
                throw new LogicException('Session support cannot be enabled as the session extension is not installed. See https://php.net/session.installation for instructions.');
            }

            $this->sessionConfigEnabled = true;
            $this->registerSessionConfiguration($config['session'], $container, $loader);
            if (!empty($config['test'])) {
                $container->getDefinition('test.session.listener')->setArgument(1, '%session.storage.options%');
            }
        }

        if ($this->isConfigEnabled($container, $config['request'])) {
            $this->registerRequestConfiguration($config['request'], $container, $loader);
        }

        if (null === $config['csrf_protection']['enabled']) {
            $config['csrf_protection']['enabled'] = $this->sessionConfigEnabled && !class_exists(FullStack::class) && interface_exists(CsrfTokenManagerInterface::class);
        }
        $this->registerSecurityCsrfConfiguration($config['csrf_protection'], $container, $phpLoader);

        if ($this->isConfigEnabled($container, $config['form'])) {
            if (!class_exists('Symfony\Component\Form\Form')) {
                throw new LogicException('Form support cannot be enabled as the Form component is not installed. Try running "composer require symfony/form".');
            }

            $this->formConfigEnabled = true;
            $this->registerFormConfiguration($config, $container, $loader);

            if (class_exists('Symfony\Component\Validator\Validation')) {
                $config['validation']['enabled'] = true;
            } else {
                $container->setParameter('validator.translation_domain', 'validators');

                $container->removeDefinition('form.type_extension.form.validator');
                $container->removeDefinition('form.type_guesser.validator');
            }
        } else {
            $container->removeDefinition('console.command.form_debug');
        }

        if ($this->isConfigEnabled($container, $config['assets'])) {
            if (!class_exists('Symfony\Component\Asset\Package')) {
                throw new LogicException('Asset support cannot be enabled as the Asset component is not installed. Try running "composer require symfony/asset".');
            }

            $this->registerAssetsConfiguration($config['assets'], $container, $loader);
        }

        if ($this->messengerConfigEnabled = $this->isConfigEnabled($container, $config['messenger'])) {
            $this->registerMessengerConfiguration($config['messenger'], $container, $loader, $config['validation']);
        } else {
            $container->removeDefinition('console.command.messenger_consume_messages');
            $container->removeDefinition('console.command.messenger_debug');
            $container->removeDefinition('console.command.messenger_stop_workers');
            $container->removeDefinition('console.command.messenger_setup_transports');
            $container->removeDefinition('console.command.messenger_failed_messages_retry');
            $container->removeDefinition('console.command.messenger_failed_messages_show');
            $container->removeDefinition('console.command.messenger_failed_messages_remove');
            $container->removeDefinition('cache.messenger.restart_workers_signal');

            if ($container->hasDefinition('messenger.transport.amqp.factory') && !class_exists(AmqpTransportFactory::class)) {
                if (class_exists(\Symfony\Component\Messenger\Transport\AmqpExt\AmqpTransportFactory::class)) {
                    $container->getDefinition('messenger.transport.amqp.factory')
                        ->setClass(\Symfony\Component\Messenger\Transport\AmqpExt\AmqpTransportFactory::class)
                        ->addTag('messenger.transport_factory');
                } else {
                    $container->removeDefinition('messenger.transport.amqp.factory');
                }
            }

            if ($container->hasDefinition('messenger.transport.redis.factory') && !class_exists(RedisTransportFactory::class)) {
                if (class_exists(\Symfony\Component\Messenger\Transport\RedisExt\RedisTransportFactory::class)) {
                    $container->getDefinition('messenger.transport.redis.factory')
                        ->setClass(\Symfony\Component\Messenger\Transport\RedisExt\RedisTransportFactory::class)
                        ->addTag('messenger.transport_factory');
                } else {
                    $container->removeDefinition('messenger.transport.redis.factory');
                }
            }
        }

        if ($this->httpClientConfigEnabled = $this->isConfigEnabled($container, $config['http_client'])) {
            $this->registerHttpClientConfiguration($config['http_client'], $container, $loader, $config['profiler']);
        }

        if ($this->mailerConfigEnabled = $this->isConfigEnabled($container, $config['mailer'])) {
            $this->registerMailerConfiguration($config['mailer'], $container, $loader);
        }

        if ($this->isConfigEnabled($container, $config['notifier'])) {
            $this->registerNotifierConfiguration($config['notifier'], $container, $phpLoader);
        }

        $propertyInfoEnabled = $this->isConfigEnabled($container, $config['property_info']);
        $this->registerValidationConfiguration($config['validation'], $container, $loader, $propertyInfoEnabled);
        $this->registerEsiConfiguration($config['esi'], $container, $loader);
        $this->registerSsiConfiguration($config['ssi'], $container, $phpLoader);
        $this->registerFragmentsConfiguration($config['fragments'], $container, $phpLoader);
        $this->registerTranslatorConfiguration($config['translator'], $container, $loader, $config['default_locale']);
        $this->registerProfilerConfiguration($config['profiler'], $container, $loader, $phpLoader);
        $this->registerWorkflowConfiguration($config['workflows'], $container, $loader);
        $this->registerDebugConfiguration($config['php_errors'], $container, $phpLoader);
        $this->registerRouterConfiguration($config['router'], $container, $loader, $config['translator']['enabled_locales'] ?? []);
        $this->registerAnnotationsConfiguration($config['annotations'], $container, $loader);
        $this->registerPropertyAccessConfiguration($config['property_access'], $container, $phpLoader);
        $this->registerSecretsConfiguration($config['secrets'], $container, $phpLoader);

        if ($this->isConfigEnabled($container, $config['serializer'])) {
            if (!class_exists('Symfony\Component\Serializer\Serializer')) {
                throw new LogicException('Serializer support cannot be enabled as the Serializer component is not installed. Try running "composer require symfony/serializer-pack".');
            }

            $this->registerSerializerConfiguration($config['serializer'], $container, $loader);
        }

        if ($propertyInfoEnabled) {
            $this->registerPropertyInfoConfiguration($container, $phpLoader);
        }

        if ($this->isConfigEnabled($container, $config['lock'])) {
            $this->registerLockConfiguration($config['lock'], $container, $phpLoader);
        }

        if ($this->isConfigEnabled($container, $config['web_link'])) {
            if (!class_exists(HttpHeaderSerializer::class)) {
                throw new LogicException('WebLink support cannot be enabled as the WebLink component is not installed. Try running "composer require symfony/weblink".');
            }

            $phpLoader->load('web_link.php');
        }

        $this->addAnnotatedClassesToCompile([
            '**\\Controller\\',
            '**\\Entity\\',

            // Added explicitly so that we don't rely on the class map being dumped to make it work
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController',
        ]);

        if (class_exists(MimeTypes::class)) {
            $phpLoader->load('mime_type.php');
        }

        $container->registerForAutoconfiguration(Command::class)
            ->addTag('console.command');
        $container->registerForAutoconfiguration(ResourceCheckerInterface::class)
            ->addTag('config_cache.resource_checker');
        $container->registerForAutoconfiguration(EnvVarLoaderInterface::class)
            ->addTag('container.env_var_loader');
        $container->registerForAutoconfiguration(EnvVarProcessorInterface::class)
            ->addTag('container.env_var_processor');
        $container->registerForAutoconfiguration(ServiceLocator::class)
            ->addTag('container.service_locator');
        $container->registerForAutoconfiguration(ServiceSubscriberInterface::class)
            ->addTag('container.service_subscriber');
        $container->registerForAutoconfiguration(ArgumentValueResolverInterface::class)
            ->addTag('controller.argument_value_resolver');
        $container->registerForAutoconfiguration(AbstractController::class)
            ->addTag('controller.service_arguments');
        $container->registerForAutoconfiguration(DataCollectorInterface::class)
            ->addTag('data_collector');
        $container->registerForAutoconfiguration(FormTypeInterface::class)
            ->addTag('form.type');
        $container->registerForAutoconfiguration(FormTypeGuesserInterface::class)
            ->addTag('form.type_guesser');
        $container->registerForAutoconfiguration(FormTypeExtensionInterface::class)
            ->addTag('form.type_extension');
        $container->registerForAutoconfiguration(CacheClearerInterface::class)
            ->addTag('kernel.cache_clearer');
        $container->registerForAutoconfiguration(CacheWarmerInterface::class)
            ->addTag('kernel.cache_warmer');
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
        $container->registerForAutoconfiguration(ObjectInitializerInterface::class)
            ->addTag('validator.initializer');
        $container->registerForAutoconfiguration(MessageHandlerInterface::class)
            ->addTag('messenger.message_handler');
        $container->registerForAutoconfiguration(TransportFactoryInterface::class)
            ->addTag('messenger.transport_factory');
        $container->registerForAutoconfiguration(MimeTypeGuesserInterface::class)
            ->addTag('mime.mime_type_guesser');
        $container->registerForAutoconfiguration(LoggerAwareInterface::class)
            ->addMethodCall('setLogger', [new Reference('logger')]);

        if (!$container->getParameter('kernel.debug')) {
            // remove tagged iterator argument for resource checkers
            $container->getDefinition('config_cache_factory')->setArguments([]);
        }

        if (!$config['disallow_search_engine_index'] ?? false) {
            $container->removeDefinition('disallow_search_engine_index_response_listener');
        }

        $container->registerForAutoconfiguration(RouteLoaderInterface::class)
            ->addTag('routing.route_loader');
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration($container->getParameter('kernel.debug'));
    }

    private function registerFormConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
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

        if (!class_exists(Translator::class)) {
            $container->removeDefinition('form.type_extension.upload.validator');
        }
        if (!method_exists(CachingFactoryDecorator::class, 'reset')) {
            $container->getDefinition('form.choice_list_factory.cached')
                ->clearTag('kernel.reset')
            ;
        }
    }

    private function registerEsiConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            $container->removeDefinition('fragment.renderer.esi');

            return;
        }

        $loader->load('esi.xml');
    }

    private function registerSsiConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            $container->removeDefinition('fragment.renderer.ssi');

            return;
        }

        $loader->load('ssi.php');
    }

    private function registerFragmentsConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            $container->removeDefinition('fragment.renderer.hinclude');

            return;
        }

        $container->setParameter('fragment.renderer.hinclude.global_template', $config['hinclude_default_template']);

        $loader->load('fragment_listener.php');
        $container->setParameter('fragment.path', $config['path']);
    }

    private function registerProfilerConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader, PhpFileLoader $phpLoader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            // this is needed for the WebProfiler to work even if the profiler is disabled
            $container->setParameter('data_collector.templates', []);

            return;
        }

        $phpLoader->load('profiling.php');
        $phpLoader->load('collectors.php');
        $loader->load('cache_debug.xml');

        if ($this->formConfigEnabled) {
            $loader->load('form_debug.xml');
        }

        if ($this->validatorConfigEnabled) {
            $loader->load('validator_debug.xml');
        }

        if ($this->translationConfigEnabled) {
            $loader->load('translation_debug.xml');

            $container->getDefinition('translator.data_collector')->setDecoratedService('translator');
        }

        if ($this->messengerConfigEnabled) {
            $loader->load('messenger_debug.xml');
        }

        if ($this->mailerConfigEnabled) {
            $loader->load('mailer_debug.xml');
        }

        if ($this->httpClientConfigEnabled) {
            $loader->load('http_client_debug.xml');
        }

        $container->setParameter('profiler_listener.only_exceptions', $config['only_exceptions']);
        $container->setParameter('profiler_listener.only_master_requests', $config['only_master_requests']);

        // Choose storage class based on the DSN
        list($class) = explode(':', $config['dsn'], 2);
        if ('file' !== $class) {
            throw new \LogicException(sprintf('Driver "%s" is not supported for the profiler.', $class));
        }

        $container->setParameter('profiler.storage.dsn', $config['dsn']);

        $container->getDefinition('profiler')
            ->addArgument($config['collect'])
            ->addTag('kernel.reset', ['method' => 'reset']);
    }

    private function registerWorkflowConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!$config['enabled']) {
            $container->removeDefinition('console.command.workflow_dump');

            return;
        }

        if (!class_exists(Workflow\Workflow::class)) {
            throw new LogicException('Workflow support cannot be enabled as the Workflow component is not installed. Try running "composer require symfony/workflow".');
        }

        $loader->load('workflow.xml');

        $registryDefinition = $container->getDefinition('workflow.registry');

        foreach ($config['workflows'] as $name => $workflow) {
            $type = $workflow['type'];
            $workflowId = sprintf('%s.%s', $type, $name);

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
                if ('workflow' === $type) {
                    $transitionDefinition = new Definition(Workflow\Transition::class, [$transition['name'], $transition['from'], $transition['to']]);
                    $transitionDefinition->setPublic(false);
                    $transitionId = sprintf('.%s.transition.%s', $workflowId, $transitionCounter++);
                    $container->setDefinition($transitionId, $transitionDefinition);
                    $transitions[] = new Reference($transitionId);
                    if (isset($transition['guard'])) {
                        $configuration = new Definition(Workflow\EventListener\GuardExpression::class);
                        $configuration->addArgument(new Reference($transitionId));
                        $configuration->addArgument($transition['guard']);
                        $configuration->setPublic(false);
                        $eventName = sprintf('workflow.%s.guard.%s', $name, $transition['name']);
                        $guardsConfiguration[$eventName][] = $configuration;
                    }
                    if ($transition['metadata']) {
                        $transitionsMetadataDefinition->addMethodCall('attach', [
                            new Reference($transitionId),
                            $transition['metadata'],
                        ]);
                    }
                } elseif ('state_machine' === $type) {
                    foreach ($transition['from'] as $from) {
                        foreach ($transition['to'] as $to) {
                            $transitionDefinition = new Definition(Workflow\Transition::class, [$transition['name'], $from, $to]);
                            $transitionDefinition->setPublic(false);
                            $transitionId = sprintf('.%s.transition.%s', $workflowId, $transitionCounter++);
                            $container->setDefinition($transitionId, $transitionDefinition);
                            $transitions[] = new Reference($transitionId);
                            if (isset($transition['guard'])) {
                                $configuration = new Definition(Workflow\EventListener\GuardExpression::class);
                                $configuration->addArgument(new Reference($transitionId));
                                $configuration->addArgument($transition['guard']);
                                $configuration->setPublic(false);
                                $eventName = sprintf('workflow.%s.guard.%s', $name, $transition['name']);
                                $guardsConfiguration[$eventName][] = $configuration;
                            }
                            if ($transition['metadata']) {
                                $transitionsMetadataDefinition->addMethodCall('attach', [
                                    new Reference($transitionId),
                                    $transition['metadata'],
                                ]);
                            }
                        }
                    }
                }
            }
            $metadataStoreDefinition->replaceArgument(2, $transitionsMetadataDefinition);

            // Create places
            $places = array_column($workflow['places'], 'name');
            $initialMarking = $workflow['initial_marking'] ?? [];

            // Create a Definition
            $definitionDefinition = new Definition(Workflow\Definition::class);
            $definitionDefinition->setPublic(false);
            $definitionDefinition->addArgument($places);
            $definitionDefinition->addArgument($transitions);
            $definitionDefinition->addArgument($initialMarking);
            $definitionDefinition->addArgument($metadataStoreDefinition);
            $definitionDefinition->addTag('workflow.definition', [
                'name' => $name,
                'type' => $type,
            ]);

            // Create MarkingStore
            if (isset($workflow['marking_store']['type'])) {
                $markingStoreDefinition = new ChildDefinition('workflow.marking_store.method');
                $markingStoreDefinition->setArguments([
                    'state_machine' === $type, //single state
                    $workflow['marking_store']['property'],
                ]);
            } elseif (isset($workflow['marking_store']['service'])) {
                $markingStoreDefinition = new Reference($workflow['marking_store']['service']);
            }

            // Create Workflow
            $workflowDefinition = new ChildDefinition(sprintf('%s.abstract', $type));
            $workflowDefinition->replaceArgument(0, new Reference(sprintf('%s.definition', $workflowId)));
            if (isset($markingStoreDefinition)) {
                $workflowDefinition->replaceArgument(1, $markingStoreDefinition);
            }
            $workflowDefinition->replaceArgument(3, $name);

            // Store to container
            $container->setDefinition($workflowId, $workflowDefinition);
            $container->setDefinition(sprintf('%s.definition', $workflowId), $definitionDefinition);
            $container->registerAliasForArgument($workflowId, WorkflowInterface::class, $name.'.'.$type);

            // Validate Workflow
            if ('state_machine' === $workflow['type']) {
                $validator = new Workflow\Validator\StateMachineValidator();
            } else {
                $validator = new Workflow\Validator\WorkflowValidator();
            }

            $trs = array_map(function (Reference $ref) use ($container): Workflow\Transition {
                return $container->get((string) $ref);
            }, $transitions);
            $realDefinition = new Workflow\Definition($places, $trs, $initialMarking);
            $validator->validate($realDefinition, $name);

            // Add workflow to Registry
            if ($workflow['supports']) {
                foreach ($workflow['supports'] as $supportedClassName) {
                    $strategyDefinition = new Definition(Workflow\SupportStrategy\InstanceOfSupportStrategy::class, [$supportedClassName]);
                    $strategyDefinition->setPublic(false);
                    $registryDefinition->addMethodCall('addWorkflow', [new Reference($workflowId), $strategyDefinition]);
                }
            } elseif (isset($workflow['support_strategy'])) {
                $registryDefinition->addMethodCall('addWorkflow', [new Reference($workflowId), new Reference($workflow['support_strategy'])]);
            }

            // Enable the AuditTrail
            if ($workflow['audit_trail']['enabled']) {
                $listener = new Definition(Workflow\EventListener\AuditTrailListener::class);
                $listener->setPrivate(true);
                $listener->addTag('monolog.logger', ['channel' => 'workflow']);
                $listener->addTag('kernel.event_listener', ['event' => sprintf('workflow.%s.leave', $name), 'method' => 'onLeave']);
                $listener->addTag('kernel.event_listener', ['event' => sprintf('workflow.%s.transition', $name), 'method' => 'onTransition']);
                $listener->addTag('kernel.event_listener', ['event' => sprintf('workflow.%s.enter', $name), 'method' => 'onEnter']);
                $listener->addArgument(new Reference('logger'));
                $container->setDefinition(sprintf('.%s.listener.audit_trail', $workflowId), $listener);
            }

            // Add Guard Listener
            if ($guardsConfiguration) {
                if (!class_exists(ExpressionLanguage::class)) {
                    throw new LogicException('Cannot guard workflows as the ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
                }

                if (!class_exists(Security::class)) {
                    throw new LogicException('Cannot guard workflows as the Security component is not installed. Try running "composer require symfony/security-core".');
                }

                $guard = new Definition(Workflow\EventListener\GuardListener::class);
                $guard->setPrivate(true);

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

                $container->setDefinition(sprintf('.%s.listener.guard', $workflowId), $guard);
                $container->setParameter('workflow.has_guard_listeners', true);
            }
        }
    }

    private function registerDebugConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader)
    {
        $loader->load('debug_prod.php');

        if (class_exists(Stopwatch::class)) {
            $container->register('debug.stopwatch', Stopwatch::class)
                ->addArgument(true)
                ->setPrivate(true)
                ->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(Stopwatch::class, new Alias('debug.stopwatch', false));
        }

        $debug = $container->getParameter('kernel.debug');

        if ($debug) {
            $container->setParameter('debug.container.dump', '%kernel.cache_dir%/%kernel.container_class%.xml');
        }

        if ($debug && class_exists(Stopwatch::class)) {
            $loader->load('debug.php');
        }

        $definition = $container->findDefinition('debug.debug_handlers_listener');

        if (false === $config['log']) {
            $definition->replaceArgument(1, null);
        } elseif (true !== $config['log']) {
            $definition->replaceArgument(2, $config['log']);
        }

        if (!$config['throw']) {
            $container->setParameter('debug.error_handler.throw_at', 0);
        }

        if ($debug && class_exists(DebugProcessor::class)) {
            $definition = new Definition(DebugProcessor::class);
            $definition->setPublic(false);
            $definition->addArgument(new Reference('request_stack'));
            $container->setDefinition('debug.log_processor', $definition);
        }
    }

    private function registerRouterConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader, array $enabledLocales = [])
    {
        if (!$this->isConfigEnabled($container, $config)) {
            $container->removeDefinition('console.command.router_debug');
            $container->removeDefinition('console.command.router_match');

            return;
        }

        $loader->load('routing.xml');

        if (null === $config['utf8']) {
            trigger_deprecation('symfony/framework-bundle', '5.1', 'Not setting the "framework.router.utf8" configuration option is deprecated, it will default to "true" in version 6.0.');
        }

        if ($config['utf8']) {
            $container->getDefinition('routing.loader')->replaceArgument(1, ['utf8' => true]);
        }

        if ($enabledLocales) {
            $enabledLocales = implode('|', array_map('preg_quote', $enabledLocales));
            $container->getDefinition('routing.loader')->replaceArgument(2, ['_locale' => $enabledLocales]);
        }

        if (!class_exists(ExpressionLanguage::class)) {
            $container->removeDefinition('router.expression_language_provider');
        }

        $container->setParameter('router.resource', $config['resource']);
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

        if ($this->annotationsConfigEnabled) {
            $container->register('routing.loader.annotation', AnnotatedRouteControllerLoader::class)
                ->setPublic(false)
                ->addTag('routing.loader', ['priority' => -10])
                ->addArgument(new Reference('annotation_reader'));

            $container->register('routing.loader.annotation.directory', AnnotationDirectoryLoader::class)
                ->setPublic(false)
                ->addTag('routing.loader', ['priority' => -10])
                ->setArguments([
                    new Reference('file_locator'),
                    new Reference('routing.loader.annotation'),
                ]);

            $container->register('routing.loader.annotation.file', AnnotationFileLoader::class)
                ->setPublic(false)
                ->addTag('routing.loader', ['priority' => -10])
                ->setArguments([
                    new Reference('file_locator'),
                    new Reference('routing.loader.annotation'),
                ]);
        }
    }

    private function registerSessionConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('session.xml');

        // session storage
        $container->setAlias('session.storage', $config['storage_id'])->setPrivate(true);
        $options = ['cache_limiter' => '0'];
        foreach (['name', 'cookie_lifetime', 'cookie_path', 'cookie_domain', 'cookie_secure', 'cookie_httponly', 'cookie_samesite', 'use_cookies', 'gc_maxlifetime', 'gc_probability', 'gc_divisor', 'sid_length', 'sid_bits_per_character'] as $key) {
            if (isset($config[$key])) {
                $options[$key] = $config[$key];
            }
        }

        if ('auto' === ($options['cookie_secure'] ?? null)) {
            $locator = $container->getDefinition('session_listener')->getArgument(0);
            $locator->setValues($locator->getValues() + [
                'session_storage' => new Reference('session.storage', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                'request_stack' => new Reference('request_stack'),
            ]);
        }

        $container->setParameter('session.storage.options', $options);

        // session handler (the internal callback registered with PHP session management)
        if (null === $config['handler_id']) {
            // Set the handler class to be null
            $container->getDefinition('session.storage.native')->replaceArgument(1, null);
            $container->getDefinition('session.storage.php_bridge')->replaceArgument(0, null);
        } else {
            $container->resolveEnvPlaceholders($config['handler_id'], null, $usedEnvs);

            if ($usedEnvs || preg_match('#^[a-z]++://#', $config['handler_id'])) {
                $id = '.cache_connection.'.ContainerBuilder::hash($config['handler_id']);

                $container->getDefinition('session.abstract_handler')
                    ->replaceArgument(0, $container->hasDefinition($id) ? new Reference($id) : $config['handler_id']);

                $container->setAlias('session.handler', 'session.abstract_handler')->setPrivate(true);
            } else {
                $container->setAlias('session.handler', $config['handler_id'])->setPrivate(true);
            }
        }

        $container->setParameter('session.save_path', $config['save_path']);

        $container->setParameter('session.metadata.update_threshold', $config['metadata_update_threshold']);
    }

    private function registerRequestConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if ($config['formats']) {
            $loader->load('request.xml');

            $listener = $container->getDefinition('request.add_request_formats_listener');
            $listener->replaceArgument(0, $config['formats']);
        }
    }

    private function registerAssetsConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('assets.xml');

        if ($config['version_strategy']) {
            $defaultVersion = new Reference($config['version_strategy']);
        } else {
            $defaultVersion = $this->createVersion($container, $config['version'], $config['version_format'], $config['json_manifest_path'], '_default');
        }

        $defaultPackage = $this->createPackageDefinition($config['base_path'], $config['base_urls'], $defaultVersion);
        $container->setDefinition('assets._default_package', $defaultPackage);

        $namedPackages = [];
        foreach ($config['packages'] as $name => $package) {
            if (null !== $package['version_strategy']) {
                $version = new Reference($package['version_strategy']);
            } elseif (!\array_key_exists('version', $package) && null === $package['json_manifest_path']) {
                // if neither version nor json_manifest_path are specified, use the default
                $version = $defaultVersion;
            } else {
                // let format fallback to main version_format
                $format = $package['version_format'] ?: $config['version_format'];
                $version = isset($package['version']) ? $package['version'] : null;
                $version = $this->createVersion($container, $version, $format, $package['json_manifest_path'], $name);
            }

            $container->setDefinition('assets._package_'.$name, $this->createPackageDefinition($package['base_path'], $package['base_urls'], $version));
            $container->registerAliasForArgument('assets._package_'.$name, PackageInterface::class, $name.'.package');
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
    private function createPackageDefinition(?string $basePath, array $baseUrls, Reference $version): Definition
    {
        if ($basePath && $baseUrls) {
            throw new \LogicException('An asset package cannot have base URLs and base paths.');
        }

        $package = new ChildDefinition($baseUrls ? 'assets.url_package' : 'assets.path_package');
        $package
            ->setPublic(false)
            ->replaceArgument(0, $baseUrls ?: $basePath)
            ->replaceArgument(1, $version)
        ;

        return $package;
    }

    private function createVersion(ContainerBuilder $container, ?string $version, ?string $format, ?string $jsonManifestPath, string $name): Reference
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
            $definitionName = 'assets.json_manifest_version_strategy';
            if (0 === strpos(parse_url($jsonManifestPath, PHP_URL_SCHEME), 'http')) {
                $definitionName = 'assets.remote_json_manifest_version_strategy';
            }

            $def = new ChildDefinition($definitionName);
            $def->replaceArgument(0, $jsonManifestPath);
            $container->setDefinition('assets._version_'.$name, $def);

            return new Reference('assets._version_'.$name);
        }

        return new Reference('assets.empty_version_strategy');
    }

    private function registerTranslatorConfiguration(array $config, ContainerBuilder $container, LoaderInterface $loader, string $defaultLocale)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            $container->removeDefinition('console.command.translation_debug');
            $container->removeDefinition('console.command.translation_update');

            return;
        }

        $loader->load('translation.xml');

        // Use the "real" translator instead of the identity default
        $container->setAlias('translator', 'translator.default')->setPublic(true);
        $container->setAlias('translator.formatter', new Alias($config['formatter'], false));
        $translator = $container->findDefinition('translator.default');
        $translator->addMethodCall('setFallbackLocales', [$config['fallbacks'] ?: [$defaultLocale]]);

        $defaultOptions = $translator->getArgument(4);
        $defaultOptions['cache_dir'] = $config['cache_dir'];
        $translator->setArgument(4, $defaultOptions);

        $translator->setArgument(5, $config['enabled_locales']);

        $container->setParameter('translator.logging', $config['logging']);
        $container->setParameter('translator.default_path', $config['default_path']);

        // Discover translation directories
        $dirs = [];
        $transPaths = [];
        $nonExistingDirs = [];
        if (class_exists('Symfony\Component\Validator\Validation')) {
            $r = new \ReflectionClass('Symfony\Component\Validator\Validation');

            $dirs[] = $transPaths[] = \dirname($r->getFileName()).'/Resources/translations';
        }
        if (class_exists('Symfony\Component\Form\Form')) {
            $r = new \ReflectionClass('Symfony\Component\Form\Form');

            $dirs[] = $transPaths[] = \dirname($r->getFileName()).'/Resources/translations';
        }
        if (class_exists('Symfony\Component\Security\Core\Exception\AuthenticationException')) {
            $r = new \ReflectionClass('Symfony\Component\Security\Core\Exception\AuthenticationException');

            $dirs[] = $transPaths[] = \dirname($r->getFileName(), 2).'/Resources/translations';
        }
        $defaultDir = $container->getParameterBag()->resolveValue($config['default_path']);
        foreach ($container->getParameter('kernel.bundles_metadata') as $name => $bundle) {
            if ($container->fileExists($dir = $bundle['path'].'/Resources/translations') || $container->fileExists($dir = $bundle['path'].'/translations')) {
                $dirs[] = $dir;
            } else {
                $nonExistingDirs[] = $dir;
            }
        }

        foreach ($config['paths'] as $dir) {
            if ($container->fileExists($dir)) {
                $dirs[] = $transPaths[] = $dir;
            } else {
                throw new \UnexpectedValueException(sprintf('"%s" defined in translator.paths does not exist or is not a directory.', $dir));
            }
        }

        if ($container->hasDefinition('console.command.translation_debug')) {
            $container->getDefinition('console.command.translation_debug')->replaceArgument(5, $transPaths);
        }

        if ($container->hasDefinition('console.command.translation_update')) {
            $container->getDefinition('console.command.translation_update')->replaceArgument(6, $transPaths);
        }

        if ($container->fileExists($defaultDir)) {
            $dirs[] = $defaultDir;
        } else {
            $nonExistingDirs[] = $defaultDir;
        }

        // Register translation resources
        if ($dirs) {
            $files = [];
            $finder = Finder::create()
                ->followLinks()
                ->files()
                ->filter(function (\SplFileInfo $file) {
                    return 2 <= substr_count($file->getBasename(), '.') && preg_match('/\.\w+$/', $file->getBasename());
                })
                ->in($dirs)
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

            $projectDir = $container->getParameter('kernel.project_dir');

            $options = array_merge(
                $translator->getArgument(4),
                [
                    'resource_files' => $files,
                    'scanned_directories' => $scannedDirectories = array_merge($dirs, $nonExistingDirs),
                    'cache_vary' => [
                        'scanned_directories' => array_map(static function (string $dir) use ($projectDir): string {
                            return 0 === strpos($dir, $projectDir.'/') ? substr($dir, 1 + \strlen($projectDir)) : $dir;
                        }, $scannedDirectories),
                    ],
                ]
            );

            $translator->replaceArgument(4, $options);
        }
    }

    private function registerValidationConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader, bool $propertyInfoEnabled)
    {
        if (!$this->validatorConfigEnabled = $this->isConfigEnabled($container, $config)) {
            return;
        }

        if (!class_exists('Symfony\Component\Validator\Validation')) {
            throw new LogicException('Validation support cannot be enabled as the Validator component is not installed. Try running "composer require symfony/validator".');
        }

        if (!isset($config['email_validation_mode'])) {
            $config['email_validation_mode'] = 'loose';
        }

        $loader->load('validator.xml');

        $validatorBuilder = $container->getDefinition('validator.builder');

        $container->setParameter('validator.translation_domain', $config['translation_domain']);

        $files = ['xml' => [], 'yml' => []];
        $this->registerValidatorMapping($container, $config, $files);

        if (!empty($files['xml'])) {
            $validatorBuilder->addMethodCall('addXmlMappings', [$files['xml']]);
        }

        if (!empty($files['yml'])) {
            $validatorBuilder->addMethodCall('addYamlMappings', [$files['yml']]);
        }

        $definition = $container->findDefinition('validator.email');
        $definition->replaceArgument(0, $config['email_validation_mode']);

        if (\array_key_exists('enable_annotations', $config) && $config['enable_annotations']) {
            if (!$this->annotationsConfigEnabled) {
                throw new \LogicException('"enable_annotations" on the validator cannot be set as Annotations support is disabled.');
            }

            $validatorBuilder->addMethodCall('enableAnnotationMapping', [new Reference('annotation_reader')]);
        }

        if (\array_key_exists('static_method', $config) && $config['static_method']) {
            foreach ($config['static_method'] as $methodName) {
                $validatorBuilder->addMethodCall('addMethodMapping', [$methodName]);
            }
        }

        if (!$container->getParameter('kernel.debug')) {
            $validatorBuilder->addMethodCall('setMappingCache', [new Reference('validator.mapping.cache.adapter')]);
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
    }

    private function registerValidatorMapping(ContainerBuilder $container, array $config, array &$files)
    {
        $fileRecorder = function ($extension, $path) use (&$files) {
            $files['yaml' === $extension ? 'yml' : $extension][] = $path;
        };

        if (interface_exists('Symfony\Component\Form\FormInterface')) {
            $reflClass = new \ReflectionClass('Symfony\Component\Form\FormInterface');
            $fileRecorder('xml', \dirname($reflClass->getFileName()).'/Resources/config/validation.xml');
        }

        foreach ($container->getParameter('kernel.bundles_metadata') as $bundle) {
            $configDir = is_dir($bundle['path'].'/Resources/config') ? $bundle['path'].'/Resources/config' : $bundle['path'].'/config';

            if (
                $container->fileExists($file = $configDir.'/validation.yaml', false) ||
                $container->fileExists($file = $configDir.'/validation.yml', false)
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

    private function registerMappingFilesFromDir(string $dir, callable $fileRecorder)
    {
        foreach (Finder::create()->followLinks()->files()->in($dir)->name('/\.(xml|ya?ml)$/')->sortByName() as $file) {
            $fileRecorder($file->getExtension(), $file->getRealPath());
        }
    }

    private function registerMappingFilesFromConfig(ContainerBuilder $container, array $config, callable $fileRecorder)
    {
        foreach ($config['mapping']['paths'] as $path) {
            if (is_dir($path)) {
                $this->registerMappingFilesFromDir($path, $fileRecorder);
                $container->addResource(new DirectoryResource($path, '/^$/'));
            } elseif ($container->fileExists($path, false)) {
                if (!preg_match('/\.(xml|ya?ml)$/', $path, $matches)) {
                    throw new \RuntimeException(sprintf('Unsupported mapping type in "%s", supported types are XML & Yaml.', $path));
                }
                $fileRecorder($matches[1], $path);
            } else {
                throw new \RuntimeException(sprintf('Could not open file or directory "%s".', $path));
            }
        }
    }

    private function registerAnnotationsConfiguration(array $config, ContainerBuilder $container, LoaderInterface $loader)
    {
        if (!$this->annotationsConfigEnabled) {
            return;
        }

        if (!class_exists('Doctrine\Common\Annotations\Annotation')) {
            throw new LogicException('Annotations cannot be enabled as the Doctrine Annotation library is not installed.');
        }

        $loader->load('annotations.xml');

        if (!method_exists(AnnotationRegistry::class, 'registerUniqueLoader')) {
            $container->getDefinition('annotations.dummy_registry')
                ->setMethodCalls([['registerLoader', ['class_exists']]]);
        }

        if ('none' !== $config['cache']) {
            if (!class_exists('Doctrine\Common\Cache\CacheProvider')) {
                throw new LogicException('Annotations cannot be enabled as the Doctrine Cache library is not installed.');
            }

            $cacheService = $config['cache'];

            if ('php_array' === $config['cache']) {
                $cacheService = 'annotations.cache';

                // Enable warmer only if PHP array is used for cache
                $definition = $container->findDefinition('annotations.cache_warmer');
                $definition->addTag('kernel.cache_warmer');
            } elseif ('file' === $config['cache']) {
                $cacheDir = $container->getParameterBag()->resolveValue($config['file_cache_dir']);

                if (!is_dir($cacheDir) && false === @mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
                    throw new \RuntimeException(sprintf('Could not create cache directory "%s".', $cacheDir));
                }

                $container
                    ->getDefinition('annotations.filesystem_cache')
                    ->replaceArgument(0, $cacheDir)
                ;

                $cacheService = 'annotations.filesystem_cache';
            }

            $container
                ->getDefinition('annotations.cached_reader')
                ->replaceArgument(2, $config['debug'])
                // temporary property to lazy-reference the cache provider without using it until AddAnnotationsCachedReaderPass runs
                ->setProperty('cacheProviderBackup', new ServiceClosureArgument(new Reference($cacheService)))
                ->addTag('annotations.cached_reader')
            ;

            $container->setAlias('annotation_reader', 'annotations.cached_reader')->setPrivate(true);
            $container->setAlias(Reader::class, new Alias('annotations.cached_reader', false));
        } else {
            $container->removeDefinition('annotations.cached_reader');
        }
    }

    private function registerPropertyAccessConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader)
    {
        if (!class_exists(PropertyAccessor::class)) {
            return;
        }

        $loader->load('property_access.php');

        $container
            ->getDefinition('property_accessor')
            ->replaceArgument(0, $config['magic_call'])
            ->replaceArgument(1, $config['throw_exception_on_invalid_index'])
            ->replaceArgument(3, $config['throw_exception_on_invalid_property_path'])
            ->replaceArgument(4, new Reference(PropertyReadInfoExtractorInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->replaceArgument(5, new Reference(PropertyWriteInfoExtractorInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
        ;
    }

    private function registerSecretsConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            $container->removeDefinition('console.command.secrets_set');
            $container->removeDefinition('console.command.secrets_list');
            $container->removeDefinition('console.command.secrets_remove');
            $container->removeDefinition('console.command.secrets_generate_key');
            $container->removeDefinition('console.command.secrets_decrypt_to_local');
            $container->removeDefinition('console.command.secrets_encrypt_from_local');

            return;
        }

        $loader->load('secrets.php');

        $container->getDefinition('secrets.vault')->replaceArgument(0, $config['vault_directory']);

        if ($config['local_dotenv_file']) {
            $container->getDefinition('secrets.local_vault')->replaceArgument(0, $config['local_dotenv_file']);
        } else {
            $container->removeDefinition('secrets.local_vault');
        }

        if ($config['decryption_env_var']) {
            if (!preg_match('/^(?:[-.\w]*+:)*+\w++$/', $config['decryption_env_var'])) {
                throw new InvalidArgumentException(sprintf('Invalid value "%s" set as "decryption_env_var": only "word" characters are allowed.', $config['decryption_env_var']));
            }

            if (class_exists(LazyString::class)) {
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

    private function registerSecurityCsrfConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $phpLoader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        if (!class_exists('Symfony\Component\Security\Csrf\CsrfToken')) {
            throw new LogicException('CSRF support cannot be enabled as the Security CSRF component is not installed. Try running "composer require symfony/security-csrf".');
        }

        if (!$this->sessionConfigEnabled) {
            throw new \LogicException('CSRF protection needs sessions to be enabled.');
        }

        // Enable services for CSRF protection (even without forms)
        $phpLoader->load('security_csrf.php');

        if (!class_exists(CsrfExtension::class)) {
            $container->removeDefinition('twig.extension.security_csrf');
        }
    }

    private function registerSerializerConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('serializer.xml');

        $chainLoader = $container->getDefinition('serializer.mapping.chain_loader');

        if (!class_exists(PropertyAccessor::class)) {
            $container->removeAlias('serializer.property_accessor');
            $container->removeDefinition('serializer.normalizer.object');
        }

        if (!class_exists(Yaml::class)) {
            $container->removeDefinition('serializer.encoder.yaml');
        }

        if (!class_exists(UnwrappingDenormalizer::class) || !class_exists(PropertyAccessor::class)) {
            $container->removeDefinition('serializer.denormalizer.unwrapping');
        }

        $serializerLoaders = [];
        if (isset($config['enable_annotations']) && $config['enable_annotations']) {
            if (!$this->annotationsConfigEnabled) {
                throw new \LogicException('"enable_annotations" on the serializer cannot be set as Annotations support is disabled.');
            }

            $annotationLoader = new Definition(
                'Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader',
                [new Reference('annotation_reader')]
            );
            $annotationLoader->setPublic(false);

            $serializerLoaders[] = $annotationLoader;
        }

        $fileRecorder = function ($extension, $path) use (&$serializerLoaders) {
            $definition = new Definition(\in_array($extension, ['yaml', 'yml']) ? 'Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader' : 'Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader', [$path]);
            $definition->setPublic(false);
            $serializerLoaders[] = $definition;
        };

        foreach ($container->getParameter('kernel.bundles_metadata') as $bundle) {
            $configDir = is_dir($bundle['path'].'/Resources/config') ? $bundle['path'].'/Resources/config' : $bundle['path'].'/config';

            if ($container->fileExists($file = $configDir.'/serialization.xml', false)) {
                $fileRecorder('xml', $file);
            }

            if (
                $container->fileExists($file = $configDir.'/serialization.yaml', false) ||
                $container->fileExists($file = $configDir.'/serialization.yml', false)
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

        if (isset($config['name_converter']) && $config['name_converter']) {
            $container->getDefinition('serializer.name_converter.metadata_aware')->setArgument(1, new Reference($config['name_converter']));
        }

        if (isset($config['circular_reference_handler']) && $config['circular_reference_handler']) {
            $arguments = $container->getDefinition('serializer.normalizer.object')->getArguments();
            $context = ($arguments[6] ?? []) + ['circular_reference_handler' => new Reference($config['circular_reference_handler'])];
            $container->getDefinition('serializer.normalizer.object')->setArgument(5, null);
            $container->getDefinition('serializer.normalizer.object')->setArgument(6, $context);
        }

        if ($config['max_depth_handler'] ?? false) {
            $defaultContext = $container->getDefinition('serializer.normalizer.object')->getArgument(6);
            $defaultContext += ['max_depth_handler' => new Reference($config['max_depth_handler'])];
            $container->getDefinition('serializer.normalizer.object')->replaceArgument(6, $defaultContext);
        }
    }

    private function registerPropertyInfoConfiguration(ContainerBuilder $container, PhpFileLoader $loader)
    {
        if (!interface_exists(PropertyInfoExtractorInterface::class)) {
            throw new LogicException('PropertyInfo support cannot be enabled as the PropertyInfo component is not installed. Try running "composer require symfony/property-info".');
        }

        $loader->load('property_info.php');

        if (interface_exists('phpDocumentor\Reflection\DocBlockFactoryInterface')) {
            $definition = $container->register('property_info.php_doc_extractor', 'Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor');
            $definition->setPrivate(true);
            $definition->addTag('property_info.description_extractor', ['priority' => -1000]);
            $definition->addTag('property_info.type_extractor', ['priority' => -1001]);
        }

        if ($container->getParameter('kernel.debug')) {
            $container->removeDefinition('property_info.cache');
        }
    }

    private function registerLockConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader)
    {
        $loader->load('lock.php');

        foreach ($config['resources'] as $resourceName => $resourceStores) {
            if (0 === \count($resourceStores)) {
                continue;
            }

            // Generate stores
            $storeDefinitions = [];
            foreach ($resourceStores as $storeDsn) {
                $storeDsn = $container->resolveEnvPlaceholders($storeDsn, null, $usedEnvs);
                $storeDefinition = new Definition(interface_exists(StoreInterface::class) ? StoreInterface::class : PersistingStoreInterface::class);
                $storeDefinition->setFactory([StoreFactory::class, 'createStore']);
                $storeDefinition->setArguments([$storeDsn]);

                $container->setDefinition($storeDefinitionId = '.lock.'.$resourceName.'.store.'.$container->hash($storeDsn), $storeDefinition);

                $storeDefinition = new Reference($storeDefinitionId);

                $storeDefinitions[] = $storeDefinition;
            }

            // Wrap array of stores with CombinedStore
            if (\count($storeDefinitions) > 1) {
                $combinedDefinition = new ChildDefinition('lock.store.combined.abstract');
                $combinedDefinition->replaceArgument(0, $storeDefinitions);
                $container->setDefinition('lock.'.$resourceName.'.store', $combinedDefinition);
            } else {
                $container->setAlias('lock.'.$resourceName.'.store', new Alias((string) $storeDefinitions[0], false));
            }

            // Generate factories for each resource
            $factoryDefinition = new ChildDefinition('lock.factory.abstract');
            $factoryDefinition->replaceArgument(0, new Reference('lock.'.$resourceName.'.store'));
            $container->setDefinition('lock.'.$resourceName.'.factory', $factoryDefinition);

            // Generate services for lock instances
            $lockDefinition = new Definition(Lock::class);
            $lockDefinition->setPublic(false);
            $lockDefinition->setFactory([new Reference('lock.'.$resourceName.'.factory'), 'createLock']);
            $lockDefinition->setArguments([$resourceName]);
            $container->setDefinition('lock.'.$resourceName, $lockDefinition);

            // provide alias for default resource
            if ('default' === $resourceName) {
                $container->setAlias('lock.store', new Alias('lock.'.$resourceName.'.store', false));
                $container->setAlias('lock.factory', new Alias('lock.'.$resourceName.'.factory', false));
                $container->setAlias('lock', new Alias('lock.'.$resourceName, false));
                $container->setAlias(PersistingStoreInterface::class, new Alias('lock.store', false));
                $container->setAlias(LockFactory::class, new Alias('lock.factory', false));
                $container->setAlias(LockInterface::class, new Alias('lock', false));
            } else {
                $container->registerAliasForArgument('lock.'.$resourceName.'.store', PersistingStoreInterface::class, $resourceName.'.lock.store');
                $container->registerAliasForArgument('lock.'.$resourceName.'.factory', LockFactory::class, $resourceName.'.lock.factory');
                $container->registerAliasForArgument('lock.'.$resourceName, LockInterface::class, $resourceName.'.lock');
            }
        }
    }

    private function registerMessengerConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader, array $validationConfig)
    {
        if (!interface_exists(MessageBusInterface::class)) {
            throw new LogicException('Messenger support cannot be enabled as the Messenger component is not installed. Try running "composer require symfony/messenger".');
        }

        $loader->load('messenger.xml');

        if (class_exists(AmqpTransportFactory::class)) {
            $container->getDefinition('messenger.transport.amqp.factory')->addTag('messenger.transport_factory');
        }

        if (class_exists(RedisTransportFactory::class)) {
            $container->getDefinition('messenger.transport.redis.factory')->addTag('messenger.transport_factory');
        }

        if (class_exists(AmazonSqsTransportFactory::class)) {
            $container->getDefinition('messenger.transport.sqs.factory')->addTag('messenger.transport_factory');
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
        foreach ($config['buses'] as $busId => $bus) {
            $middleware = $bus['middleware'];

            if ($bus['default_middleware']) {
                if ('allow_no_handlers' === $bus['default_middleware']) {
                    $defaultMiddleware['after'][1]['arguments'] = [true];
                } else {
                    unset($defaultMiddleware['after'][1]['arguments']);
                }

                // argument to add_bus_name_stamp_middleware
                $defaultMiddleware['before'][0]['arguments'] = [$busId];

                $middleware = array_merge($defaultMiddleware['before'], $middleware, $defaultMiddleware['after']);
            }

            foreach ($middleware as $middlewareItem) {
                if (!$validationConfig['enabled'] && \in_array($middlewareItem['id'], ['validation', 'messenger.middleware.validation'], true)) {
                    throw new LogicException('The Validation middleware is only available when the Validator component is installed and enabled. Try running "composer require symfony/validator".');
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
        } else {
            $container->getDefinition('messenger.transport.symfony_serializer')
                ->replaceArgument(1, $config['serializer']['symfony_serializer']['format'])
                ->replaceArgument(2, $config['serializer']['symfony_serializer']['context']);
            $container->setAlias('messenger.default_serializer', $config['serializer']['default_serializer']);
        }

        $senderAliases = [];
        $transportRetryReferences = [];
        foreach ($config['transports'] as $name => $transport) {
            $serializerId = $transport['serializer'] ?? 'messenger.default_serializer';

            $transportDefinition = (new Definition(TransportInterface::class))
                ->setFactory([new Reference('messenger.transport_factory'), 'createTransport'])
                ->setArguments([$transport['dsn'], $transport['options'] + ['transport_name' => $name], new Reference($serializerId)])
                ->addTag('messenger.receiver', ['alias' => $name])
            ;
            $container->setDefinition($transportId = 'messenger.transport.'.$name, $transportDefinition);
            $senderAliases[$name] = $transportId;

            if (null !== $transport['retry_strategy']['service']) {
                $transportRetryReferences[$name] = new Reference($transport['retry_strategy']['service']);
            } else {
                $retryServiceId = sprintf('messenger.retry.multiplier_retry_strategy.%s', $name);
                $retryDefinition = new ChildDefinition('messenger.retry.abstract_multiplier_retry_strategy');
                $retryDefinition
                    ->replaceArgument(0, $transport['retry_strategy']['max_retries'])
                    ->replaceArgument(1, $transport['retry_strategy']['delay'])
                    ->replaceArgument(2, $transport['retry_strategy']['multiplier'])
                    ->replaceArgument(3, $transport['retry_strategy']['max_delay']);
                $container->setDefinition($retryServiceId, $retryDefinition);

                $transportRetryReferences[$name] = new Reference($retryServiceId);
            }
        }

        $senderReferences = [];
        // alias => service_id
        foreach ($senderAliases as $alias => $serviceId) {
            $senderReferences[$alias] = new Reference($serviceId);
        }
        // service_id => service_id
        foreach ($senderAliases as $serviceId) {
            $senderReferences[$serviceId] = new Reference($serviceId);
        }

        $messageToSendersMapping = [];
        foreach ($config['routing'] as $message => $messageConfiguration) {
            if ('*' !== $message && !class_exists($message) && !interface_exists($message, false)) {
                throw new LogicException(sprintf('Invalid Messenger routing configuration: class or interface "%s" not found.', $message));
            }

            // make sure senderAliases contains all senders
            foreach ($messageConfiguration['senders'] as $sender) {
                if (!isset($senderReferences[$sender])) {
                    throw new LogicException(sprintf('Invalid Messenger routing configuration: the "%s" class is being routed to a sender called "%s". This is not a valid transport or service id.', $message, $sender));
                }
            }

            $messageToSendersMapping[$message] = $messageConfiguration['senders'];
        }

        $sendersServiceLocator = ServiceLocatorTagPass::register($container, $senderReferences);

        $container->getDefinition('messenger.senders_locator')
            ->replaceArgument(0, $messageToSendersMapping)
            ->replaceArgument(1, $sendersServiceLocator)
        ;

        $container->getDefinition('messenger.retry.send_failed_message_for_retry_listener')
            ->replaceArgument(0, $sendersServiceLocator)
        ;

        $container->getDefinition('messenger.retry_strategy_locator')
            ->replaceArgument(0, $transportRetryReferences);

        if ($config['failure_transport']) {
            if (!isset($senderReferences[$config['failure_transport']])) {
                throw new LogicException(sprintf('Invalid Messenger configuration: the failure transport "%s" is not a valid transport or service id.', $config['failure_transport']));
            }

            $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')
                ->replaceArgument(0, $senderReferences[$config['failure_transport']]);
            $container->getDefinition('console.command.messenger_failed_messages_retry')
                ->replaceArgument(0, $config['failure_transport']);
            $container->getDefinition('console.command.messenger_failed_messages_show')
                ->replaceArgument(0, $config['failure_transport']);
            $container->getDefinition('console.command.messenger_failed_messages_remove')
                ->replaceArgument(0, $config['failure_transport']);
        } else {
            $container->removeDefinition('messenger.failure.send_failed_message_to_failure_transport_listener');
            $container->removeDefinition('console.command.messenger_failed_messages_retry');
            $container->removeDefinition('console.command.messenger_failed_messages_show');
            $container->removeDefinition('console.command.messenger_failed_messages_remove');
        }
    }

    private function registerCacheConfiguration(array $config, ContainerBuilder $container)
    {
        if (!class_exists(DefaultMarshaller::class)) {
            $container->removeDefinition('cache.default_marshaller');
        }

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
        foreach (['doctrine', 'psr6', 'redis', 'memcached', 'pdo'] as $name) {
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
        foreach ($config['pools'] as $name => $pool) {
            $pool['adapters'] = $pool['adapters'] ?: ['cache.app'];

            foreach ($pool['adapters'] as $provider => $adapter) {
                if ($config['pools'][$adapter]['tags'] ?? false) {
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

            if ($pool['tags']) {
                if (true !== $pool['tags'] && ($config['pools'][$pool['tags']]['tags'] ?? false)) {
                    $pool['tags'] = '.'.$pool['tags'].'.inner';
                }
                $container->register($name, TagAwareAdapter::class)
                    ->addArgument(new Reference('.'.$name.'.inner'))
                    ->addArgument(true !== $pool['tags'] ? new Reference($pool['tags']) : null)
                    ->setPublic($pool['public'])
                ;

                $pool['name'] = $name;
                $pool['public'] = false;
                $name = '.'.$name.'.inner';

                if (!\in_array($pool['name'], ['cache.app', 'cache.system'], true)) {
                    $container->registerAliasForArgument($pool['name'], TagAwareCacheInterface::class);
                    $container->registerAliasForArgument($name, CacheInterface::class, $pool['name']);
                    $container->registerAliasForArgument($name, CacheItemPoolInterface::class, $pool['name']);
                }
            } elseif (!\in_array($name, ['cache.app', 'cache.system'], true)) {
                $container->register('.'.$name.'.taggable', TagAwareAdapter::class)
                    ->addArgument(new Reference($name))
                ;
                $container->registerAliasForArgument('.'.$name.'.taggable', TagAwareCacheInterface::class, $name);
                $container->registerAliasForArgument($name, CacheInterface::class);
                $container->registerAliasForArgument($name, CacheItemPoolInterface::class);
            }

            $definition->setPublic($pool['public']);
            unset($pool['adapters'], $pool['public'], $pool['tags']);

            $definition->addTag('cache.pool', $pool);
            $container->setDefinition($name, $definition);
        }

        if (method_exists(PropertyAccessor::class, 'createCache')) {
            $propertyAccessDefinition = $container->register('cache.property_access', AdapterInterface::class);
            $propertyAccessDefinition->setPublic(false);

            if (!$container->getParameter('kernel.debug')) {
                $propertyAccessDefinition->setFactory([PropertyAccessor::class, 'createCache']);
                $propertyAccessDefinition->setArguments([null, 0, $version, new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]);
                $propertyAccessDefinition->addTag('cache.pool', ['clearer' => 'cache.system_clearer']);
                $propertyAccessDefinition->addTag('monolog.logger', ['channel' => 'cache']);
            } else {
                $propertyAccessDefinition->setClass(ArrayAdapter::class);
                $propertyAccessDefinition->setArguments([0, false]);
            }
        }
    }

    private function registerHttpClientConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader, array $profilerConfig)
    {
        $loader->load('http_client.xml');

        $container->getDefinition('http_client')->setArguments([$config['default_options'] ?? [], $config['max_host_connections'] ?? 6]);

        if (!$hasPsr18 = interface_exists(ClientInterface::class)) {
            $container->removeDefinition('psr18.http_client');
            $container->removeAlias(ClientInterface::class);
        }

        if (!interface_exists(HttpClient::class)) {
            $container->removeDefinition(HttpClient::class);
        }

        $httpClientId = $this->isConfigEnabled($container, $profilerConfig) ? '.debug.http_client.inner' : 'http_client';

        foreach ($config['scoped_clients'] as $name => $scopeConfig) {
            if ('http_client' === $name) {
                throw new InvalidArgumentException(sprintf('Invalid scope name: "%s" is reserved.', $name));
            }

            $scope = $scopeConfig['scope'] ?? null;
            unset($scopeConfig['scope']);

            if (null === $scope) {
                $container->register($name, ScopingHttpClient::class)
                    ->setFactory([ScopingHttpClient::class, 'forBaseUri'])
                    ->setArguments([new Reference($httpClientId), $scopeConfig['base_uri'], $scopeConfig])
                    ->addTag('http_client.client')
                ;
            } else {
                $container->register($name, ScopingHttpClient::class)
                    ->setArguments([new Reference($httpClientId), [$scope => $scopeConfig], $scope])
                    ->addTag('http_client.client')
                ;
            }

            $container->registerAliasForArgument($name, HttpClientInterface::class);

            if ($hasPsr18) {
                $container->setDefinition('psr18.'.$name, new ChildDefinition('psr18.http_client'))
                    ->replaceArgument(0, new Reference($name));

                $container->registerAliasForArgument('psr18.'.$name, ClientInterface::class, $name);
            }
        }
    }

    private function registerMailerConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (!class_exists(Mailer::class)) {
            throw new LogicException('Mailer support cannot be enabled as the component is not installed. Try running "composer require symfony/mailer".');
        }

        $loader->load('mailer.xml');
        $loader->load('mailer_transports.xml');
        if (!\count($config['transports']) && null === $config['dsn']) {
            $config['dsn'] = 'smtp://null';
        }
        $transports = $config['dsn'] ? ['main' => $config['dsn']] : $config['transports'];
        $container->getDefinition('mailer.transports')->setArgument(0, $transports);
        $container->getDefinition('mailer.default_transport')->setArgument(0, current($transports));

        $mailer = $container->getDefinition('mailer.mailer');
        if (false === $messageBus = $config['message_bus']) {
            $mailer->replaceArgument(1, null);
        } else {
            $mailer->replaceArgument(1, $messageBus ? new Reference($messageBus) : new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }

        $classToServices = [
            SesTransportFactory::class => 'mailer.transport_factory.amazon',
            GmailTransportFactory::class => 'mailer.transport_factory.gmail',
            MandrillTransportFactory::class => 'mailer.transport_factory.mailchimp',
            MailgunTransportFactory::class => 'mailer.transport_factory.mailgun',
            PostmarkTransportFactory::class => 'mailer.transport_factory.postmark',
            SendgridTransportFactory::class => 'mailer.transport_factory.sendgrid',
        ];

        foreach ($classToServices as $class => $service) {
            if (!class_exists($class)) {
                $container->removeDefinition($service);
            }
        }

        $envelopeListener = $container->getDefinition('mailer.envelope_listener');
        $envelopeListener->setArgument(0, $config['envelope']['sender'] ?? null);
        $envelopeListener->setArgument(1, $config['envelope']['recipients'] ?? null);

        if ($config['headers']) {
            $headers = new Definition(Headers::class);
            foreach ($config['headers'] as $name => $data) {
                $value = $data['value'];
                if (\in_array(strtolower($name), ['from', 'to', 'cc', 'bcc', 'reply-to'])) {
                    $value = (array) $value;
                }
                $headers->addMethodCall('addHeader', [$name, $value]);
            }
            $messageListener = $container->getDefinition('mailer.message_listener');
            $messageListener->setArgument(0, $headers);
        } else {
            $container->removeDefinition('mailer.message_listener');
        }
    }

    private function registerNotifierConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader)
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
        }
        if ($config['texter_transports']) {
            $container->getDefinition('texter.transports')->setArgument(0, $config['texter_transports']);
        } else {
            $container->removeDefinition('texter');
        }

        if ($this->mailerConfigEnabled) {
            $sender = $container->getDefinition('mailer.envelope_listener')->getArgument(0);
            $container->getDefinition('notifier.channel.email')->setArgument(2, $sender);
        } else {
            $container->removeDefinition('notifier.channel.email');
        }

        if ($this->messengerConfigEnabled) {
            if ($config['notification_on_failed_messages']) {
                $container->getDefinition('notifier.failed_message_listener')->addTag('kernel.event_subscriber');
            }

            // as we have a bus, the channels don't need the transports
            $container->getDefinition('notifier.channel.chat')->setArgument(0, null);
            if ($container->hasDefinition('notifier.channel.email')) {
                $container->getDefinition('notifier.channel.email')->setArgument(0, null);
            }
            $container->getDefinition('notifier.channel.sms')->setArgument(0, null);
        }

        $container->getDefinition('notifier.channel_policy')->setArgument(0, $config['channel_policy']);

        $classToServices = [
            SlackTransportFactory::class => 'notifier.transport_factory.slack',
            TelegramTransportFactory::class => 'notifier.transport_factory.telegram',
            MattermostTransportFactory::class => 'notifier.transport_factory.mattermost',
            NexmoTransportFactory::class => 'notifier.transport_factory.nexmo',
            RocketChatTransportFactory::class => 'notifier.transport_factory.rocketchat',
            TwilioTransportFactory::class => 'notifier.transport_factory.twilio',
            FirebaseTransportFactory::class => 'notifier.transport_factory.firebase',
            FreeMobileTransportFactory::class => 'notifier.transport_factory.freemobile',
            OvhCloudTransportFactory::class => 'notifier.transport_factory.ovhcloud',
            SinchTransportFactory::class => 'notifier.transport_factory.sinch',
        ];

        foreach ($classToServices as $class => $service) {
            if (!class_exists($class)) {
                $container->removeDefinition($service);
            }
        }

        if (isset($config['admin_recipients'])) {
            $notifier = $container->getDefinition('notifier');
            foreach ($config['admin_recipients'] as $i => $recipient) {
                $id = 'notifier.admin_recipient.'.$i;
                $container->setDefinition($id, new Definition(AdminRecipient::class, [$recipient['email'], $recipient['phone']]));
                $notifier->addMethodCall('addAdminRecipient', [new Reference($id)]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getXsdValidationBasePath()
    {
        return \dirname(__DIR__).'/Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/symfony';
    }
}
