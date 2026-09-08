<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\MappedSuperclass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DefaultMessageBusPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RemoveMissingHttpClientDependenciesPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FullStack;
use Symfony\Component\AssetMapper\AssetMapperBundle;
use Symfony\Component\AssetMapper\DependencyInjection\RemoveMissingDependenciesPass as AssetMapperRemoveMissingDependenciesPass;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PdoTagAwareAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\CacheBundle;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\AddBehaviorDescribingTagsPass;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveBindingsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveTaggedIteratorArgumentPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Form;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerBundle;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\DependencyInjection\RemoveMissingDependenciesPass as HttpClientRemoveMissingDependenciesPass;
use Symfony\Component\HttpClient\Exception\ChunkCacheItemNotFoundException;
use Symfony\Component\HttpClient\HttpClientBundle;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\ThrottlingHttpClient;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\DependencyInjection\LoggerPass;
use Symfony\Component\HttpKernel\EventListener\ProfilerListener;
use Symfony\Component\HttpKernel\EventListener\RateLimitAttributeListener;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Fragment\FragmentUriGeneratorInterface;
use Symfony\Component\JsonStreamer\JsonStreamerBundle;
use Symfony\Component\Lock\LockBundle;
use Symfony\Component\Mailer\EventListener\InMemoryPgpPublicKeyRepository;
use Symfony\Component\Mailer\EventListener\InMemorySmimeCertificateRepository;
use Symfony\Component\Mailer\EventListener\PgpMimeEncryptedMessageListener;
use Symfony\Component\Mailer\EventListener\PgpMimeSignedMessageListener;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Messenger\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Messenger\MessengerBundle;
use Symfony\Component\Mime\Crypto\PgpEncrypter;
use Symfony\Component\Mime\Crypto\PgpSigner;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\PropertyAccess\PropertyAccessBundle;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\PropertyInfoBundle;
use Symfony\Component\RateLimiter\DependencyInjection\DefaultLockFactoryPass;
use Symfony\Component\RateLimiter\RateLimiterBundle;
use Symfony\Component\RemoteEvent\Messenger\ConsumeRemoteEventHandler;
use Symfony\Component\RemoteEvent\RemoteEventBundle;
use Symfony\Component\Scheduler\SchedulerBundle;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Semaphore\SemaphoreBundle;
use Symfony\Component\Semaphore\Store\StoreFactory as SemaphoreStoreFactory;
use Symfony\Component\Serializer\DependencyInjection\SerializerPass;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\FormErrorNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\TranslatableNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Translation\Command\XliffUpdateSourcesCommand;
use Symfony\Component\Translation\DependencyInjection\TranslatorPass;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\TypeInfo\TypeInfoBundle;
use Symfony\Component\Uid\Factory\UuidFactory;
use Symfony\Component\Uid\UidBundle;
use Symfony\Component\Uid\Uuid47Transformer;
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\DependencyInjection\AddConstraintValidatorsPass;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Webhook\DependencyInjection\RemoveMissingDependenciesPass as WebhookRemoveMissingDependenciesPass;
use Symfony\Component\Webhook\WebhookBundle;
use Symfony\Component\WebLink\EventListener\AddLinkHeaderListener;
use Symfony\Component\WebLink\WebLinkBundle;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Workflow\WorkflowBundle;
use Symfony\Component\Yaml\Schema\SchemaResolverInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class FrameworkExtensionTestCase extends TestCase
{
    private static array $containerCache = [];

    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testFormCsrfProtection()
    {
        $container = $this->createContainerFromFile('full');

        $def = $container->getDefinition('form.type_extension.csrf');

        $this->assertTrue($container->getParameter('form.type_extension.csrf.enabled'));
        $this->assertEquals('%form.type_extension.csrf.enabled%', $def->getArgument(1));
        $this->assertEquals('_csrf', $container->getParameter('form.type_extension.csrf.field_name'));
        $this->assertEquals('%form.type_extension.csrf.field_name%', $def->getArgument(2));
    }

    public function testFormCsrfProtectionWithCsrfDisabled()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('To use form CSRF protection, "framework.csrf_protection" must be enabled.');

        $this->createContainerFromFile('form_csrf_disabled');
    }

    public function testPropertyAccessConfigurationIsForwardedToPropertyAccessBundle()
    {
        $container = $this->createContainer(['kernel.charset' => 'UTF-8', 'kernel.secret' => 'secret', 'kernel.runtime_environment' => 'test']);
        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension(new PropertyAccessBundle()->getContainerExtension());
        $this->loadFromFile($container, 'legacy_property_access');
        $container->compile();

        $def = $container->getDefinition('test_property_accessor');
        $this->assertSame(PropertyAccessor::MAGIC_GET | PropertyAccessor::MAGIC_CALL, $def->getArgument(0));
        $this->assertSame(PropertyAccessor::THROW_ON_INVALID_INDEX, $def->getArgument(1));
        $this->assertTrue($def->getArgument(5));
    }

    public function testRequestAndSessionValueResolversRunBeforeEntityValueResolver()
    {
        $container = $this->createContainerFromFile('full');

        // DoctrineBundle ships EntityValueResolver at priority 110. Lower priorities trigger an
        // entity-manager bootstrap on every Request/Session controller argument before the
        // dedicated resolver is asked, costing tens of ms per request.
        $entityValueResolverPriority = 110;

        $requestTag = $container->getDefinition('argument_resolver.request')->getTag('controller.argument_value_resolver');
        $this->assertGreaterThan($entityValueResolverPriority, $requestTag[0]['priority']);

        $sessionTag = $container->getDefinition('argument_resolver.session')->getTag('controller.argument_value_resolver');
        $this->assertGreaterThan($entityValueResolverPriority, $sessionTag[0]['priority']);
    }

    public function testCsrfProtectionNeedsSessionToBeEnabled()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('CSRF protection needs sessions to be enabled.');
        $this->createContainerFromFile('csrf_needs_session');
    }

    public function testCsrfProtectionForFormsEnablesCsrfProtectionAutomatically()
    {
        $container = $this->createContainerFromFile('csrf');

        $this->assertTrue($container->hasDefinition('security.csrf.token_manager'));
    }

    public function testFormsCsrfIsEnabledByDefault()
    {
        if (class_exists(FullStack::class)) {
            $this->markTestSkipped('testing with the FullStack prevents verifying default values');
        }
        $container = $this->createContainerFromFile('form_default_csrf');

        $this->assertTrue($container->hasDefinition('security.csrf.token_manager'));
        $this->assertTrue($container->hasParameter('form.type_extension.csrf.enabled'));
        $this->assertTrue($container->getParameter('form.type_extension.csrf.enabled'));
    }

    public function testHttpMethodOverride()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertFalse($container->getParameter('kernel.http_method_override'));
    }

    public function testAllowedHttpMethodOverride()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertNull($container->getParameter('kernel.allowed_http_method_override'));
    }

    public function testAllowedHttpMethodOverrideWithSpecificMethods()
    {
        $container = $this->createContainerFromClosure(static function ($container) {
            $container->loadFromExtension('framework', [
                'http_method_override' => true,
                'allowed_http_method_override' => ['PUT', 'DELETE'],
                'secret' => 's3cr3t',
            ]);
        });

        $this->assertTrue($container->getParameter('kernel.http_method_override'));
        $this->assertEquals(['PUT', 'DELETE'], $container->getParameter('kernel.allowed_http_method_override'));
    }

    public function testTrustXSendfileTypeHeader()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->getParameter('kernel.trust_x_sendfile_type_header'));
    }

    public function testEsi()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('esi'), '->registerEsiConfiguration() loads esi.xml');
        $this->assertTrue($container->hasDefinition('fragment.renderer.esi'), 'The ESI fragment renderer is registered');
    }

    public function testEsiDisabled()
    {
        $container = $this->createContainerFromFile('esi_disabled');

        $this->assertFalse($container->hasDefinition('fragment.renderer.esi'), 'The ESI fragment renderer is not registered');
        $this->assertFalse($container->hasDefinition('esi'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testFragmentsAndHinclude()
    {
        $this->expectUserDeprecationMessage('Since symfony/framework-bundle 8.2: Setting the "framework.fragments.hinclude_default_template" configuration option is deprecated. It will be removed in version 9.0.');

        $container = $this->createContainerFromFile('fragments_and_hinclude');
        $this->assertTrue($container->has('fragment.uri_generator'));
        $this->assertTrue($container->hasAlias(FragmentUriGeneratorInterface::class));
        $this->assertTrue($container->hasParameter('fragment.renderer.hinclude.global_template'));
        $this->assertSame('global_hinclude_template', $container->getDefinition('fragment.renderer.hinclude')->getArgument(2));
    }

    public function testFragmentsWithoutHincludeDefaultTemplate()
    {
        $container = $this->createContainerFromFile('fragments_without_hinclude_template');

        $this->assertTrue($container->hasDefinition('fragment.renderer.hinclude'));
        $this->assertTrue($container->hasParameter('fragment.renderer.hinclude.global_template'));
        $this->assertNull($container->getDefinition('fragment.renderer.hinclude')->getArgument(2));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testHincludeGlobalTemplateParameterIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/framework-bundle 8.2: The "fragment.renderer.hinclude.global_template" parameter is deprecated. It will be removed in version 9.0.');

        $container = $this->createContainerFromFile('fragments_without_hinclude_template');

        $container->getParameter('fragment.renderer.hinclude.global_template');
    }

    public function testSsi()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('ssi'), '->registerSsiConfiguration() loads ssi.xml');
        $this->assertTrue($container->hasDefinition('fragment.renderer.ssi'), 'The SSI fragment renderer is registered');
    }

    public function testSsiDisabled()
    {
        $container = $this->createContainerFromFile('ssi_disabled');

        $this->assertFalse($container->hasDefinition('fragment.renderer.ssi'), 'The SSI fragment renderer is not registered');
        $this->assertFalse($container->hasDefinition('ssi'));
    }

    public function testEsiAndSsiWithoutFragments()
    {
        $container = $this->createContainerFromFile('esi_and_ssi_without_fragments');

        $this->assertFalse($container->hasDefinition('fragment.renderer.hinclude'), 'The HInclude fragment renderer is not registered');
        $this->assertTrue($container->hasDefinition('fragment.renderer.esi'), 'The ESI fragment renderer is registered');
        $this->assertTrue($container->hasDefinition('fragment.renderer.ssi'), 'The SSI fragment renderer is registered');
    }

    public function testEnabledProfiler()
    {
        $container = $this->createContainerFromFile('profiler');

        $this->assertTrue($container->hasDefinition('profiler'), '->registerProfilerConfiguration() loads profiling.xml');
        $this->assertTrue($container->hasDefinition('data_collector.config'), '->registerProfilerConfiguration() loads collectors.xml');
        $this->assertTrue($container->hasDefinition('.data_collector.command'));
    }

    public function testDisabledProfiler()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertFalse($container->hasDefinition('profiler'), '->registerProfilerConfiguration() does not load profiling.xml');
        $this->assertFalse($container->hasDefinition('data_collector.config'), '->registerProfilerConfiguration() does not load collectors.xml');
    }

    public function testProfilerWithoutConsole()
    {
        $extension = new class extends FrameworkExtension {
            protected function hasConsole(): bool
            {
                return false;
            }

            public function getAlias(): string
            {
                return 'framework';
            }
        };

        $container = $this->createContainerFromFile('profiler', [], true, false, $extension);
        $container->compile();

        $this->assertFalse($container->hasDefinition('.data_collector.command'));
    }

    public function testProfilerCollectSerializerDataEnabled()
    {
        $container = $this->createContainerFromFile('profiler');

        $this->assertTrue($container->hasDefinition('profiler'));
        $this->assertTrue($container->hasDefinition('serializer.data_collector'));
        $this->assertTrue($container->hasDefinition('debug.serializer'));
    }

    public function testProfilerExclusions()
    {
        if (8 > (new \ReflectionMethod(ProfilerListener::class, '__construct'))->getNumberOfParameters()) {
            $this->markTestSkipped('This test requires symfony/http-kernel 8.2 or higher.');
        }

        $container = $this->createContainerFromFile('profiler_exclusions');

        $definition = $container->getDefinition('profiler_listener');

        $this->assertSame(['^/\.well-known/'], $definition->getArgument(6));
        $this->assertSame([404 => [], 400 => ['^/foo', '^/bar']], $definition->getArgument(7));
    }

    public function testWorkflowsConfigurationIsForwardedToWorkflowBundle()
    {
        $container = $this->createContainer(['kernel.charset' => 'UTF-8', 'kernel.secret' => 'secret', 'kernel.runtime_environment' => 'test']);
        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension(new WorkflowBundle()->getContainerExtension());
        $this->loadFromFile($container, 'legacy_workflows');
        $container->compile();

        $this->assertSame(Workflow::class, $container->getDefinition('test_workflow')->getClass());
        $this->assertSame('article', $container->getDefinition('test_workflow')->getTag('workflow')[0]['name']);
    }

    public function testWebLinkConfigurationIsForwardedToWebLinkBundle()
    {
        $container = $this->createContainer(['kernel.charset' => 'UTF-8', 'kernel.secret' => 'secret', 'kernel.runtime_environment' => 'test']);
        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension(new WebLinkBundle()->getContainerExtension());
        $this->loadFromFile($container, 'legacy_web_link');
        $container->compile();

        $this->assertSame(AddLinkHeaderListener::class, $container->getDefinition('test_add_link_header_listener')->getClass());
    }

    #[DataProvider('provideRemoteEventConfigurationFixtures')]
    public function testRemoteEventConfigurationIsForwardedToRemoteEventBundle(string $file)
    {
        $container = $this->createContainer(['kernel.charset' => 'UTF-8', 'kernel.secret' => 'secret', 'kernel.runtime_environment' => 'test']);
        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension(new RemoteEventBundle()->getContainerExtension());
        $this->loadFromFile($container, $file);
        $container->compile();

        $this->assertSame(ConsumeRemoteEventHandler::class, $container->getDefinition('test_remote_event_handler')->getClass());
    }

    public static function provideRemoteEventConfigurationFixtures(): iterable
    {
        yield 'underscored' => ['legacy_remote_event'];
        yield 'hyphenated' => ['legacy_hyphenated_remote_event'];
    }

    public function testWebhookConfigurationIsForwardedToWebhookBundle()
    {
        $container = $this->createContainer(['kernel.charset' => 'UTF-8', 'kernel.secret' => 'secret', 'kernel.runtime_environment' => 'test']);
        $container->registerExtension(new FrameworkExtension());
        $this->loadFromFile($container, 'legacy_webhook');
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([]);
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        $this->assertSame('sha512', $container->getDefinition('webhook.signer')->getArgument(0));
    }

    public function testSemaphoreConfigurationIsForwardedToSemaphoreBundle()
    {
        $container = $this->createContainer(['kernel.charset' => 'UTF-8', 'kernel.secret' => 'secret', 'kernel.runtime_environment' => 'test']);
        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension(new SemaphoreBundle()->getContainerExtension());
        $this->loadFromFile($container, 'legacy_semaphore');
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([]);
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        $this->assertTrue($container->hasDefinition('semaphore.default.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('semaphore.default.factory')->getArgument(0));
        $this->assertSame([SemaphoreStoreFactory::class, 'createStore'], $storeDef->getFactory());
        $this->assertSame('redis://localhost', $storeDef->getArgument(0));
    }

    public function testMessengerConfigurationIsForwardedToMessengerBundle()
    {
        $container = $this->createContainer(['kernel.charset' => 'UTF-8', 'kernel.secret' => 'secret', 'kernel.runtime_environment' => 'test']);
        $container->registerExtension(new FrameworkExtension());
        $this->loadFromFile($container, 'legacy_messenger');
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([]);
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        $this->assertTrue($container->hasDefinition('messenger.transport.async'));
        $this->assertSame('in-memory://', $container->getDefinition('messenger.transport.async')->getArgument(0));
        $this->assertSame('messenger.bus.default', (string) $container->getAlias('messenger.default_bus'));
    }

    public function testHtmlSanitizerConfigurationIsForwardedToHtmlSanitizerBundle()
    {
        $container = $this->createContainer(['kernel.charset' => 'UTF-8', 'kernel.secret' => 'secret', 'kernel.runtime_environment' => 'test']);
        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension(new HtmlSanitizerBundle()->getContainerExtension());
        $this->loadFromFile($container, 'legacy_html_sanitizer');
        $container->compile();

        $this->assertSame(HtmlSanitizer::class, $container->getDefinition('test_html_sanitizer')->getClass());
        $this->assertSame('custom', $container->getDefinition('test_html_sanitizer')->getTag('html_sanitizer')[0]['sanitizer']);
    }

    public function testTypeInfoConfigurationIsForwardedToTypeInfoBundle()
    {
        $container = $this->createContainer(['kernel.charset' => 'UTF-8', 'kernel.secret' => 'secret', 'kernel.runtime_environment' => 'test']);
        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension(new TypeInfoBundle()->getContainerExtension());
        $this->loadFromFile($container, 'legacy_type_info');
        $container->compile();

        $this->assertSame(['CustomAlias' => 'int'], $container->getDefinition('test_type_info_context_factory')->getArgument(1));
    }

    public function testUidConfigurationIsForwardedToUidBundle()
    {
        $container = $this->createContainer(['kernel.charset' => 'UTF-8', 'kernel.secret' => 'secret', 'kernel.runtime_environment' => 'test']);
        $container->registerExtension(new FrameworkExtension());
        $bundle = new UidBundle();
        $bundle->build($container);
        $container->registerExtension($bundle->getContainerExtension());
        $this->loadFromFile($container, 'legacy_uid');
        $container->compile();

        $definition = $container->getDefinition('test_uuid_factory');
        $this->assertSame(UuidFactory::class, $definition->getClass());
        $this->assertSame(6, $definition->getArgument(0));
        $this->assertSame('73902feb-9b95-4fe5-9c6f-b3e6d29e77b5', $definition->getArgument(5));

        $this->assertSame(Uuid47Transformer::class, $container->getDefinition('test_uuid47_transformer')->getClass());
    }

    public function testLockConfigurationIsForwardedToLockBundle()
    {
        $container = $this->createContainerFromFile('legacy_lock');

        $this->assertTrue($container->hasDefinition('lock.default.factory'));
        $this->assertSame('.lock.flock.store', (string) $container->getDefinition('lock.default.factory')->getArgument(0));
        $this->assertSame('lock.default.factory', (string) $container->getAlias('lock.factory'));
    }

    public function testEnabledPhpErrorsConfig()
    {
        $container = $this->createContainerFromFile('php_errors_enabled');

        $definition = $container->getDefinition('debug.error_handler_configurator');
        $this->assertEquals(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $definition->getArgument(0));
        $this->assertNull($definition->getArgument(1));
        $this->assertSame(-1, $container->getParameter('debug.error_handler.throw_at'));
    }

    public function testDisabledPhpErrorsConfig()
    {
        $container = $this->createContainerFromFile('php_errors_disabled');

        $definition = $container->getDefinition('debug.error_handler_configurator');
        $this->assertNull($definition->getArgument(0));
        $this->assertNull($definition->getArgument(1));
        $this->assertSame(0, $container->getParameter('debug.error_handler.throw_at'));
    }

    public function testPhpErrorsWithLogLevel()
    {
        $container = $this->createContainerFromFile('php_errors_log_level');

        $definition = $container->getDefinition('debug.error_handler_configurator');
        $this->assertEquals(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $definition->getArgument(0));
        $this->assertSame(8, $definition->getArgument(1));
    }

    public function testPhpErrorsWithLogLevels()
    {
        $container = $this->createContainerFromFile('php_errors_log_levels');

        $definition = $container->getDefinition('debug.error_handler_configurator');
        $this->assertEquals(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $definition->getArgument(0));
        $this->assertSame([
            \E_NOTICE => LogLevel::ERROR,
            \E_WARNING => LogLevel::ERROR,
        ], $definition->getArgument(1));
    }

    public function testExceptionsConfig()
    {
        $container = $this->createContainerFromFile('exceptions');

        $configuration = $container->getDefinition('exception_listener')->getArgument(3);

        $this->assertSame([
            BadRequestHttpException::class,
            NotFoundHttpException::class,
            ConflictHttpException::class,
            ServiceUnavailableHttpException::class,
        ], array_keys($configuration));

        $this->assertEqualsCanonicalizing([
            'log_channel' => null,
            'log_level' => 'info',
            'status_code' => 422,
        ], $configuration[BadRequestHttpException::class]);

        $this->assertEqualsCanonicalizing([
            'log_channel' => null,
            'log_level' => 'info',
            'status_code' => null,
        ], $configuration[NotFoundHttpException::class]);

        $this->assertEqualsCanonicalizing([
            'log_channel' => null,
            'log_level' => 'info',
            'status_code' => null,
        ], $configuration[ConflictHttpException::class]);

        $this->assertEqualsCanonicalizing([
            'log_channel' => null,
            'log_level' => null,
            'status_code' => 500,
        ], $configuration[ServiceUnavailableHttpException::class]);
    }

    public function testRouter()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->has('router'), '->registerRouterConfiguration() loads routing.xml');
        $arguments = $container->findDefinition('router')->getArguments();
        $this->assertEquals($container->getParameter('kernel.project_dir').'/config/routing.xml', $container->getParameter('router.resource'), '->registerRouterConfiguration() sets routing resource');
        $this->assertEquals('%router.resource%', $arguments[1], '->registerRouterConfiguration() sets routing resource');
        $this->assertEquals('xml', $arguments[2]['resource_type'], '->registerRouterConfiguration() sets routing resource type');

        $this->assertSame(['_locale' => 'fr|en'], $container->getDefinition('routing.loader')->getArgument(2));
    }

    public function testRouterRequestContextInlinesHostAndScheme()
    {
        $container = $this->createContainerFromFile('full');

        // The host and scheme are inlined as plain values instead of being read through
        // ParameterBag::all() at runtime, which would eagerly resolve every env var and
        // fail during cache warmup when one of them is missing.
        $requestContext = $container->getDefinition('router.request_context');
        $this->assertSame('localhost', $requestContext->getArgument(1));
        $this->assertSame('http', $requestContext->getArgument(2));
    }

    public function testRouterRequestContextUsesHostAndSchemeParameters()
    {
        $container = $this->createContainerFromClosure(static function ($container) {
            $container->setParameter('router.request_context.host', 'example.com');
            $container->setParameter('router.request_context.scheme', 'https');
            $container->loadFromExtension('framework', [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'router' => ['resource' => '%kernel.project_dir%/config/routing.xml'],
            ]);
        });

        $requestContext = $container->getDefinition('router.request_context');
        $this->assertSame('example.com', $requestContext->getArgument(1));
        $this->assertSame('https', $requestContext->getArgument(2));
    }

    public function testRouterEnabledLocalesWithEnvPlaceholders()
    {
        $container = $this->createContainerFromFile('router_enabled_locales_env');
        $requirements = $container->getDefinition('routing.loader')->getArgument(2);

        $this->assertIsArray($requirements);
        $this->assertArrayHasKey('_locale', $requirements);

        $requirementDefinition = $requirements['_locale'];
        $this->assertInstanceOf(Definition::class, $requirementDefinition);
        $this->assertSame('implode', $requirementDefinition->getFactory());

        $this->assertSame('|', $requirementDefinition->getArgument(0));

        $arrayMap = $requirementDefinition->getArgument(1);
        $this->assertInstanceOf(Definition::class, $arrayMap);
        $this->assertSame('array_map', $arrayMap->getFactory());
        $this->assertSame('preg_quote', $arrayMap->getArgument(0));
    }

    public function testRouterRequiresResourceOption()
    {
        $container = $this->createContainer();
        $loader = new FrameworkExtension();

        $this->expectException(InvalidConfigurationException::class);

        $loader->load([['router' => true]], $container);
    }

    public function testSession()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertEquals('fr', $container->getParameter('kernel.default_locale'));
        $this->assertEquals('session.storage.factory.native', (string) $container->getAlias('session.storage.factory'));
        $this->assertEquals('session.handler.native_file', (string) $container->getAlias('session.handler'));

        $options = $container->getParameter('session.storage.options');
        $this->assertEquals('_SYMFONY', $options['name']);
        $this->assertEquals(86400, $options['cookie_lifetime']);
        $this->assertEquals('/', $options['cookie_path']);
        $this->assertEquals('example.com', $options['cookie_domain']);
        $this->assertTrue($options['cookie_secure']);
        $this->assertFalse($options['cookie_httponly']);
        $this->assertTrue($options['use_cookies']);
        $this->assertEquals(108, $options['gc_divisor']);
        $this->assertEquals(1, $options['gc_probability']);
        $this->assertEquals(90000, $options['gc_maxlifetime']);

        $this->assertEquals('/path/to/sessions', $container->getParameter('session.save_path'));
    }

    public function testNullSessionHandler()
    {
        $container = $this->createContainerFromFile('session');

        $this->assertNull($container->getParameter('session.save_path'));
        $this->assertSame('session.handler.native', (string) $container->getAlias('session.handler'));

        $expected = ['session_factory', 'logger', 'session_collector', 'request_stack'];
        $this->assertEquals($expected, array_keys($container->getDefinition('session_listener')->getArgument(0)->getValues()));
        $this->assertFalse($container->getDefinition('session.storage.factory.native')->getArgument(3));
    }

    public function testRequest()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('request.add_request_formats_listener'), '->registerRequestConfiguration() loads request.xml');
        $listenerDef = $container->getDefinition('request.add_request_formats_listener');
        $this->assertEquals(['csv' => ['text/csv', 'text/plain'], 'pdf' => ['application/pdf']], $listenerDef->getArgument(0));
    }

    public function testEmptyRequestFormats()
    {
        $container = $this->createContainerFromFile('request');

        $this->assertFalse($container->hasDefinition('request.add_request_formats_listener'), '->registerRequestConfiguration() does not load request.xml when no request formats are defined');
    }

    public function testAssets()
    {
        $container = $this->createContainerFromFile('assets');
        $packages = $container->getDefinition('assets.packages');

        // default package
        $defaultPackage = $container->getDefinition((string) $packages->getArgument(0));
        $this->assertUrlPackage($container, $defaultPackage, ['http://cdn.example.com'], 'SomeVersionScheme', '%%s?version=%%s');

        // packages
        $packageTags = $container->findTaggedServiceIds('assets.package');
        $this->assertCount(10, $packageTags);

        $packages = [];
        foreach ($packageTags as $serviceId => $tagAttributes) {
            $packages[$tagAttributes[0]['package']] = $serviceId;
        }

        $package = $container->getDefinition((string) $packages['images_path']);
        $this->assertPathPackage($container, $package, '/foo', 'SomeVersionScheme', '%%s?version=%%s');

        $package = $container->getDefinition((string) $packages['images']);
        $this->assertUrlPackage($container, $package, ['http://images1.example.com', 'http://images2.example.com'], '1.0.0', '%%s?version=%%s');

        $package = $container->getDefinition((string) $packages['foo']);
        $this->assertPathPackage($container, $package, '', '1.0.0', '%%s-%%s');

        $package = $container->getDefinition((string) $packages['bar']);
        $this->assertUrlPackage($container, $package, ['https://bar2.example.com'], 'SomeVersionScheme', '%%s?version=%%s');

        $package = $container->getDefinition((string) $packages['bar_version_strategy']);
        $this->assertEquals('assets.custom_version_strategy', (string) $package->getArgument(1));

        $package = $container->getDefinition((string) $packages['json_manifest_strategy']);
        $versionStrategy = $container->getDefinition((string) $package->getArgument(1));
        $this->assertEquals('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        $this->assertEquals('/path/to/manifest.json', $versionStrategy->getArgument(0));
        $this->assertFalse($versionStrategy->getArgument(2));

        $package = $container->getDefinition($packages['remote_manifest']);
        $versionStrategy = $container->getDefinition($package->getArgument(1));
        $this->assertSame('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        $this->assertSame('https://cdn.example.com/manifest.json', $versionStrategy->getArgument(0));

        $package = $container->getDefinition($packages['var_manifest']);
        $versionStrategy = $container->getDefinition($package->getArgument(1));
        $this->assertSame('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        $this->assertSame('https://cdn.example.com/manifest.json', $versionStrategy->getArgument(0));
        $this->assertFalse($versionStrategy->getArgument(2));

        $package = $container->getDefinition($packages['env_manifest']);
        $versionStrategy = $container->getDefinition($package->getArgument(1));
        $this->assertSame('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        $this->assertStringMatchesFormat('env_%s', $versionStrategy->getArgument(0));
        $this->assertFalse($versionStrategy->getArgument(2));

        $package = $container->getDefinition((string) $packages['strict_manifest_strategy']);
        $versionStrategy = $container->getDefinition((string) $package->getArgument(1));
        $this->assertEquals('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        $this->assertEquals('/path/to/manifest.json', $versionStrategy->getArgument(0));
        $this->assertTrue($versionStrategy->getArgument(2));
    }

    public function testAssetsDefaultVersionStrategyAsService()
    {
        $container = $this->createContainerFromFile('assets_version_strategy_as_service');
        $packages = $container->getDefinition('assets.packages');

        // default package
        $defaultPackage = $container->getDefinition((string) $packages->getArgument(0));
        $this->assertEquals('assets.custom_version_strategy', (string) $defaultPackage->getArgument(1));
    }

    public function testFormDataClassAttributeAutoconfiguration()
    {
        $container = $this->createContainerFromFile('full', [], true, false);
        $container->compile();

        $configurators = $container->getAttributeAutoconfigurators()[AsFormType::class] ?? [];
        $this->assertCount(1, $configurators);

        $definition = new ChildDefinition('');
        $configurators[0]($definition);

        $this->assertSame([[]], $definition->getTag('form.data_class'));
        $this->assertCount(1, $definition->getTag('container.excluded'));
    }

    public function testDoctrineMappedClassAttributesAreForwardedToTheTag()
    {
        $container = $this->createContainerFromFile('default_config', [], true, false);
        $container->compile();

        foreach ([Entity::class, MappedSuperclass::class] as $attribute) {
            $configurators = $container->getAttributeAutoconfigurators()[$attribute] ?? [];
            $this->assertCount(1, $configurators);

            $definition = new ChildDefinition('');
            $configurators[0]($definition);

            $this->assertCount(1, $definition->getTag('container.excluded'));
            $this->assertSame([[]], $definition->getTag('doctrine.orm.entity'));
        }
    }

    public function testTranslator()
    {
        $container = $this->createContainerFromFile('full');
        $this->assertTrue($container->hasDefinition('translator.default'), '->registerTranslatorConfiguration() loads translation.php');
        $this->assertEquals('translator.default', (string) $container->getAlias('translator'), '->registerTranslatorConfiguration() redefines translator service from identity to real translator');
        $options = $container->getDefinition('translator.default')->getArgument(4);

        $this->assertArrayHasKey('cache_dir', $options);
        $this->assertSame($container->getParameter('kernel.cache_dir').'/translations', $options['cache_dir']);

        $files = array_map('realpath', $options['resource_files']['en']);
        $ref = new \ReflectionClass(Validation::class);
        $this->assertContains(
            strtr(\dirname($ref->getFileName()).'/Resources/translations/validators.en.xlf', '/', \DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds Validator translation resources'
        );
        $ref = new \ReflectionClass(Form::class);
        $this->assertContains(
            strtr(\dirname($ref->getFileName()).'/Resources/translations/validators.en.xlf', '/', \DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds Form translation resources'
        );
        $ref = new \ReflectionClass(AuthenticationEvents::class);
        $this->assertContains(
            strtr(\dirname($ref->getFileName()).'/Resources/translations/security.en.xlf', '/', \DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds Security translation resources'
        );
        $this->assertContains(
            strtr(__DIR__.'/Fixtures/translations/test_paths.en.yml', '/', \DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds translation resources in custom paths'
        );
        $this->assertContains(
            strtr(__DIR__.'/translations/test_default.en.xlf', '/', \DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds translation resources in default path'
        );
        $this->assertContains(
            strtr(__DIR__.'/Fixtures/translations/domain.with.dots.en.yml', '/', \DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds translation resources with dots in domain'
        );
        $this->assertContains(strtr(__DIR__.'/translations/security.en.yaml', '/', \DIRECTORY_SEPARATOR), $files);

        $positionOverridingTranslationFile = array_search(strtr(realpath(__DIR__.'/translations/security.en.yaml'), '/', \DIRECTORY_SEPARATOR), $files);

        if (false !== $positionCoreTranslationFile = array_search(strtr(realpath(__DIR__.'/../../../../Component/Security/Core/Resources/translations/security.en.xlf'), '/', \DIRECTORY_SEPARATOR), $files)) {
            $this->assertContains(strtr(realpath(__DIR__.'/../../../../Component/Security/Core/Resources/translations/security.en.xlf'), '/', \DIRECTORY_SEPARATOR), $files);
        } else {
            $this->assertContains(strtr(realpath(__DIR__.'/../../vendor/symfony/security-core/Resources/translations/security.en.xlf'), '/', \DIRECTORY_SEPARATOR), $files);

            $positionCoreTranslationFile = array_search(strtr(realpath(__DIR__.'/../../vendor/symfony/security-core/Resources/translations/security.en.xlf'), '/', \DIRECTORY_SEPARATOR), $files);
        }

        $this->assertGreaterThan($positionCoreTranslationFile, $positionOverridingTranslationFile);

        $calls = $container->getDefinition('translator.default')->getMethodCalls();
        $this->assertEquals(['fr'], $calls[1][1][0]);

        $nonExistingDirectories = array_filter(
            $options['scanned_directories'],
            static fn ($directory) => !file_exists($directory)
        );

        $this->assertNotEmpty($nonExistingDirectories, 'FrameworkBundle should pass non existing directories to Translator');

        $this->assertSame('Fixtures/translations', $options['cache_vary']['scanned_directories'][3]);

        if (class_exists(XliffUpdateSourcesCommand::class)) {
            $this->assertSame(
                [__DIR__.'/Fixtures/translations', __DIR__.'/translations'],
                $container->getParameterBag()->resolveValue($container->getDefinition('console.command.translation_xliff_update_sources')->getArgument(3)),
                '->registerTranslatorConfiguration() passes only app-owned paths to the XLIFF source updater'
            );
        }
    }

    public function testTranslatorProvidersMergedEnabledLocales()
    {
        $container = $this->createContainerFromFile('translator_providers');
        $this->assertSame(['es', 'en', 'fr', 'de', 'pl'], $container->getDefinition('console.command.translation_pull')->getArgument(5));
        $this->assertSame(['es', 'en', 'fr', 'de', 'pl'], $container->getDefinition('console.command.translation_push')->getArgument(3));
    }

    public function testTranslatorMultipleFallbacks()
    {
        $container = $this->createContainerFromFile('translator_fallbacks');

        $calls = $container->getDefinition('translator.default')->getMethodCalls();
        $this->assertEquals(['en', 'fr'], $calls[1][1][0]);
    }

    public function testTranslatorCacheDirDisabled()
    {
        $container = $this->createContainerFromFile('translator_cache_dir_disabled');
        $options = $container->getDefinition('translator.default')->getArgument(4);
        $this->assertNull($options['cache_dir']);
    }

    public function testTranslatorGlobals()
    {
        $container = $this->createContainerFromFile('translator_globals');

        $calls = $container->getDefinition('translator.default')->getMethodCalls();

        $this->assertCount(5, $calls);
        $this->assertSame(
            ['addGlobalParameter', ['%%app_name%%', 'My application']],
            $calls[2],
        );
        $this->assertSame(
            ['addGlobalParameter', ['{app_version}', '1.2.3']],
            $calls[3],
        );
        $this->assertEquals(
            ['addGlobalParameter', ['{url}', new Definition(TranslatableMessage::class, ['url', ['scheme' => 'https://'], 'global'])]],
            $calls[4],
        );
    }

    public function testTranslatorWithoutGlobals()
    {
        $container = $this->createContainerFromFile('translator_without_globals');

        $calls = $container->getDefinition('translator.default')->getMethodCalls();

        $this->assertCount(2, $calls);
    }

    public function testValidation()
    {
        $container = $this->createContainerFromFile('full');
        $projectDir = $container->getParameter('kernel.project_dir');

        $ref = new \ReflectionClass(Form::class);
        $xmlMappings = [];
        if (!$ref->getAttributes(Traverse::class)) {
            $xmlMappings[] = \dirname($ref->getFileName()).'/Resources/config/validation.xml';
        }
        $xmlMappings[] = strtr($projectDir.'/config/validator/foo.xml', '/', \DIRECTORY_SEPARATOR);

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $attributes = !class_exists(FullStack::class);

        $this->assertCount($attributes ? 8 : 7, $calls);
        $this->assertSame('setConstraintValidatorFactory', $calls[0][0]);
        $this->assertEquals([new Reference('validator.validator_factory')], $calls[0][1]);
        $this->assertSame('setGroupProviderLocator', $calls[1][0]);
        $this->assertInstanceOf(ServiceLocatorArgument::class, $calls[1][1][0]);
        $this->assertSame('setTranslator', $calls[2][0]);
        $this->assertEquals([new Reference('translator', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE)], $calls[2][1]);
        $this->assertSame('setTranslationDomain', $calls[3][0]);
        $this->assertSame(['%validator.translation_domain%'], $calls[3][1]);
        $this->assertSame('addXmlMappings', $calls[4][0]);
        $this->assertSame([$xmlMappings], $calls[4][1]);
        $i = 4;
        if ($attributes) {
            $this->assertSame('enableAttributeMapping', $calls[++$i][0]);
        }
        $this->assertSame('addMethodMapping', $calls[++$i][0]);
        $this->assertSame(['loadValidatorMetadata'], $calls[$i][1]);
        $this->assertSame('setMappingCache', $calls[++$i][0]);
        $this->assertEquals([new Reference('validator.mapping.cache.adapter')], $calls[$i][1]);
    }

    public function testValidationService()
    {
        $container = $this->createContainerFromFile('validation_attributes', ['kernel.charset' => 'UTF-8'], false);

        $this->assertInstanceOf(ValidatorInterface::class, $container->get('validator.alias'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testFileLinkFormat()
    {
        if (\ini_get('xdebug.file_link_format') || get_cfg_var('xdebug.file_link_format')) {
            $this->markTestSkipped('A custom file_link_format is defined.');
        }

        $this->expectUserDeprecationMessage('Since symfony/framework-bundle 8.2: Setting the "framework.ide" configuration option is deprecated, use the "SYMFONY_IDE" env var instead.');

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'ide' => 'file%%link%%format',
            ]);
        });

        $this->assertEquals('file%link%format', $container->getParameter('debug.file_link_format'));
    }

    public function testValidationAttributes()
    {
        $container = $this->createContainerFromFile('validation_attributes');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $this->assertCount(8, $calls);
        $this->assertSame('enableAttributeMapping', $calls[5][0]);
        $this->assertSame('addMethodMapping', $calls[6][0]);
        $this->assertSame(['loadValidatorMetadata'], $calls[6][1]);
        $this->assertSame('setMappingCache', $calls[7][0]);
        $this->assertEquals([new Reference('validator.mapping.cache.adapter')], $calls[7][1]);
        // no cache this time
    }

    public function testValidationPaths()
    {
        require_once __DIR__.'/Fixtures/TestBundle/TestBundle.php';

        $container = $this->createContainerFromFile('validation_attributes', [
            'kernel.bundles' => ['TestBundle' => 'Symfony\\Bundle\\FrameworkBundle\\Tests\\TestBundle'],
            'kernel.bundles_metadata' => ['TestBundle' => ['namespace' => 'Symfony\\Bundle\\FrameworkBundle\\Tests', 'path' => __DIR__.'/Fixtures/TestBundle']],
        ]);

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $this->assertCount(9, $calls);
        $this->assertSame('addXmlMappings', $calls[4][0]);
        $this->assertSame('addYamlMappings', $calls[5][0]);
        $this->assertSame('enableAttributeMapping', $calls[6][0]);
        $this->assertSame('addMethodMapping', $calls[7][0]);
        $this->assertSame(['loadValidatorMetadata'], $calls[7][1]);
        $this->assertSame('setMappingCache', $calls[8][0]);
        $this->assertEquals([new Reference('validator.mapping.cache.adapter')], $calls[8][1]);

        $xmlMappings = $calls[4][1][0];

        if (!(new \ReflectionClass(Form::class))->getAttributes(Traverse::class)) {
            try {
                // Testing symfony/symfony
                $this->assertStringEndsWith('Component'.\DIRECTORY_SEPARATOR.'Form/Resources/config/validation.xml', $xmlMappings[0]);
            } catch (\Exception $e) {
                // Testing symfony/framework-bundle with deps=high
                $this->assertStringEndsWith('symfony'.\DIRECTORY_SEPARATOR.'form/Resources/config/validation.xml', $xmlMappings[0]);
            }
            array_shift($xmlMappings);
        }
        $this->assertCount(2, $xmlMappings);
        $this->assertStringEndsWith('TestBundle/Resources/config/validation.xml', $xmlMappings[0]);

        $yamlMappings = $calls[5][1][0];
        $this->assertCount(1, $yamlMappings);
        $this->assertStringEndsWith('TestBundle/Resources/config/validation.yml', $yamlMappings[0]);
    }

    public function testValidationPathsUsingCustomBundlePath()
    {
        require_once __DIR__.'/Fixtures/CustomPathBundle/src/CustomPathBundle.php';

        $container = $this->createContainerFromFile('validation_attributes', [
            'kernel.bundles' => ['CustomPathBundle' => 'Symfony\\Bundle\\FrameworkBundle\\Tests\\CustomPathBundle'],
            'kernel.bundles_metadata' => ['TestBundle' => ['namespace' => 'Symfony\\Bundle\\FrameworkBundle\\Tests', 'path' => __DIR__.'/Fixtures/CustomPathBundle']],
        ]);

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();
        $xmlMappings = $calls[4][1][0];

        if (!(new \ReflectionClass(Form::class))->getAttributes(Traverse::class)) {
            try {
                // Testing symfony/symfony
                $this->assertStringEndsWith('Component'.\DIRECTORY_SEPARATOR.'Form/Resources/config/validation.xml', $xmlMappings[0]);
            } catch (\Exception $e) {
                // Testing symfony/framework-bundle with deps=high
                $this->assertStringEndsWith('symfony'.\DIRECTORY_SEPARATOR.'form/Resources/config/validation.xml', $xmlMappings[0]);
            }
            array_shift($xmlMappings);
        }
        $this->assertCount(2, $xmlMappings);
        $this->assertStringEndsWith('CustomPathBundle/Resources/config/validation.xml', $xmlMappings[0]);

        $yamlMappings = $calls[5][1][0];
        $this->assertCount(1, $yamlMappings);
        $this->assertStringEndsWith('CustomPathBundle/Resources/config/validation.yml', $yamlMappings[0]);
    }

    public function testValidationNoStaticMethod()
    {
        $container = $this->createContainerFromFile('validation_no_static_method');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $attributes = !class_exists(FullStack::class);

        $this->assertCount($attributes ? 7 : 6, $calls);
        $this->assertSame('addXmlMappings', $calls[4][0]);
        $i = 4;
        if ($attributes) {
            $this->assertSame('enableAttributeMapping', $calls[++$i][0]);
        }
        $this->assertSame('setMappingCache', $calls[++$i][0]);
        $this->assertEquals([new Reference('validator.mapping.cache.adapter')], $calls[$i][1]);
        // no cache, no attributes, no static methods
    }

    public function testEmailValidationModeIsPassedToEmailValidator()
    {
        $container = $this->createContainerFromFile('validation_email_validation_mode');

        $this->assertSame('html5-allow-no-tld', $container->getDefinition('validator.email')->getArgument(0));
    }

    public function testValidationTranslationDomain()
    {
        $container = $this->createContainerFromFile('validation_translation_domain');

        $this->assertSame('messages', $container->getParameter('validator.translation_domain'));
    }

    public function testValidationPropertyMetadataExistenceCheck()
    {
        $container = $this->createContainerFromFile('validation_property_metadata_existence_check');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();
        $methods = array_column($calls, 0);

        $this->assertContains('enablePropertyMetadataExistenceCheck', $methods);
    }

    public function testValidationMapping()
    {
        $container = $this->createContainerFromFile('validation_mapping');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $this->assertSame('addXmlMappings', $calls[4][0]);

        $this->assertSame('addYamlMappings', $calls[5][0]);
        $this->assertCount(3, $calls[5][1][0]);
        $this->assertStringContainsString('foo.yml', $calls[5][1][0][0]);
        $this->assertStringContainsString('validation.yml', $calls[5][1][0][1]);
        $this->assertStringContainsString('validation.yaml', $calls[5][1][0][2]);
    }

    public function testValidationAutoMapping()
    {
        $container = $this->createContainerFromFile('validation_auto_mapping');
        $parameter = [
            'App\\' => ['services' => ['foo', 'bar']],
            'Symfony\\' => ['services' => ['a', 'b']],
            'Foo\\' => ['services' => []],
        ];

        $this->assertSame($parameter, $container->getParameter('validator.auto_mapping'));
        $this->assertTrue($container->hasDefinition('validator.property_info_loader'));
    }

    public function testFormsCanBeEnabledWithoutCsrfProtection()
    {
        $container = $this->createContainerFromFile('form_no_csrf');

        $this->assertFalse($container->getParameter('form.type_extension.csrf.enabled'));
    }

    public function testFormCsrfFieldAttr()
    {
        $container = $this->createContainerFromFile('form_csrf_field_attr');

        $expected = [
            'data-foo' => 'bar',
            'data-bar' => 'baz',
        ];
        $this->assertSame($expected, $container->getParameter('form.type_extension.csrf.field_attr'));
    }

    public function testStopwatchEnabledWithDebugModeEnabled()
    {
        $container = $this->createContainerFromFile('default_config', [
            'kernel.container_class' => 'foo',
            'kernel.debug' => true,
        ]);

        $this->assertTrue($container->has('debug.stopwatch'));
    }

    public function testStopwatchEnabledWithDebugModeDisabled()
    {
        $container = $this->createContainerFromFile('default_config', [
            'kernel.container_class' => 'foo',
        ]);

        $this->assertTrue($container->has('debug.stopwatch'));
    }

    public function testSerializerDisabled()
    {
        $container = $this->createContainerFromFile('default_config');
        $this->assertSame(!class_exists(FullStack::class) && class_exists(Serializer::class), $container->has('serializer'));
    }

    public function testSerializerEnabled()
    {
        $container = $this->createContainerFromFile('full');
        $this->assertTrue($container->has('serializer'));

        $argument = $container->getDefinition('serializer.mapping.chain_loader')->getArgument(0);

        $this->assertCount(2, $argument);
        $this->assertEquals(new Reference('serializer.mapping.attribute_loader'), $argument[0]);
        $this->assertEquals(new Reference('serializer.name_converter.camel_case_to_snake_case'), $container->getDefinition('serializer.name_converter.metadata_aware')->getArgument(1));
        $this->assertEquals(new Reference('property_info', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE), $container->getDefinition('serializer.normalizer.object')->getArgument(3));
    }

    public function testSerializerWithoutTranslator()
    {
        $container = $this->createContainerFromFile('serializer_without_translator');
        $this->assertFalse($container->hasDefinition('serializer.normalizer.translatable'));
    }

    public function testSerializerDefaultParameters()
    {
        $container = $this->createContainerFromFile('serializer_enabled');
        $this->assertFalse($container->hasParameter('.serializer.name_converter'));
        $this->assertFalse($container->hasParameter('serializer.default_context'));
        $this->assertTrue($container->hasParameter('.serializer.named_serializers'));
        $this->assertSame([], $container->getParameter('.serializer.named_serializers'));
    }

    public function testSerializerParametersAreSet()
    {
        $container = $this->createContainerFromFile('full');
        $this->assertTrue($container->hasParameter('.serializer.name_converter'));
        $this->assertSame('serializer.name_converter.camel_case_to_snake_case', $container->getParameter('.serializer.name_converter'));
        $this->assertTrue($container->hasParameter('serializer.default_context'));
        $this->assertSame(['enable_max_depth' => true], $container->getParameter('serializer.default_context'));
        $this->assertTrue($container->hasParameter('.serializer.named_serializers'));
        $this->assertSame(['api' => ['include_built_in_normalizers' => true, 'include_built_in_encoders' => true, 'default_context' => ['enable_max_depth' => false]]], $container->getParameter('.serializer.named_serializers'));
    }

    public function testRegisterSerializerExtractor()
    {
        $container = $this->createContainerFromFile('full');

        $serializerExtractorDefinition = $container->getDefinition('property_info.serializer_extractor');

        $this->assertEquals('serializer.mapping.class_metadata_factory', $serializerExtractorDefinition->getArgument(0)->__toString());
        $this->assertTrue($serializerExtractorDefinition->isPrivate());
        $tag = $serializerExtractorDefinition->getTag('property_info.list_extractor');
        $this->assertEquals(['priority' => -999], $tag[0]);
    }

    public function testDataUriNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.data_uri');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(DataUriNormalizer::class, $definition->getClass());
        $this->assertEquals(-920, $tag[0]['priority']);
    }

    public function testDateIntervalNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.dateinterval');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(DateIntervalNormalizer::class, $definition->getClass());
        $this->assertEquals(-915, $tag[0]['priority']);
    }

    public function testDateTimeNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.datetime');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(DateTimeNormalizer::class, $definition->getClass());
        $this->assertEquals(-910, $tag[0]['priority']);
    }

    public function testFormErrorNormalizerRegistred()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.form_error');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(FormErrorNormalizer::class, $definition->getClass());
        $this->assertEquals(-915, $tag[0]['priority']);
    }

    public function testJsonSerializableNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.json_serializable');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(JsonSerializableNormalizer::class, $definition->getClass());
        $this->assertEquals(-950, $tag[0]['priority']);
    }

    public function testObjectNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full', compile: false);
        $container->addCompilerPass(new SerializerPass());
        $container->addCompilerPass(new ResolveBindingsPass());
        $container->compile();

        $definition = $container->getDefinition('serializer.normalizer.object');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(ObjectNormalizer::class, $definition->getClass());
        $this->assertEquals(-1000, $tag[0]['priority']);

        $this->assertEquals([
            'enable_max_depth' => true,
            'circular_reference_handler' => new Reference('my.circular.reference.handler'),
            'max_depth_handler' => new Reference('my.max.depth.handler'),
        ], $definition->getArgument(6));
    }

    public function testConstraintViolationListNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.constraint_violation_list');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(ConstraintViolationListNormalizer::class, $definition->getClass());
        $this->assertEquals(-915, $tag[0]['priority']);
        $this->assertEquals(new Reference('serializer.name_converter.metadata_aware'), $definition->getArgument(1));
    }

    public function testTranslatableNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.translatable');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertSame(TranslatableNormalizer::class, $definition->getClass());
        $this->assertSame(-920, $tag[0]['priority']);
        $this->assertEquals(new Reference('translator'), $definition->getArgument('$translator'));
    }

    /**
     * @see https://github.com/symfony/symfony/issues/54478
     */
    public function testBackedEnumNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.backed_enum');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertSame(BackedEnumNormalizer::class, $definition->getClass());
        $this->assertSame(-915, $tag[0]['priority']);
    }

    public function testSerializerCacheActivated()
    {
        $container = $this->createContainerFromFile('serializer_enabled');

        $this->assertTrue($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));

        $cache = $container->getDefinition('serializer.mapping.cache_class_metadata_factory')->getArgument(1);
        $this->assertEquals(new Reference('serializer.mapping.cache.symfony'), $cache);
    }

    public function testSerializerCacheUsedWithoutAttributesAndMappingFiles()
    {
        $container = $this->createContainerFromFile('serializer_mapping_without_attributes', ['kernel.debug' => true, 'kernel.container_class' => __CLASS__]);
        $this->assertFalse($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));
    }

    public function testSerializerCacheUsedWithoutAttributesAndMappingFilesNoDebug()
    {
        $container = $this->createContainerFromFile('serializer_mapping_without_attributes', ['kernel.debug' => false, 'kernel.container_class' => __CLASS__]);
        $this->assertTrue($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));
    }

    public function testSerializerCacheNotActivatedWithAttributes()
    {
        $container = $this->createContainerFromFile('serializer_mapping', ['kernel.debug' => true, 'kernel.container_class' => __CLASS__]);
        $this->assertFalse($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));
    }

    public function testSerializerMapping()
    {
        $container = $this->createContainerFromFile('serializer_mapping_without_attributes', ['kernel.bundles_metadata' => ['TestBundle' => ['namespace' => 'Symfony\\Bundle\\FrameworkBundle\\Tests', 'path' => __DIR__.'/Fixtures/TestBundle']]]);
        $projectDir = $container->getParameter('kernel.project_dir');
        $configDir = __DIR__.'/Fixtures/TestBundle/Resources/config';
        $expectedLoaders = [
            new Reference('serializer.mapping.attribute_loader'),
            new Definition(XmlFileLoader::class, [$configDir.'/serialization.xml']),
            new Definition(YamlFileLoader::class, [$configDir.'/serialization.yml']),
            new Definition(YamlFileLoader::class, [$projectDir.'/config/serializer/foo.yml']),
            new Definition(XmlFileLoader::class, [$configDir.'/serializer_mapping/files/foo.xml']),
            new Definition(YamlFileLoader::class, [$configDir.'/serializer_mapping/files/foo.yml']),
            new Definition(YamlFileLoader::class, [$configDir.'/serializer_mapping/serialization.yml']),
            new Definition(YamlFileLoader::class, [$configDir.'/serializer_mapping/serialization.yaml']),
        ];

        foreach ($expectedLoaders as $loader) {
            if ($loader instanceof Definition && is_file($arg = $loader->getArgument(0))) {
                $loader->replaceArgument(0, strtr($arg, '/', \DIRECTORY_SEPARATOR));
            }
        }

        $loaders = $container->getDefinition('serializer.mapping.chain_loader')->getArgument(0);
        foreach ($loaders as $loader) {
            if ($loader instanceof Definition && is_file($arg = $loader->getArgument(0))) {
                $loader->replaceArgument(0, strtr($arg, '/', \DIRECTORY_SEPARATOR));
            }
        }
        $this->assertEquals($expectedLoaders, $loaders);
    }

    public function testSerializerServiceIsRegisteredWhenEnabled()
    {
        $container = $this->createContainerFromFile('serializer_enabled');

        $this->assertTrue($container->hasDefinition('serializer'));
    }

    public function testSerializerServiceIsNotRegisteredWhenDisabled()
    {
        $container = $this->createContainerFromFile('serializer_disabled');

        $this->assertFalse($container->hasDefinition('serializer'));
    }

    public function testPropertyInfoConfigurationIsForwardedToPropertyInfoBundle()
    {
        $container = $this->createContainerFromFile('legacy_property_info');

        $this->assertTrue($container->has('property_info'));
        $this->assertFalse($container->has('property_info.constructor_extractor'));
    }

    public function testEventDispatcherService()
    {
        $container = $this->createContainer(['kernel.charset' => 'UTF-8', 'kernel.secret' => 'secret']);
        $container->registerExtension(new FrameworkExtension());
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([new LoggerPass()]);
        $this->loadFromFile($container, 'default_config');
        $container
            ->register('foo', \stdClass::class)
            ->setPublic(true)
            ->setProperty('dispatcher', new Reference('event_dispatcher'));
        $container->compile();
        $this->assertInstanceOf(EventDispatcherInterface::class, $container->get('foo')->dispatcher);
    }

    public function testCacheConfigurationIsForwardedToCacheBundle()
    {
        $container = $this->createContainerFromFile('legacy_cache');

        $this->assertSame('my-app', $container->getParameter('cache.prefix.seed'));
        $this->assertSame('cache.adapter.array', $container->getDefinition('cache.app')->getParent());
        $this->assertSame('cache.app', (string) $container->getAlias('test_cache_app'));
    }

    #[DataProvider('provideSectionCachePools')]
    public function testTheSectionPoolsAreRegisteredAlongsideTheirSection(string $file, string $id)
    {
        $this->assertTrue($this->createContainerFromFile($file)->has($id));
        $this->assertFalse($this->createContainerFromFile('cache_pools_without_sections')->has($id));
    }

    public static function provideSectionCachePools(): iterable
    {
        yield ['full', 'cache.validator'];
        yield ['full', 'cache.serializer'];
        yield ['full', 'cache.property_info'];
        yield ['section_cache_pools', 'cache.messenger.restart_workers_signal'];
        yield ['section_cache_pools', 'cache.scheduler'];
        yield ['asset_mapper_without_assets', 'cache.asset_mapper'];
    }

    public function testCacheDefaultRedisProvider()
    {
        $container = $this->createContainerFromFile('cache');

        $redisUrl = 'redis://localhost';
        $providerId = '.cache_connection.'.ContainerBuilder::hash($redisUrl);

        $this->assertTrue($container->hasDefinition($providerId));

        $url = $container->getDefinition($providerId)->getArgument(0);

        $this->assertSame($redisUrl, $url);
    }

    public function testCacheDefaultProviderDeducesTheAdapterFromTheDsn()
    {
        $container = $this->createContainerFromFile('cache_default_provider');

        $pool = $container->getDefinition('cache.app');
        $this->assertSame([AbstractAdapter::class, 'createAdapter'], $pool->getFactory());
        $this->assertStringStartsWith('.cache_connection.', (string) $pool->getArgument(0));

        $connection = $container->getDefinition((string) $pool->getArgument(0));
        $this->assertSame([AbstractAdapter::class, 'createConnection'], $connection->getFactory());
        $this->assertStringContainsString('APP_CACHE_DSN', $connection->getArgument(0));

        // cache.system keeps its own adapter, the DSN only applies to cache.app
        $this->assertNull($container->getDefinition('cache.system')->getFactory());
    }

    public function testCachePoolProviderWithoutAdapterDeducesTheAdapterFromTheDsn()
    {
        $container = $this->createContainerFromFile('cache_default_provider');

        $pool = $container->getDefinition('my_pool');
        $this->assertSame([AbstractAdapter::class, 'createAdapter'], $pool->getFactory());

        $connection = $container->getDefinition((string) $pool->getArgument(0));
        $this->assertSame('memcached://localhost', $connection->getArgument(0));
    }

    public function testCacheDefaultMongodbProvider()
    {
        $container = $this->createContainerFromFile('cache_mongodb');

        foreach (['cache.adapter.mongodb', 'cache.adapter.mongodb_tag_aware'] as $id) {
            $this->assertTrue($container->hasDefinition($id), \sprintf('"%s" should be a service, not an alias.', $id));
            $this->assertSame('cache.default_mongodb_provider', $container->getDefinition($id)->getTag('cache.pool')[0]['provider']);
        }

        $dsn = 'mongodb://localhost:27017/db?collection_name=cache';
        $providerId = '.cache_connection.'.ContainerBuilder::hash($dsn);

        $this->assertTrue($container->hasDefinition($providerId));

        $connection = $container->getDefinition($providerId);
        $this->assertSame([AbstractAdapter::class, 'createConnection'], $connection->getFactory());
        $this->assertSame($dsn, $connection->getArgument(0));
    }

    public function testCacheMongodbPoolDeducesTheAdapterFromTheDsn()
    {
        $container = $this->createContainerFromFile('cache_mongodb');

        $pool = $container->getDefinition('my_mongodb_pool');
        $this->assertSame([AbstractAdapter::class, 'createAdapter'], $pool->getFactory());

        $connection = $container->getDefinition((string) $pool->getArgument(0));
        $this->assertSame('mongodb://localhost:27017/db?collection_name=pool', $connection->getArgument(0));
    }

    public function testCacheDefaultValkeyProvider()
    {
        $container = $this->createContainerFromFile('cache');

        foreach (['cache.adapter.valkey', 'cache.adapter.valkey_tag_aware'] as $id) {
            $this->assertTrue($container->hasDefinition($id), \sprintf('"%s" should be a service, not an alias.', $id));
            $this->assertSame('cache.default_valkey_provider', $container->getDefinition($id)->getTag('cache.pool')[0]['provider']);
        }

        $valkeyUrl = 'valkey://valkey-host';
        $providerId = '.cache_connection.'.ContainerBuilder::hash($valkeyUrl);

        $this->assertTrue($container->hasDefinition($providerId));
        $this->assertSame($valkeyUrl, $container->getDefinition($providerId)->getArgument(0));
    }

    public function testCachePoolServices()
    {
        $container = $this->createContainerFromFile('cache', [], true, false);
        $container->setParameter('cache.prefix.seed', 'test');
        $container->addCompilerPass(new CachePoolPass());
        $container->compile();

        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.foo', 'cache.adapter.apcu', 30);
        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.baz', 'cache.adapter.filesystem', 7);
        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.foobar', 'cache.adapter.psr6', 10);
        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.def', 'cache.app', 'PT11S');
        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.expr', 'cache.app', '13 seconds');

        $chain = $container->getDefinition('cache.chain');

        $this->assertSame(ChainAdapter::class, $chain->getClass());

        $this->assertCount(2, $chain->getArguments());
        $this->assertCount(3, $chain->getArguments()[0]);

        $expectedSeed = $chain->getArgument(0)[1]->getArgument(0);
        $expected = [
            [
                (new ChildDefinition('cache.adapter.array'))
                    ->replaceArgument(0, 12),
                (new ChildDefinition('cache.adapter.filesystem'))
                    ->replaceArgument(0, $expectedSeed)
                    ->replaceArgument(1, 12),
                (new ChildDefinition('cache.adapter.redis'))
                    ->replaceArgument(0, new Reference('.cache_connection.'.(\count((new \ReflectionMethod(ContainerConfigurator::class, 'extension'))->getParameters()) > 2 ? 'U5HliuY' : 'kYdiLgf')))
                    ->replaceArgument(1, $expectedSeed)
                    ->replaceArgument(2, 12),
            ],
            12,
        ];
        $this->assertEquals($expected, $chain->getArguments());

        // Test "tags: true" wrapping logic
        $tagAwareDefinition = $container->getDefinition('cache.ccc');
        $this->assertSame(TagAwareAdapter::class, $tagAwareDefinition->getClass());
        $this->assertCachePoolServiceDefinitionIsCreated($container, (string) $tagAwareDefinition->getArgument(0), 'cache.adapter.array', 410);

        $this->assertEquals([
            ['setLogger', [new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]],
        ], $tagAwareDefinition->getMethodCalls());
        $this->assertSame([['channel' => 'cache']], $tagAwareDefinition->getTag('monolog.logger'));
    }

    public function testRedisTagAwareAdapter()
    {
        $container = $this->createContainerFromFile('cache', [], true);

        $aliasesForArguments = [];
        $argNames = [
            'cacheRedisTagAwareFoo',
            'cacheRedisTagAwareFoo2',
            'cacheRedisTagAwareBar',
            'cacheRedisTagAwareBar2',
            'cacheRedisTagAwareBaz',
            'cacheRedisTagAwareBaz2',
        ];
        foreach ($argNames as $argumentName) {
            $aliasesForArguments[] = \sprintf('%s $%s', TagAwareCacheInterface::class, $argumentName);
            $aliasesForArguments[] = \sprintf('%s $%s', CacheInterface::class, $argumentName);
            $aliasesForArguments[] = \sprintf('%s $%s', CacheItemPoolInterface::class, $argumentName);
        }

        foreach ($aliasesForArguments as $aliasForArgumentStr) {
            $aliasForArgument = $container->getAlias($aliasForArgumentStr);
            $this->assertNotNull($aliasForArgument, \sprintf("No alias found for '%s'", $aliasForArgumentStr));

            $def = $container->getDefinition((string) $aliasForArgument);
            $this->assertInstanceOf(ChildDefinition::class, $def, \sprintf("No definition found for '%s'", $aliasForArgumentStr));

            $defParent = $container->getDefinition($def->getParent());
            if ($defParent instanceof ChildDefinition) {
                $defParent = $container->getDefinition($defParent->getParent());
            }

            $this->assertSame(RedisTagAwareAdapter::class, $defParent->getClass(), \sprintf("'%s' is not %s", $aliasForArgumentStr, RedisTagAwareAdapter::class));
        }
    }

    public function testPdoTagAwareAdapter()
    {
        $container = $this->createContainerFromFile('cache_pdo_tag_aware', [], true);

        $argNames = [
            'cachePdoTagAwareFoo',
            'cachePdoTagAwareFoo2',
            'cachePdoTagAwareBar',
            'cachePdoTagAwareBar2',
            'cachePdoTagAwareBaz',
            'cachePdoTagAwareBaz2',
        ];
        foreach ($argNames as $argumentName) {
            foreach ([TagAwareCacheInterface::class, CacheInterface::class, CacheItemPoolInterface::class] as $alias) {
                $aliasForArgumentStr = \sprintf('%s $%s', $alias, $argumentName);
                $aliasForArgument = $container->getAlias($aliasForArgumentStr);
                $this->assertNotNull($aliasForArgument, \sprintf("No alias found for '%s'", $aliasForArgumentStr));

                $def = $container->getDefinition((string) $aliasForArgument);
                $this->assertInstanceOf(ChildDefinition::class, $def, \sprintf("No definition found for '%s'", $aliasForArgumentStr));

                $defParent = $container->getDefinition($def->getParent());
                if ($defParent instanceof ChildDefinition) {
                    $defParent = $container->getDefinition($defParent->getParent());
                }

                $this->assertSame(PdoTagAwareAdapter::class, $defParent->getClass(), \sprintf("'%s' is not %s", $aliasForArgumentStr, PdoTagAwareAdapter::class));
            }
        }
    }

    #[DataProvider('appRedisTagAwareConfigProvider')]
    public function testAppRedisTagAwareAdapter(string $configFile)
    {
        $container = $this->createContainerFromFile($configFile);

        foreach ([TagAwareCacheInterface::class, CacheInterface::class, CacheItemPoolInterface::class] as $alias) {
            $def = $container->findDefinition($alias);

            while ($def instanceof ChildDefinition) {
                $def = $container->getDefinition($def->getParent());
            }

            $this->assertSame(RedisTagAwareAdapter::class, $def->getClass());
        }
    }

    public static function appRedisTagAwareConfigProvider(): array
    {
        return [
            ['cache_app_redis_tag_aware'],
            ['cache_app_redis_tag_aware_pool'],
        ];
    }

    public function testCacheTaggableTagAppliedToPools()
    {
        $container = $this->createContainerFromFile('cache');

        $servicesToCheck = [
            'cache.app.taggable' => 'cache.app',
            'cache.redis_tag_aware.bar' => 'cache.redis_tag_aware.bar',
            '.cache.foobar.taggable' => 'cache.foobar',
        ];

        foreach ($servicesToCheck as $id => $expectedPool) {
            $this->assertTrue($container->hasDefinition($id));

            $def = $container->getDefinition($id);

            $this->assertTrue($def->hasTag('cache.taggable'));
            $this->assertSame($expectedPool, $def->getTag('cache.taggable')[0]['pool'] ?? null);
        }
    }

    #[DataProvider('appRedisTagAwareConfigProvider')]
    public function testCacheTaggableTagAppliedToRedisAwareAppPool(string $configFile)
    {
        $container = $this->createContainerFromFile($configFile);

        $def = $container->getDefinition('cache.app');

        $this->assertTrue($def->hasTag('cache.taggable'));
        $this->assertSame('cache.app', $def->getTag('cache.taggable')[0]['pool'] ?? null);
    }

    public function testCachePoolInvalidateTagsCommandRegistered()
    {
        $container = $this->createContainerFromFile('cache');
        $this->assertTrue($container->hasDefinition('console.command.cache_pool_invalidate_tags'));

        $locator = $container->getDefinition('console.command.cache_pool_invalidate_tags')->getArgument(0);
        $this->assertInstanceOf(ServiceLocatorArgument::class, $locator);

        $iterator = $locator->getTaggedIteratorArgument();
        $this->assertInstanceOf(TaggedIteratorArgument::class, $iterator);

        $this->assertSame('cache.taggable', $iterator->getTag());
        $this->assertSame('pool', $iterator->getIndexAttribute());
        $this->assertTrue($iterator->needsIndexes());
    }

    public function testSessionCookieSecureAuto()
    {
        $container = $this->createContainerFromFile('session_cookie_secure_auto');

        $expected = ['session_factory', 'logger', 'session_collector', 'request_stack'];
        $this->assertEquals($expected, array_keys($container->getDefinition('session_listener')->getArgument(0)->getValues()));
    }

    public function testRobotsTagListenerIsRegisteredInDebugMode()
    {
        $container = $this->createContainer(['kernel.debug' => true]);
        (new FrameworkExtension())->load([], $container);
        $this->assertTrue($container->has('disallow_search_engine_index_response_listener'), 'DisallowRobotsIndexingListener should be registered');

        $definition = $container->getDefinition('disallow_search_engine_index_response_listener');
        $this->assertTrue($definition->hasTag('kernel.event_subscriber'), 'DisallowRobotsIndexingListener should have the correct tag');

        $container = $this->createContainer(['kernel.debug' => true]);
        (new FrameworkExtension())->load([['disallow_search_engine_index' => false]], $container);
        $this->assertFalse(
            $container->has('disallow_search_engine_index_response_listener'),
            'DisallowRobotsIndexingListener should not be registered when explicitly disabled'
        );

        $container = $this->createContainer(['kernel.debug' => false]);
        (new FrameworkExtension())->load([], $container);
        $this->assertFalse($container->has('disallow_search_engine_index_response_listener'), 'DisallowRobotsIndexingListener should NOT be registered');
    }

    public function testHttpClientDefaultOptions()
    {
        $container = $this->createContainerFromFile('http_client_default_options');
        $this->assertTrue($container->hasDefinition('http_client.transport'), '->registerHttpClientConfiguration() loads http_client.xml');

        $defaultOptions = [
            'headers' => [],
            'resolve' => [],
            'extra' => [],
        ];
        $this->assertSame([$defaultOptions, 4], $container->getDefinition('http_client.transport')->getArguments());

        $this->assertTrue($container->getDefinition('http_client')->hasTag('kernel.reset'));

        $this->assertTrue($container->hasDefinition('foo'), 'should have the "foo" service.');
        $definition = $container->getDefinition('foo');
        $this->assertSame(HttpClientInterface::class, $definition->getClass());
        $this->assertTrue($definition->hasTag('http_client.client'));
    }

    public function testScopedHttpClientWithoutQueryOption()
    {
        $container = $this->createContainerFromFile('http_client_scoped_without_query_option');

        $this->assertTrue($container->hasDefinition('foo'), 'should have the "foo" service.');
        $this->assertSame(HttpClientInterface::class, $container->getDefinition('foo')->getClass());
    }

    public function testHttpClientOverrideDefaultOptions()
    {
        $container = $this->createContainerFromFile('http_client_override_default_options');

        $this->assertSame(['foo' => 'bar'], $container->getDefinition('http_client.transport')->getArgument(0)['headers']);
        $this->assertSame(['foo' => 'bar'], $container->getDefinition('http_client.transport')->getArgument(0)['extra']);
        $this->assertSame(4, $container->getDefinition('http_client.transport')->getArgument(1));
        $this->assertSame('http://example.com', $container->getDefinition('foo.scoping')->getArgument(1));

        $expected = [
            'headers' => [
                'bar' => 'baz',
            ],
            'extra' => [
                'bar' => 'baz',
            ],
            'max_connect_duration' => 0.5,
            'query' => [],
            'resolve' => [],
        ];
        $this->assertEquals($expected, $container->getDefinition('foo.scoping')->getArgument(2));
    }

    public function testCachingHttpClient()
    {
        if (!class_exists(ChunkCacheItemNotFoundException::class)) {
            $this->expectException(LogicException::class);
        }

        $container = $this->createContainerFromFile('http_client_caching');

        $this->assertTrue($container->hasDefinition('http_client.caching'));
        $definition = $container->getDefinition('http_client.caching');
        $this->assertSame(CachingHttpClient::class, $definition->getClass());
        $this->assertSame('http_client', $definition->getDecoratedService()[0]);
        $this->assertCount(5, $arguments = $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('.inner', (string) $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('foo', (string) $arguments[1]);
        $this->assertArrayHasKey('headers', $arguments[2]);
        $this->assertSame(['X-powered' => 'PHP'], $arguments[2]['headers']);
        $this->assertFalse($arguments[3]);
        $this->assertSame(2, $arguments[4]);

        $this->assertTrue($container->hasDefinition('bar.caching'));
        $definition = $container->getDefinition('bar.caching');
        $this->assertSame(CachingHttpClient::class, $definition->getClass());
        $arguments = $definition->getArguments();
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('.inner', (string) $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('baz', (string) $arguments[1]);
        $scopedClient = $container->getDefinition('bar.scoping');

        $this->assertSame('.inner', (string) $scopedClient->getArgument(0));
        $this->assertSame('bar', $scopedClient->getDecoratedService()[0]);
    }

    public function testHttpClientRetry()
    {
        $container = $this->createContainerFromFile('http_client_retry');

        $this->assertSame([429, 500 => ['GET', 'HEAD']], $container->getDefinition('http_client.retry_strategy')->getArgument(0));
        $this->assertSame(100, $container->getDefinition('http_client.retry_strategy')->getArgument(1));
        $this->assertSame(2, $container->getDefinition('http_client.retry_strategy')->getArgument(2));
        $this->assertSame(0, $container->getDefinition('http_client.retry_strategy')->getArgument(3));
        $this->assertSame(0.3, $container->getDefinition('http_client.retry_strategy')->getArgument(4));
        $this->assertSame(2, $container->getDefinition('http_client.retryable')->getArgument(2));

        $this->assertSame(RetryableHttpClient::class, $container->getDefinition('foo.retryable')->getClass());
        $this->assertSame(4, $container->getDefinition('foo.retry_strategy')->getArgument(2));
    }

    public function testHttpClientWithQueryParameterKey()
    {
        $container = $this->createContainerFromFile('http_client_xml_key');

        $expected = [
            'key' => 'foo',
        ];
        $this->assertSame($expected, $container->getDefinition('foo.scoping')->getArgument(2)['query']);

        $expected = [
            'host' => '127.0.0.1',
        ];
        $this->assertSame($expected, $container->getDefinition('foo.scoping')->getArgument(2)['resolve']);
    }

    public function testHttpClientFullDefaultOptions()
    {
        $container = $this->createContainerFromFile('http_client_full_default_options');

        $defaultOptions = $container->getDefinition('http_client.transport')->getArgument(0);

        $this->assertSame(['X-powered' => 'PHP'], $defaultOptions['headers']);
        $this->assertSame(2, $defaultOptions['max_redirects']);
        $this->assertSame(2.0, (float) $defaultOptions['http_version']);
        $this->assertSame(['localhost' => '127.0.0.1'], $defaultOptions['resolve']);
        $this->assertSame('proxy.org', $defaultOptions['proxy']);
        $this->assertSame(3.5, $defaultOptions['timeout']);
        $this->assertSame(10.1, $defaultOptions['max_duration']);
        $this->assertSame(1.5, $defaultOptions['max_connect_duration']);
        $this->assertSame('127.0.0.1', $defaultOptions['bindto']);
        $this->assertTrue($defaultOptions['verify_peer']);
        $this->assertTrue($defaultOptions['verify_host']);
        $this->assertSame('/etc/ssl/cafile', $defaultOptions['cafile']);
        $this->assertSame('/etc/ssl', $defaultOptions['capath']);
        $this->assertSame('/etc/ssl/cert.pem', $defaultOptions['local_cert']);
        $this->assertSame('/etc/ssl/private_key.pem', $defaultOptions['local_pk']);
        $this->assertSame('password123456', $defaultOptions['passphrase']);
        $this->assertSame('RC4-SHA:TLS13-AES-128-GCM-SHA256', $defaultOptions['ciphers']);
        $this->assertSame([
            'pin-sha256' => ['14s5erg62v1v8471g2revg48r7==', 'jsda84hjtyd4821bgfesd215bsfg5412='],
            'md5' => 'sdhtb481248721thbr=',
        ], $defaultOptions['peer_fingerprint']);
        $this->assertSame(['foo' => ['bar' => 'baz']], $defaultOptions['extra']);
    }

    public function testHttpClientRateLimiter()
    {
        if (!class_exists(ThrottlingHttpClient::class)) {
            $this->expectException(LogicException::class);
        }

        $container = $this->createContainerFromFile('http_client_rate_limiter');

        $this->assertTrue($container->hasDefinition('http_client.throttling'));
        $definition = $container->getDefinition('http_client.throttling');
        $this->assertSame(ThrottlingHttpClient::class, $definition->getClass());
        $this->assertSame('http_client', $definition->getDecoratedService()[0]);
        $this->assertCount(2, $arguments = $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('.inner', (string) $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('http_client.throttling.limiter', (string) $arguments[1]);

        $this->assertTrue($container->hasDefinition('foo.throttling'));
        $definition = $container->getDefinition('foo.throttling');
        $this->assertSame(ThrottlingHttpClient::class, $definition->getClass());
        $this->assertSame('foo', $definition->getDecoratedService()[0]);
        $this->assertCount(2, $arguments = $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('.inner', (string) $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('foo.throttling.limiter', (string) $arguments[1]);
    }

    public function testRateLimiterAttributeListener()
    {
        $container = $this->createContainerFromFile('http_client_rate_limiter');

        $this->assertTrue($container->hasDefinition('rate_limiter.attribute_listener'));
        $definition = $container->getDefinition('rate_limiter.attribute_listener');
        $this->assertSame(RateLimitAttributeListener::class, $definition->getClass());
        $this->assertTrue($definition->hasTag('kernel.event_subscriber'));
    }

    public static function provideMailer(): iterable
    {
        yield [
            'mailer_with_dsn',
            ['main' => 'smtp://example.com'],
            ['redirected@example.org'],
            ['foobar@example\.org'],
        ];
        yield [
            'mailer_with_transports',
            [
                'transport1' => 'smtp://example1.com',
                'transport2' => 'smtp://example2.com',
            ],
            ['redirected@example.org', 'redirected1@example.org'],
            ['foobar@example\.org', '.*@example\.com'],
        ];
    }

    #[DataProvider('provideMailer')]
    public function testMailer(string $configFile, array $expectedTransports, array $expectedRecipients, array $expectedAllowedRecipients)
    {
        $container = $this->createContainerFromFile($configFile);

        $this->assertTrue($container->hasAlias('mailer'));
        $this->assertTrue($container->hasDefinition('mailer.transports'));
        $this->assertSame($expectedTransports, $container->getDefinition('mailer.transports')->getArgument(0));
        $this->assertTrue($container->hasAlias('mailer.default_transport'));
        $this->assertTrue($container->hasDefinition('mailer.envelope_listener'));
        $l = $container->getDefinition('mailer.envelope_listener');
        $this->assertSame('sender@example.org', $l->getArgument(0));
        $this->assertSame($expectedRecipients, $l->getArgument(1));
        $this->assertSame($expectedAllowedRecipients, $l->getArgument(2));
        $this->assertEquals(new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition('mailer.mailer')->getArgument(1));

        $this->assertTrue($container->hasDefinition('mailer.message_listener'));
        $l = $container->getDefinition('mailer.message_listener');
        $h = $l->getArgument(0);
        $this->assertCount(3, $h->getMethodCalls());
    }

    public function testMailerWithTracking()
    {
        if (!class_exists(TrackingHeader::class)) {
            $this->markTestSkipped('This test requires symfony/mailer 8.2 or superior.');
        }

        $container = $this->createContainerFromFile('mailer_with_tracking');

        $l = $container->getDefinition('mailer.message_listener');
        $calls = $l->getArgument(0)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('add', $calls[0][0]);
        $header = $calls[0][1][0];
        $this->assertSame(TrackingHeader::class, $header->getClass());
        $this->assertSame([false, false], $header->getArguments());
    }

    public function testMailerTrackingYieldsToAnExplicitTrackingHeader()
    {
        if (!class_exists(TrackingHeader::class)) {
            $this->markTestSkipped('This test requires symfony/mailer 8.2 or superior.');
        }

        $container = $this->createContainerFromFile('mailer_with_tracking_and_header');

        $l = $container->getDefinition('mailer.message_listener');
        $calls = $l->getArgument(0)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addHeader', $calls[0][0]);
        $this->assertSame(['X-Track', 'opens=true; clicks=default'], $calls[0][1]);
    }

    public function testMailerSmimeEncrypterWithCertificates()
    {
        $container = $this->createContainerFromFile('mailer_with_smime_certificates');

        $this->assertTrue($container->hasDefinition('mailer.smime_encrypter.repository'));
        $definition = $container->getDefinition('mailer.smime_encrypter.repository');
        $this->assertSame(InMemorySmimeCertificateRepository::class, $definition->getClass());
        $this->assertSame([
            'r1@example.com' => '/path/to/r1.crt',
            'r2@example.com' => '/path/to/r2.crt',
        ], $definition->getArgument(0));

        $listener = $container->getDefinition('mailer.smime_encrypter.listener');
        $this->assertSame('fail', $listener->getArgument(2));
        $this->assertTrue($listener->getArgument(3));
    }

    public function testMailerSmimeEncrypterDefaultsToTheDeprecatedBehaviorAndNoSenderEncryption()
    {
        $container = $this->createContainerFromFile('mailer_with_smime_repository');

        $this->assertTrue($container->hasAlias('mailer.smime_encrypter.repository'));
        $this->assertSame('my_repository', (string) $container->getAlias('mailer.smime_encrypter.repository'));

        $listener = $container->getDefinition('mailer.smime_encrypter.listener');
        $this->assertSame('send_unencrypted', $listener->getArgument(2));
        $this->assertFalse($listener->getArgument(3));
    }

    public function testMailerSmimeEncrypterRejectsBothRepositoryAndCertificates()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You cannot use both "smime_encrypter.repository" and "smime_encrypter.certificates" at the same time.');

        $this->createContainerFromFile('mailer_with_smime_repository_and_certificates');
    }

    public function testMailerSmimeEncrypterRequiresARepositoryOrCertificates()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You must configure either "smime_encrypter.repository" or "smime_encrypter.certificates".');

        $this->createContainerFromFile('mailer_with_smime_without_source');
    }

    public function testMailerPgp()
    {
        if (!class_exists(PgpSigner::class) || !class_exists(PgpMimeSignedMessageListener::class)) {
            $this->markTestSkipped('This test requires symfony/mime 8.2 and symfony/mailer 8.2 or higher.');
        }

        $container = $this->createContainerFromFile('mailer_with_pgp');

        $signer = $container->getDefinition('mailer.pgp_signer');
        $this->assertSame('/path/to/secret.asc', $signer->getArgument(0));
        $this->assertSame('/path/to/public.asc', $signer->getArgument(1));
        $this->assertSame('passphrase', $signer->getArgument(2));
        $this->assertSame(['binary' => 'gpg', 'digest_algorithm' => 'SHA256'], $signer->getArgument(3));

        $repository = $container->getDefinition('mailer.pgp_encrypter.repository');
        $this->assertSame(InMemoryPgpPublicKeyRepository::class, $repository->getClass());
        $this->assertSame([
            'r1@example.com' => '/path/to/r1.asc',
            'r2@example.com' => '/path/to/r2.asc',
        ], $repository->getArgument(0));

        $this->assertSame([
            'binary' => 'gpg',
            'cipher_algorithm' => 'AES192',
            'timeout' => 60.0,
            'hide_recipients' => true,
        ], $container->getDefinition('mailer.pgp_encrypter')->getArgument(0));

        $listener = $container->getDefinition('mailer.pgp_encrypter.listener');
        $this->assertSame('skip', $listener->getArgument(2));
        $this->assertTrue($listener->getArgument(3));
    }

    public function testMailerPgpEncrypterFailsAndDoesNotEncryptForTheSenderByDefault()
    {
        if (!class_exists(PgpEncrypter::class) || !class_exists(PgpMimeEncryptedMessageListener::class)) {
            $this->markTestSkipped('This test requires symfony/mime 8.2 and symfony/mailer 8.2 or higher.');
        }

        $container = $this->createContainerFromFile('mailer_with_pgp_repository');

        $this->assertSame('my_pgp_repository', (string) $container->getAlias('mailer.pgp_encrypter.repository'));
        $this->assertFalse($container->hasDefinition('mailer.pgp_signer'));

        $listener = $container->getDefinition('mailer.pgp_encrypter.listener');
        $this->assertSame('fail', $listener->getArgument(2));
        $this->assertFalse($listener->getArgument(3));
    }

    public function testMailerPgpSignerRequiresASecretKey()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You must configure "pgp_signer.secret_key".');

        $this->createContainerFromFile('mailer_with_pgp_signer_without_secret_key');
    }

    public function testMailerPgpEncrypterRequiresARepositoryOrKeys()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You must configure either "pgp_encrypter.repository" or "pgp_encrypter.keys".');

        $this->createContainerFromFile('mailer_with_pgp_without_source');
    }

    public function testMailerWithDisabledMessageBus()
    {
        $container = $this->createContainerFromFile('mailer_with_disabled_message_bus');

        $this->assertNull($container->getDefinition('mailer.mailer')->getArgument(1));
    }

    /**
     * @param array{profiler?: bool|array<string, mixed>, test?: bool} $extraConfig
     */
    #[DataProvider('provideLoggerListenerRegistration')]
    public function testLoggerListenerRegistration(string $serviceId, array $extraConfig, bool $expectedRegistered, bool $expectedGated)
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) use ($extraConfig) {
            $container->loadFromExtension('framework', array_merge([
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'secret' => 's3cr3t',
                'mailer' => ['dsn' => 'smtp://null'],
                'notifier' => ['texter_transports' => ['twilio' => 'twilio://ACCOUNT:TOKEN@default?from=FROM']],
            ], $extraConfig));
        });

        $this->assertSame($expectedRegistered, $container->hasDefinition($serviceId));

        if (!$expectedRegistered) {
            return;
        }

        $arguments = $container->getDefinition($serviceId)->getArguments();

        if ($expectedGated) {
            $this->assertEquals(new Reference('profiler.is_disabled_state_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE), $arguments[0]);
        } else {
            $this->assertSame([], $arguments);
        }
    }

    public static function provideLoggerListenerRegistration(): iterable
    {
        $profiler = ['profiler' => ['enabled' => true]];

        foreach (['mailer.message_logger_listener', 'notifier.notification_logger_listener'] as $serviceId) {
            $name = substr($serviceId, 0, strpos($serviceId, '.'));

            // Nothing consumes the retained messages, so the listener is dropped.
            yield $name.': neither profiler nor test' => [$serviceId, [], false, false];

            // The profiler consumes them, but only while it is collecting.
            yield $name.': profiler only' => [$serviceId, $profiler, true, true];

            // The assertions read the listener directly, so it must always collect.
            yield $name.': test only' => [$serviceId, ['test' => true], true, false];
            yield $name.': profiler and test' => [$serviceId, $profiler + ['test' => true], true, false];
        }
    }

    public function testMailerWithSpecificMessageBus()
    {
        $container = $this->createContainerFromFile('mailer_with_specific_message_bus');

        $this->assertEquals(new Reference('app.another_bus'), $container->getDefinition('mailer.mailer')->getArgument(1));
    }

    public function testHttpClientNoMock()
    {
        $container = $this->createContainerFromFile('http_client_scoped_without_query_option');

        $definition = $container->getDefinition('foo');
        $arguments = $definition->getArgument(0);

        $this->assertCount(1, $arguments);
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('http_client.transport', (string) $arguments[0]);
    }

    public function testMailerRateLimiter()
    {
        $container = $this->createContainerFromFile('mailer_with_rate_limiter');

        $this->assertTrue($container->hasDefinition('mailer.rate_limiter_locator'));
        $l = $container->getDefinition('mailer.rate_limiter_locator');
        $this->assertCount(1, $l->getArguments());
        $this->assertEquals(new Reference('limiter.foo_limiter', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE), $l->getArgument(0)['main']);
    }

    public function testHttpClientMockResponseFactory()
    {
        $container = $this->createContainerFromFile('http_client_mock_response_factory');

        $this->assertTrue($container->hasDefinition('.http_client.mock_transport.my_factory'));
        $this->assertTrue($container->hasDefinition('.http_client.mock_transport.my_other_factory'));

        // opted-out clients point at the undecorated real transport, renamed by the mock decoration
        $definition = $container->getDefinition('notMocked');
        $arguments = $definition->getArgument(0);
        $this->assertSame('http_client.transport.real', (string) $arguments[0]);

        // "mocked" inherits the top-level factory, which decorates "http_client.transport" in place
        $definition = $container->getDefinition('mocked');
        $arguments = $definition->getArgument(0);
        $this->assertSame('http_client.transport', (string) $arguments[0]);

        $definition = $container->getDefinition('mocked_custom_factory');
        $arguments = $definition->getArgument(0);
        $this->assertSame('.http_client.mock_transport.my_other_factory', (string) $arguments[0]);
    }

    public function testHttpClientUseMockClientButOverrideInScopedClientsAndEnableFactories()
    {
        $container = $this->createContainerFromFile('http_client_mock');

        $this->assertTrue($container->hasDefinition('http_client.mock_transport'));
        $this->assertTrue($container->hasDefinition('.http_client.mock_transport.my_response_factory'));

        // opted-out clients point at the undecorated real transport, renamed by the mock decoration
        $definition = $container->getDefinition('notMocked');
        $arguments = $definition->getArgument(0);
        $this->assertSame('http_client.transport.real', (string) $arguments[0]);

        // "mocked" inherits the top-level boolean factory, which decorates "http_client.transport" in place
        $definition = $container->getDefinition('mocked');
        $arguments = $definition->getArgument(0);
        $this->assertSame('http_client.transport', (string) $arguments[0]);

        $definition = $container->getDefinition('mocked_with_factory');
        $arguments = $definition->getArgument(0);
        $this->assertSame('.http_client.mock_transport.my_response_factory', (string) $arguments[0]);
    }

    public function testHttpClientRootClientMockedFromTopLevelFactory()
    {
        $container = $this->createContainerFromFile('http_client_mock_response_factory');

        // The root client keeps using "http_client.transport"; the mock decorates it in place so decorators
        // registered on "http_client.transport" are preserved.
        $definition = $container->getDefinition('http_client');
        $arguments = $definition->getArgument(0);
        $this->assertCount(1, $arguments);
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('http_client.transport', (string) $arguments[0]);

        $decoratedService = $container->getDefinition('.http_client.mock_transport.my_factory')->getDecoratedService();
        $this->assertSame('http_client.transport', $decoratedService[0]);
        $this->assertSame('http_client.transport.real', $decoratedService[1]);
        $this->assertSame(\PHP_INT_MAX, $decoratedService[2]);
    }

    public function testHttpClientRootClientMockedFromBooleanTopLevel()
    {
        $container = $this->createContainerFromFile('http_client_mock');

        $definition = $container->getDefinition('http_client');
        $arguments = $definition->getArgument(0);
        $this->assertCount(1, $arguments);
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('http_client.transport', (string) $arguments[0]);

        $decoratedService = $container->getDefinition('http_client.mock_transport')->getDecoratedService();
        $this->assertSame('http_client.transport', $decoratedService[0]);
        $this->assertSame('http_client.transport.real', $decoratedService[1]);
        $this->assertSame(\PHP_INT_MAX, $decoratedService[2]);
    }

    /**
     * Configuring a mock response factory must decorate "http_client.transport" in place, not replace it as the
     * transport referenced by "http_client". Otherwise any decorator registered on "http_client.transport" (a
     * common way to add cross-cutting behavior, e.g. dispatching an event per request) is silently removed from
     * the chain as soon as a mock factory is configured.
     */
    public function testHttpClientMockResponseFactoryKeepsTransportDecoratable()
    {
        $container = $this->createContainerFromFile('http_client_mock_response_factory');

        // "http_client" keeps using "http_client.transport", so decorators registered on it stay in the chain.
        $this->assertSame('http_client.transport', (string) $container->getDefinition('http_client')->getArgument(0)[0]);

        // The mock is wired as the innermost decorator of "http_client.transport", not as its replacement, so
        // decorators registered with any priority keep wrapping it.
        $decoratedService = $container->getDefinition('.http_client.mock_transport.my_factory')->getDecoratedService();
        $this->assertNotNull($decoratedService, 'The mock transport must decorate "http_client.transport".');
        $this->assertSame('http_client.transport', $decoratedService[0]);
        $this->assertSame(\PHP_INT_MAX, $decoratedService[2]);
    }

    public function testHttpClientRootClientNotMockedByDefault()
    {
        $container = $this->createContainerFromFile('http_client_scoped_without_query_option');

        $definition = $container->getDefinition('http_client');
        $arguments = $definition->getArgument(0);
        $this->assertCount(1, $arguments);
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('http_client.transport', (string) $arguments[0]);
    }

    public function testRegisterParameterCollectingBehaviorDescribingTags()
    {
        try {
            $defaultTags = (new \ReflectionClassConstant(AddBehaviorDescribingTagsPass::class, 'DEFAULT_TAGS'))->getValue();
        } catch (\ReflectionException) {
            $defaultTags = [];
        }

        if (!\in_array('proxy', $defaultTags, true)) {
            $this->markTestSkipped('Requires symfony/dependency-injection registering "proxy" and "container.service_subscriber.locator" as default behavior-describing tags.');
        }

        $container = $this->createContainerFromFile('default_config', [], true, false);
        $container->addCompilerPass(new AddBehaviorDescribingTagsPass([
            'container.do_not_inline',
            'container.service_locator',
            'container.service_subscriber',
            'kernel.event_subscriber',
            'kernel.event_listener',
            'kernel.reset',
        ]));
        $container->addCompilerPass(new AddBehaviorDescribingTagsPass(['kernel.locale_aware']));
        $container->compile();

        $this->assertTrue($container->hasParameter('container.behavior_describing_tags'));
        $this->assertEquals([
            'proxy',
            'container.do_not_inline',
            'container.service_locator',
            'container.service_subscriber',
            'container.service_subscriber.locator',
            'kernel.event_subscriber',
            'kernel.event_listener',
            'kernel.reset',
            'kernel.locale_aware',
        ], $container->getParameter('container.behavior_describing_tags'));
    }

    public function testNotifierWithoutMailer()
    {
        $container = $this->createContainerFromFile('notifier_without_mailer');

        $this->assertFalse($container->hasDefinition('notifier.channel.email'));
    }

    public function testNotifierWithoutMessenger()
    {
        $container = $this->createContainerFromFile('notifier_without_messenger');

        $this->assertFalse($container->getDefinition('notifier.failed_message_listener')->hasTag('kernel.event_subscriber'));
    }

    public function testNotificationLoggerListenerIsResettable()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'secret' => 's3cr3t',
                'test' => true,
                'notifier' => ['texter_transports' => ['twilio' => 'twilio://ACCOUNT:TOKEN@default?from=FROM']],
            ]);
        });

        // Otherwise a worker keeps every notification it ever sent, and the
        // collector reports the ones from previous messages.
        $this->assertSame([['method' => 'reset']], $container->getDefinition('notifier.notification_logger_listener')->getTag('kernel.reset'));
    }

    public function testNotifierWithMailerAndMessenger()
    {
        $container = $this->createContainerFromFile('notifier');

        $this->assertTrue($container->hasDefinition('notifier'));
        $this->assertTrue($container->hasDefinition('chatter'));
        $this->assertTrue($container->hasDefinition('texter'));
        $this->assertTrue($container->hasDefinition('notifier.channel.chat'));
        $this->assertTrue($container->hasDefinition('notifier.channel.email'));
        $this->assertTrue($container->hasDefinition('notifier.channel.sms'));
        $this->assertTrue($container->hasDefinition('notifier.channel_policy'));
        $this->assertTrue($container->getDefinition('notifier.failed_message_listener')->hasTag('kernel.event_subscriber'));
    }

    public function testNotifierWithoutTransports()
    {
        $container = $this->createContainerFromFile('notifier_without_transports');

        $this->assertTrue($container->hasDefinition('notifier'));
        $this->assertFalse($container->hasDefinition('chatter'));
        $this->assertFalse($container->hasAlias(ChatterInterface::class));
        $this->assertFalse($container->hasDefinition('texter'));
        $this->assertFalse($container->hasAlias(TexterInterface::class));
    }

    public function testIfNotifierTransportsAreKnownByFrameworkExtension()
    {
        if (!class_exists(FullStack::class)) {
            $this->markTestSkipped('This test can only run in fullstack test suites');
        }

        $container = $this->createContainerFromFile('notifier');

        foreach ((new Finder())->in(\dirname(__DIR__, 4).'/Component/Notifier/Bridge')->directories()->depth(0)->exclude('Mercure') as $bridgeDirectory) {
            $transportFactoryName = strtolower(preg_replace('/(.)([A-Z])/', '$1-$2', $bridgeDirectory->getFilename()));
            $this->assertTrue($container->hasDefinition('notifier.transport_factory.'.$transportFactoryName), \sprintf('Did you forget to add the "%s" TransportFactory to the $classToServices array in FrameworkExtension?', $bridgeDirectory->getFilename()));
        }
    }

    public function testLocaleSwitcherServiceRegistered()
    {
        if (!class_exists(LocaleSwitcher::class)) {
            $this->markTestSkipped('LocaleSwitcher not available.');
        }

        $container = $this->createContainerFromFile('full', compile: false);
        $container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $container->compile();

        $this->assertTrue($container->has('translation.locale_switcher'));

        $switcherDef = $container->getDefinition('translation.locale_switcher');

        $this->assertSame('%kernel.default_locale%', $switcherDef->getArgument(0));
        $this->assertInstanceOf(TaggedIteratorArgument::class, $switcherDef->getArgument(1));
        $this->assertSame('kernel.locale_aware', $switcherDef->getArgument(1)->getTag());
        $this->assertEquals(new Reference('router.request_context', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE), $switcherDef->getArgument(2));

        $localeAwareServices = array_map(static fn (Reference $r) => (string) $r, $switcherDef->getArgument(1)->getValues());

        $this->assertNotContains('translation.locale_switcher', $localeAwareServices);
    }

    public function testNotifierWithDisabledMessageBus()
    {
        $container = $this->createContainerFromFile('notifier_with_disabled_message_bus');

        $this->assertNull($container->getDefinition('chatter')->getArgument(1));
        $this->assertNull($container->getDefinition('texter')->getArgument(1));
        $this->assertNull($container->getDefinition('notifier.channel.chat')->getArgument(1));
        $this->assertNull($container->getDefinition('notifier.channel.email')->getArgument(1));
        $this->assertNull($container->getDefinition('notifier.channel.sms')->getArgument(1));
    }

    public function testNotifierWithSpecificMessageBus()
    {
        $container = $this->createContainerFromFile('notifier_with_specific_message_bus');

        $this->assertEquals(new Reference('app.another_bus'), $container->getDefinition('chatter')->getArgument(1));
        $this->assertEquals(new Reference('app.another_bus'), $container->getDefinition('texter')->getArgument(1));
        $this->assertEquals(new Reference('app.another_bus'), $container->getDefinition('notifier.channel.chat')->getArgument(1));
        $this->assertEquals(new Reference('app.another_bus'), $container->getDefinition('notifier.channel.email')->getArgument(1));
        $this->assertEquals(new Reference('app.another_bus'), $container->getDefinition('notifier.channel.sms')->getArgument(1));
    }

    public function testTrustedProxiesWithPrivateRanges()
    {
        $container = $this->createContainerFromFile('trusted_proxies_private_ranges');

        $this->assertSame(IpUtils::PRIVATE_SUBNETS, $container->getParameter('kernel.trusted_proxies'));
    }

    public function testAssetMapperWithoutAssets()
    {
        $container = $this->createContainerFromFile('asset_mapper_without_assets');

        $this->assertTrue($container->has('asset_mapper'));
        $this->assertSame([['method' => 'reset', 'on_invalid' => 'ignore']], $container->getDefinition('asset_mapper.cached_mapped_asset_factory')->getTag('kernel.reset'));
        $this->assertFalse($container->has('asset_mapper.asset_package'));
        $this->assertFalse($container->has('assets.packages'));
        $this->assertFalse($container->has('assets._default_package'));
    }

    #[TestWith([true, '/assets_path/'])]
    #[TestWith([false, null])]
    public function testAssetMapperDevServerPrefix(bool $server, ?string $expectedPrefix)
    {
        $container = $this->createContainerFromClosure(static function ($container) use ($server) {
            $container->loadFromExtension('framework', [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'assets' => null,
                'asset_mapper' => [
                    'server' => $server,
                    'public_prefix' => '/assets_path/',
                    'paths' => ['assets/'],
                ],
            ]);
        });

        $this->assertSame($expectedPrefix, $container->getDefinition('asset_mapper.asset_package')->getArgument(3));
    }

    public function testAssetMapperMinimumReleaseAge()
    {
        $container = $this->createContainerFromFile('asset_mapper_minimum_release_age');

        $this->assertSame(604800, $container->getDefinition('asset_mapper.importmap.update_checker')->getArgument(3));
    }

    public function testAssetMapperImportmapEntries()
    {
        $container = $this->createContainerFromClosure(static function ($container) {
            $container->loadFromExtension('framework', [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'assets' => null,
                'asset_mapper' => [
                    'paths' => ['assets/'],
                    'importmap_entries' => 'reachable',
                    'importmap_polyfill' => 'my-polyfill',
                ],
            ]);
        });

        $definition = $container->getDefinition('asset_mapper.importmap.generator');
        $this->assertSame('reachable', $definition->getArgument(4));
        // the polyfill name is configured on the renderer only, and handed over at render time
        $this->assertSame('my-polyfill', $container->getDefinition('asset_mapper.importmap.renderer')->getArgument(3));
    }

    public function testJsonStreamerConfigurationIsForwardedToJsonStreamerBundle()
    {
        $container = $this->createContainerFromFile('legacy_json_streamer');

        $this->assertTrue($container->has('test_json_streamer_stream_writer'));
        $this->assertSame(['include_null_properties' => true], $container->getParameter('.json_streamer.default_options'));
    }

    public function testSecretsDecryptionEnvVarWithDot()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'secrets' => [
                    'enabled' => true,
                    'vault_directory' => '%kernel.project_dir%/config/secrets/%kernel.environment%',
                    'decryption_env_var' => 'dynamic.var',
                ],
            ]);
        });
        $this->assertTrue($container->hasDefinition('secrets.vault'));
    }

    public function testSecretsDecryptionEnvVarWithInvalidCharacters()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value "invalid@var" set as "decryption_env_var": only "word" and dot characters are allowed.');
        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'secrets' => [
                    'enabled' => true,
                    'vault_directory' => '%kernel.project_dir%/config/secrets/%kernel.environment%',
                    'decryption_env_var' => 'invalid@var',
                ],
            ]);
        });
    }

    public function testYamlLintCommandUsesTheKernelConfigDir()
    {
        if (!interface_exists(SchemaResolverInterface::class)) {
            $this->markTestSkipped('The installed symfony/yaml has no JSON schema support.');
        }

        $container = $this->createContainerFromFile('default_config', ['.kernel.config_dir' => '/project/config']);

        $resolver = $container->getDefinition('console.command.yaml_lint')->getArgument(0);

        $this->assertSame('/project/config', $resolver->getArgument(0));
    }

    public function testYamlLintCommandWithoutKernelConfigDir()
    {
        if (!interface_exists(SchemaResolverInterface::class)) {
            $this->markTestSkipped('The installed symfony/yaml has no JSON schema support.');
        }

        $container = $this->createContainerFromFile('default_config');

        $resolver = $container->getDefinition('console.command.yaml_lint')->getArgument(0);

        // Without a config directory, the generated schema.json is never applied.
        $this->assertNull($resolver->getArgument(0));
    }

    protected function createContainer(array $data = [])
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag(array_merge([
            'kernel.bundles' => ['FrameworkBundle' => FrameworkBundle::class],
            'kernel.bundles_metadata' => ['FrameworkBundle' => ['namespace' => 'Symfony\\Bundle\\FrameworkBundle', 'path' => __DIR__.'/../..']],
            'kernel.cache_dir' => __DIR__,
            'kernel.build_dir' => __DIR__,
            'kernel.share_dir' => __DIR__,
            'kernel.project_dir' => __DIR__,
            'kernel.debug' => false,
            'kernel.environment' => 'test',
            'kernel.runtime_mode.web' => true,
            'kernel.name' => 'kernel',
            'kernel.container_class' => 'testContainer',
            'container.build_hash' => 'Abc1234',
            'container.build_id' => hash('crc32', 'Abc123423456789'),
            'container.build_time' => 23456789,
        ], $data)));

        new ServicesBundle()->getContainerExtension()->load([], $container);
        $cacheBundle = new CacheBundle();
        $cacheBundle->build($container);
        $container->registerExtension($cacheBundle->getContainerExtension());
        $container->registerExtension(new LockBundle()->getContainerExtension());
        $container->registerExtension(new MessengerBundle()->getContainerExtension());
        $container->registerExtension(new SchedulerBundle()->getContainerExtension());
        $container->registerExtension(new JsonStreamerBundle()->getContainerExtension());
        $container->registerExtension(new PropertyInfoBundle()->getContainerExtension());
        $container->registerExtension(new AssetMapperBundle()->getContainerExtension());
        $container->registerExtension(new RateLimiterBundle()->getContainerExtension());
        $container->registerExtension(new WebhookBundle()->getContainerExtension());
        $container->registerExtension(new HttpClientBundle()->getContainerExtension());
        $container->getCompilerPassConfig()->setMergePass(new MergeExtensionConfigurationPass(['cache']));

        return $container;
    }

    protected function createContainerFromFile(string $file, array $data = [], bool $resetCompilerPasses = true, bool $compile = true, ?FrameworkExtension $extension = null): ContainerBuilder
    {
        $cacheKey = md5(static::class.$file.serialize($data));
        if ($compile && isset(self::$containerCache[$cacheKey])) {
            return self::$containerCache[$cacheKey];
        }
        $container = $this->createContainer($data);
        $container->registerExtension($extension ?: new FrameworkExtension());
        $this->loadFromFile($container, $file);

        if ($resetCompilerPasses) {
            $container->getCompilerPassConfig()->setOptimizationPasses([]);
            $container->getCompilerPassConfig()->setRemovingPasses([]);
            $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        }
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([new AddBehaviorDescribingTagsPass(), new LoggerPass(), new DefaultLockFactoryPass(), new DefaultMessageBusPass(), new RemoveMissingDependenciesPass(), new AssetMapperRemoveMissingDependenciesPass(), new WebhookRemoveMissingDependenciesPass(), new HttpClientRemoveMissingDependenciesPass(), new RemoveMissingHttpClientDependenciesPass()]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([new AddConstraintValidatorsPass(), new TranslatorPass()]);

        if (!$compile) {
            return $container;
        }
        $container->compile();

        return self::$containerCache[$cacheKey] = $container;
    }

    protected function createContainerFromClosure($closure, $data = [], bool $compile = true): ContainerBuilder
    {
        $container = $this->createContainer($data);
        $container->registerExtension(new FrameworkExtension());
        $loader = new ClosureLoader($container);
        $loader->load($closure);

        $container->addCompilerPass(new DefaultLockFactoryPass());
        $container->addCompilerPass(new DefaultMessageBusPass());
        $container->addCompilerPass(new RemoveMissingDependenciesPass());
        $container->addCompilerPass(new AssetMapperRemoveMissingDependenciesPass());
        $container->addCompilerPass(new WebhookRemoveMissingDependenciesPass());
        $container->addCompilerPass(new HttpClientRemoveMissingDependenciesPass());
        $container->addCompilerPass(new RemoveMissingHttpClientDependenciesPass());
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);

        if ($compile) {
            $container->compile();
        }

        return $container;
    }

    private function assertPathPackage(ContainerBuilder $container, ChildDefinition $package, $basePath, $version, $format)
    {
        $this->assertEquals('assets.path_package', $package->getParent());
        $this->assertEquals($basePath, $package->getArgument(0));
        $this->assertVersionStrategy($container, $package->getArgument(1), $version, $format);
    }

    private function assertUrlPackage(ContainerBuilder $container, ChildDefinition $package, $baseUrls, $version, $format)
    {
        $this->assertEquals('assets.url_package', $package->getParent());
        $this->assertEquals($baseUrls, $package->getArgument(0));
        $this->assertVersionStrategy($container, $package->getArgument(1), $version, $format);
    }

    private function assertVersionStrategy(ContainerBuilder $container, Reference $reference, $version, $format)
    {
        $versionStrategy = $container->getDefinition((string) $reference);
        if (null === $version) {
            $this->assertEquals('assets.empty_version_strategy', (string) $reference);
        } else {
            $this->assertEquals('assets.static_version_strategy', $versionStrategy->getParent());
            $this->assertEquals($version, $versionStrategy->getArgument(0));
            $this->assertEquals($format, $versionStrategy->getArgument(1));
        }
    }

    private function assertCachePoolServiceDefinitionIsCreated(ContainerBuilder $container, $id, $adapter, $defaultLifetime)
    {
        $this->assertTrue($container->has($id), \sprintf('Service definition "%s" for cache pool of type "%s" is registered', $id, $adapter));

        $poolDefinition = $container->getDefinition($id);

        $this->assertInstanceOf(ChildDefinition::class, $poolDefinition, \sprintf('Cache pool "%s" is based on an abstract cache pool.', $id));

        $this->assertTrue($poolDefinition->hasTag('cache.pool'), \sprintf('Service definition "%s" is tagged with the "cache.pool" tag.', $id));
        $this->assertFalse($poolDefinition->isAbstract(), \sprintf('Service definition "%s" is not abstract.', $id));

        $tag = $poolDefinition->getTag('cache.pool');
        $this->assertArrayHasKey('default_lifetime', $tag[0], 'The default lifetime is stored as an attribute of the "cache.pool" tag.');
        $this->assertSame($defaultLifetime, $tag[0]['default_lifetime'], 'The default lifetime is stored as an attribute of the "cache.pool" tag.');

        $parentDefinition = $poolDefinition;
        do {
            $parentId = $parentDefinition->getParent();
            $parentDefinition = $container->findDefinition($parentId);
        } while ($parentDefinition instanceof ChildDefinition);

        match ($adapter) {
            'cache.adapter.apcu' => $this->assertSame(ApcuAdapter::class, $parentDefinition->getClass()),
            'cache.app', 'cache.adapter.filesystem' => $this->assertSame(FilesystemAdapter::class, $parentDefinition->getClass()),
            'cache.adapter.psr6' => $this->assertSame(ProxyAdapter::class, $parentDefinition->getClass()),
            'cache.adapter.redis' => $this->assertSame(RedisAdapter::class, $parentDefinition->getClass()),
            'cache.adapter.array' => $this->assertSame(ArrayAdapter::class, $parentDefinition->getClass()),
            default => $this->fail('Unresolved adapter: '.$adapter),
        };
    }
}
