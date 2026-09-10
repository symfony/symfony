<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddDebugLogProcessorPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddValidatorSecurityExpressionLanguageProviderPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AssetsContextPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ContainerBuilderDebugDumpPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DefaultCachePoolsPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DefaultMessageBusPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ErrorLoggerCompilerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\FindCommandBundlesPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\JsonSchemaConfigDumpPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\PhpConfigReferenceDumpPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ProfilerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RemoveUnusedSessionMarshallingHandlerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ReportMissingDependenciesPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TestServiceContainerRealRefPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TestServiceContainerWeakRefPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslationLintCommandPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslationUpdateCommandPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\UnusedTagsPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\VirtualRequestStackPass;
use Symfony\Component\Asset\AssetBundle;
use Symfony\Component\AssetMapper\AssetMapperBundle;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\CacheBundle;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\Console\ConsoleBundle;
use Symfony\Component\DependencyInjection\Compiler\AddBehaviorDescribingTagsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Compiler\RegisterReverseContainerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\UndefinedExtensionHandler;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\EventDispatcher\DependencyInjection\AddEventAliasesPass;
use Symfony\Component\Form\DependencyInjection\FormPass;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerBundle;
use Symfony\Component\HttpClient\HttpClientBundle;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\DependencyInjection\ControllerArgumentValueResolverPass;
use Symfony\Component\HttpKernel\DependencyInjection\ControllerAttributesListenerPass;
use Symfony\Component\HttpKernel\DependencyInjection\FragmentRendererPass;
use Symfony\Component\HttpKernel\DependencyInjection\LoggerPass;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterLocaleAwareServicesPass;
use Symfony\Component\HttpKernel\DependencyInjection\RemoveEmptyControllerArgumentLocatorsPass;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\JsonPath\JsonPathBundle;
use Symfony\Component\JsonStreamer\JsonStreamerBundle;
use Symfony\Component\Lock\LockBundle;
use Symfony\Component\Mailer\MailerBundle;
use Symfony\Component\Messenger\MessengerBundle;
use Symfony\Component\Mime\MimeBundle;
use Symfony\Component\Notifier\NotifierBundle;
use Symfony\Component\ObjectMapper\ObjectMapperBundle;
use Symfony\Component\Process\ProcessBundle;
use Symfony\Component\PropertyAccess\PropertyAccessBundle;
use Symfony\Component\PropertyInfo\PropertyInfoBundle;
use Symfony\Component\RateLimiter\RateLimiterBundle;
use Symfony\Component\RemoteEvent\RemoteEventBundle;
use Symfony\Component\Routing\DependencyInjection\AddExpressionLanguageProvidersPass;
use Symfony\Component\Routing\DependencyInjection\RoutingControllerPass;
use Symfony\Component\Routing\DependencyInjection\RoutingResolverPass;
use Symfony\Component\Routing\RouterBundle;
use Symfony\Component\Runtime\SymfonyRuntime;
use Symfony\Component\Scheduler\SchedulerBundle;
use Symfony\Component\Semaphore\SemaphoreBundle;
use Symfony\Component\Serializer\SerializerBundle;
use Symfony\Component\Translation\TranslationBundle;
use Symfony\Component\TypeInfo\TypeInfoBundle;
use Symfony\Component\Uid\UidBundle;
use Symfony\Component\Validator\ValidationBundle;
use Symfony\Component\VarExporter\Internal\LazyObjectRegistry;
use Symfony\Component\VarExporter\Internal\Registry;
use Symfony\Component\Webhook\WebhookBundle;
use Symfony\Component\WebLink\WebLinkBundle;
use Symfony\Component\Workflow\WorkflowBundle;

// Help opcache.preload discover always-needed symbols
class_exists(ApcuAdapter::class);
class_exists(ArrayAdapter::class);
class_exists(ChainAdapter::class);
class_exists(PhpArrayAdapter::class);
class_exists(PhpFilesAdapter::class);
class_exists(Dotenv::class);
class_exists(ErrorHandler::class);
class_exists(LazyObjectRegistry::class);
class_exists(Registry::class);

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[RequiredBundle(ServicesBundle::class)]
#[RequiredBundle(RouterBundle::class)]
#[RequiredBundle(CacheBundle::class)]
#[RequiredBundle(ConsoleBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(AssetBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(SerializerBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(ValidationBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(TranslationBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(WebLinkBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(LockBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(MessengerBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(SemaphoreBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(WorkflowBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(RemoteEventBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(HtmlSanitizerBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(TypeInfoBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(PropertyAccessBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(PropertyInfoBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(UidBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(SchedulerBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(JsonStreamerBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(AssetMapperBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(RateLimiterBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(WebhookBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(HttpClientBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(MailerBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(NotifierBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(ProcessBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(JsonPathBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(MimeBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(ObjectMapperBundle::class, ignoreOnInvalid: true)]
class FrameworkBundle extends Bundle
{
    public function boot(): void
    {
        $_ENV['DOCTRINE_DEPRECATIONS'] = $_SERVER['DOCTRINE_DEPRECATIONS'] ??= 'trigger';

        if (class_exists(SymfonyRuntime::class)) {
            $handler = get_error_handler();
        } else {
            $handler = [ErrorHandler::register(null, false)];
        }

        if (\is_array($handler) && $handler[0] instanceof ErrorHandler) {
            $this->container->get('debug.error_handler_configurator')->configure($handler[0]);
        }

        if ($this->container->getParameter('kernel.http_method_override')) {
            Request::enableHttpMethodParameterOverride();
        }

        if ($this->container->hasParameter('kernel.allowed_http_method_override')) {
            Request::setAllowedHttpMethodOverride($this->container->getParameter('kernel.allowed_http_method_override'));
        }

        if ($this->container->hasParameter('kernel.trust_x_sendfile_type_header') && $this->container->getParameter('kernel.trust_x_sendfile_type_header')) {
            BinaryFileResponse::trustXSendfileTypeHeader();
        }
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // so that a missing extension can be reported with the package that provides it;
        // "cache" is deliberately absent, it is a hard requirement of this bundle and its
        // bundle is always registered, so suggesting to require it could never help
        UndefinedExtensionHandler::addPackages($container, [
            'asset' => 'symfony/asset',
            'asset_mapper' => 'symfony/asset-mapper',
            'html_sanitizer' => 'symfony/html-sanitizer',
            'http_client' => 'symfony/http-client',
            'json_streamer' => 'symfony/json-streamer',
            'lock' => 'symfony/lock',
            'mailer' => 'symfony/mailer',
            'messenger' => 'symfony/messenger',
            'notifier' => 'symfony/notifier',
            'property_access' => 'symfony/property-access',
            'property_info' => 'symfony/property-info',
            'rate_limiter' => 'symfony/rate-limiter',
            'remote_event' => 'symfony/remote-event',
            'scheduler' => 'symfony/scheduler',
            'semaphore' => 'symfony/semaphore',
            'serializer' => 'symfony/serializer',
            'translation' => 'symfony/translation',
            'type_info' => 'symfony/type-info',
            'uid' => 'symfony/uid',
            'validation' => 'symfony/validator',
            'web_link' => 'symfony/web-link',
            'webhook' => 'symfony/webhook',
            'workflow' => 'symfony/workflow',
        ]);

        $container->addCompilerPass(new AddEventAliasesPass(
            array_merge(
                KernelEvents::ALIASES,
                class_exists(FormEvents::class) ? FormEvents::ALIASES : [],
            ),
            [
                KernelEvents::REQUEST,
                KernelEvents::CONTROLLER,
                KernelEvents::CONTROLLER_ARGUMENTS,
                KernelEvents::RESPONSE,
                KernelEvents::FINISH_REQUEST,
            ],
        ));

        $container->addCompilerPass(new AddBehaviorDescribingTagsPass(['kernel.locale_aware']), PassConfig::TYPE_BEFORE_OPTIMIZATION, 200);
        $container->addCompilerPass(new AssetsContextPass());
        $container->addCompilerPass(new FindCommandBundlesPass());
        $container->addCompilerPass(new LoggerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -32);
        $container->addCompilerPass(new RegisterControllerArgumentLocatorsPass());
        $container->addCompilerPass(new RemoveEmptyControllerArgumentLocatorsPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new RoutingResolverPass());
        $this->addCompilerPassIfExists($container, RoutingControllerPass::class);
        $container->addCompilerPass(new ProfilerPass());
        $this->addCompilerPassIfExists($container, ControllerAttributesListenerPass::class, PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new AddValidatorSecurityExpressionLanguageProviderPass());
        $container->addCompilerPass(new TranslationLintCommandPass(), PassConfig::TYPE_BEFORE_REMOVING, 10);
        $container->addCompilerPass(new AddExpressionLanguageProvidersPass());
        $container->addCompilerPass(new FragmentRendererPass());
        $container->addCompilerPass(new ControllerArgumentValueResolverPass());
        $container->addCompilerPass(new DefaultCachePoolsPass());
        $container->addCompilerPass(new ReportMissingDependenciesPass());
        $this->addCompilerPassIfExists($container, FormPass::class);
        $container->addCompilerPass(new RegisterLocaleAwareServicesPass());
        $container->addCompilerPass(new TestServiceContainerWeakRefPass(), PassConfig::TYPE_BEFORE_REMOVING, -32);
        $container->addCompilerPass(new TestServiceContainerRealRefPass(), PassConfig::TYPE_AFTER_REMOVING);
        // must run before CachePoolPass, which wires the pools this pass can still remove
        $container->addCompilerPass(new DefaultMessageBusPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 33);
        $container->addCompilerPass(new RegisterReverseContainerPass(true));
        $container->addCompilerPass(new RegisterReverseContainerPass(false), PassConfig::TYPE_AFTER_REMOVING);
        $container->addCompilerPass(new RemoveUnusedSessionMarshallingHandlerPass());
        // must be registered after MonologBundle's LoggerChannelPass
        $container->addCompilerPass(new ErrorLoggerCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -32);
        $container->addCompilerPass(new VirtualRequestStackPass());
        $container->addCompilerPass(new TranslationUpdateCommandPass(), PassConfig::TYPE_BEFORE_REMOVING);

        if ($container->getParameter('kernel.debug')) {
            if ($container->hasParameter('.kernel.config_dir') && $container->hasParameter('.kernel.bundles_definition')) {
                $container->addCompilerPass(new PhpConfigReferenceDumpPass($container->getParameter('.kernel.config_dir').'/reference.php', $container->getParameter('.kernel.bundles_definition')));
                $container->addCompilerPass(new JsonSchemaConfigDumpPass($container->getParameter('.kernel.config_dir').'/schema.json', $container->getParameter('.kernel.bundles_definition')));
            }
            $container->addCompilerPass(new AddDebugLogProcessorPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 2);
            $container->addCompilerPass(new UnusedTagsPass(), PassConfig::TYPE_AFTER_REMOVING);
            $container->addCompilerPass(new ContainerBuilderDebugDumpPass(), PassConfig::TYPE_BEFORE_REMOVING, -255);
        }
    }

    /**
     * @internal
     */
    public static function considerProfilerEnabled(): bool
    {
        return !($GLOBALS['app'] ?? null) instanceof Application || (\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) || !isset($_SERVER['QUERY_STRING'])) && \in_array('--profile', $_SERVER['argv'] ?? [], true);
    }

    private function addCompilerPassIfExists(ContainerBuilder $container, string $class, string $type = PassConfig::TYPE_BEFORE_OPTIMIZATION, int $priority = 0): void
    {
        $container->addResource(new ClassExistenceResource($class));

        if (class_exists($class)) {
            $container->addCompilerPass(new $class(), $type, $priority);
        }
    }
}
