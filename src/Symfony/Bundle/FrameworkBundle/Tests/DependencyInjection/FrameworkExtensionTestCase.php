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

use Doctrine\Common\Annotations\Annotation;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddAnnotationsCachedReaderPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyMessage;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FullStack;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveTaggedIteratorArgumentPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Form;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\HttpKernel\DependencyInjection\LoggerPass;
use Symfony\Component\HttpKernel\Fragment\FragmentUriGeneratorInterface;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransportFactory;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\BeanstalkdTransportFactory;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\FormErrorNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Translation\DependencyInjection\TranslatorPass;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Validator\DependencyInjection\AddConstraintValidatorsPass;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow;
use Symfony\Component\Workflow\Exception\InvalidDefinitionException;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\WorkflowEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

abstract class FrameworkExtensionTestCase extends TestCase
{
    use ExpectDeprecationTrait;

    private static $containerCache = [];

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

    public function testPropertyAccessWithDefaultValue()
    {
        $container = $this->createContainerFromFile('full');

        $def = $container->getDefinition('property_accessor');
        $this->assertSame(PropertyAccessor::MAGIC_SET | PropertyAccessor::MAGIC_GET, $def->getArgument(0));
        $this->assertSame(PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH, $def->getArgument(1));
    }

    public function testPropertyAccessWithOverriddenValues()
    {
        $container = $this->createContainerFromFile('property_accessor');
        $def = $container->getDefinition('property_accessor');
        $this->assertSame(PropertyAccessor::MAGIC_GET | PropertyAccessor::MAGIC_CALL, $def->getArgument(0));
        $this->assertSame(PropertyAccessor::THROW_ON_INVALID_INDEX, $def->getArgument(1));
    }

    public function testPropertyAccessCache()
    {
        $container = $this->createContainerFromFile('property_accessor');

        if (!method_exists(PropertyAccessor::class, 'createCache')) {
            $this->assertFalse($container->hasDefinition('cache.property_access'));

            return;
        }

        $cache = $container->getDefinition('cache.property_access');
        $this->assertSame([PropertyAccessor::class, 'createCache'], $cache->getFactory(), 'PropertyAccessor::createCache() should be used in non-debug mode');
        $this->assertSame(AdapterInterface::class, $cache->getClass());
    }

    public function testPropertyAccessCacheWithDebug()
    {
        $container = $this->createContainerFromFile('property_accessor', ['kernel.debug' => true]);

        if (!method_exists(PropertyAccessor::class, 'createCache')) {
            $this->assertFalse($container->hasDefinition('cache.property_access'));

            return;
        }

        $cache = $container->getDefinition('cache.property_access');
        $this->assertNull($cache->getFactory());
        $this->assertSame(ArrayAdapter::class, $cache->getClass(), 'ArrayAdapter should be used in debug mode');
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

    public function testFragmentsAndHinclude()
    {
        $container = $this->createContainerFromFile('fragments_and_hinclude');
        $this->assertTrue($container->has('fragment.uri_generator'));
        $this->assertTrue($container->hasAlias(FragmentUriGeneratorInterface::class));
        $this->assertTrue($container->hasParameter('fragment.renderer.hinclude.global_template'));
        $this->assertEquals('global_hinclude_template', $container->getParameter('fragment.renderer.hinclude.global_template'));
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
    }

    public function testDisabledProfiler()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertFalse($container->hasDefinition('profiler'), '->registerProfilerConfiguration() does not load profiling.xml');
        $this->assertFalse($container->hasDefinition('data_collector.config'), '->registerProfilerConfiguration() does not load collectors.xml');
    }

    public function testProfilerCollectSerializerDataEnabled()
    {
        $container = $this->createContainerFromFile('profiler_collect_serializer_data');

        $this->assertTrue($container->hasDefinition('profiler'));
        $this->assertTrue($container->hasDefinition('serializer.data_collector'));
        $this->assertTrue($container->hasDefinition('debug.serializer'));
    }

    public function testProfilerCollectSerializerDataDefaultDisabled()
    {
        $container = $this->createContainerFromFile('profiler');

        $this->assertTrue($container->hasDefinition('profiler'));
        $this->assertFalse($container->hasDefinition('serializer.data_collector'));
        $this->assertFalse($container->hasDefinition('debug.serializer'));
    }

    public function testWorkflows()
    {
        $container = $this->createContainerFromFile('workflows');

        $this->assertTrue($container->hasDefinition('workflow.article'), 'Workflow is registered as a service');
        $this->assertSame('workflow.abstract', $container->getDefinition('workflow.article')->getParent());

        $args = $container->getDefinition('workflow.article')->getArguments();
        $this->assertArrayHasKey('index_0', $args);
        $this->assertArrayHasKey('index_1', $args);
        $this->assertArrayHasKey('index_3', $args);
        $this->assertArrayHasKey('index_4', $args);
        $this->assertNull($args['index_4'], 'Workflows has eventsToDispatch=null');

        $this->assertSame(['workflow' => [['name' => 'article']], 'workflow.workflow' => [['name' => 'article']]], $container->getDefinition('workflow.article')->getTags());

        $this->assertTrue($container->hasDefinition('workflow.article.definition'), 'Workflow definition is registered as a service');

        $workflowDefinition = $container->getDefinition('workflow.article.definition');

        $this->assertSame(
            [
                'draft',
                'wait_for_journalist',
                'approved_by_journalist',
                'wait_for_spellchecker',
                'approved_by_spellchecker',
                'published',
            ],
            $workflowDefinition->getArgument(0),
            'Places are passed to the workflow definition'
        );
        $this->assertCount(4, $workflowDefinition->getArgument(1));
        $this->assertSame(['draft'], $workflowDefinition->getArgument(2));
        $metadataStoreDefinition = $container->getDefinition('workflow.article.metadata_store');
        $this->assertSame(InMemoryMetadataStore::class, $metadataStoreDefinition->getClass());
        $this->assertSame([
            'title' => 'article workflow',
            'description' => 'workflow for articles',
        ], $metadataStoreDefinition->getArgument(0));

        $this->assertTrue($container->hasDefinition('state_machine.pull_request'), 'State machine is registered as a service');
        $this->assertSame('state_machine.abstract', $container->getDefinition('state_machine.pull_request')->getParent());
        $this->assertTrue($container->hasDefinition('state_machine.pull_request.definition'), 'State machine definition is registered as a service');

        $this->assertSame(['workflow' => [['name' => 'pull_request']], 'workflow.state_machine' => [['name' => 'pull_request']]], $container->getDefinition('state_machine.pull_request')->getTags());

        $stateMachineDefinition = $container->getDefinition('state_machine.pull_request.definition');

        $this->assertSame(
            [
                'start',
                'coding',
                'travis',
                'review',
                'merged',
                'closed',
            ],
            $stateMachineDefinition->getArgument(0),
            'Places are passed to the state machine definition'
        );
        $this->assertCount(9, $stateMachineDefinition->getArgument(1));
        $this->assertSame(['start'], $stateMachineDefinition->getArgument(2));

        $metadataStoreReference = $stateMachineDefinition->getArgument(3);
        $this->assertInstanceOf(Reference::class, $metadataStoreReference);
        $this->assertSame('state_machine.pull_request.metadata_store', (string) $metadataStoreReference);

        $metadataStoreDefinition = $container->getDefinition('state_machine.pull_request.metadata_store');
        $this->assertSame(Workflow\Metadata\InMemoryMetadataStore::class, $metadataStoreDefinition->getClass());
        $this->assertSame(InMemoryMetadataStore::class, $metadataStoreDefinition->getClass());

        $workflowMetadata = $metadataStoreDefinition->getArgument(0);
        $this->assertSame(['title' => 'workflow title'], $workflowMetadata);

        $placesMetadata = $metadataStoreDefinition->getArgument(1);
        $this->assertArrayHasKey('start', $placesMetadata);
        $this->assertSame(['title' => 'place start title'], $placesMetadata['start']);

        $transitionsMetadata = $metadataStoreDefinition->getArgument(2);
        $this->assertSame(\SplObjectStorage::class, $transitionsMetadata->getClass());
        $transitionsMetadataCall = $transitionsMetadata->getMethodCalls()[0];
        $this->assertSame('attach', $transitionsMetadataCall[0]);
        $params = $transitionsMetadataCall[1];
        $this->assertCount(2, $params);
        $this->assertInstanceOf(Reference::class, $params[0]);
        $this->assertSame('.state_machine.pull_request.transition.0', (string) $params[0]);

        $serviceMarkingStoreWorkflowDefinition = $container->getDefinition('workflow.service_marking_store_workflow');
        /** @var Reference $markingStoreRef */
        $markingStoreRef = $serviceMarkingStoreWorkflowDefinition->getArgument(1);
        $this->assertInstanceOf(Reference::class, $markingStoreRef);
        $this->assertEquals('workflow_service', (string) $markingStoreRef);

        $this->assertTrue($container->hasDefinition('.workflow.registry'), 'Workflow registry is registered as a service');
        $registryDefinition = $container->getDefinition('.workflow.registry');
        $this->assertGreaterThan(0, \count($registryDefinition->getMethodCalls()));
    }

    public function testWorkflowAreValidated()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('A transition from a place/state must have an unique name. Multiple transitions named "go" from place/state "first" were found on StateMachine "my_workflow".');
        $this->createContainerFromFile('workflow_not_valid');
    }

    public function testWorkflowCannotHaveBothSupportsAndSupportStrategy()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"supports" and "support_strategy" cannot be used together.');
        $this->createContainerFromFile('workflow_with_support_and_support_strategy');
    }

    public function testWorkflowShouldHaveOneOfSupportsAndSupportStrategy()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"supports" or "support_strategy" should be configured.');
        $this->createContainerFromFile('workflow_without_support_and_support_strategy');
    }

    public function testWorkflowMultipleTransitionsWithSameName()
    {
        $container = $this->createContainerFromFile('workflow_with_multiple_transitions_with_same_name');

        $this->assertTrue($container->hasDefinition('workflow.article'), 'Workflow is registered as a service');
        $this->assertTrue($container->hasDefinition('workflow.article.definition'), 'Workflow definition is registered as a service');

        $workflowDefinition = $container->getDefinition('workflow.article.definition');

        $transitions = $workflowDefinition->getArgument(1);

        $this->assertCount(5, $transitions);

        $this->assertSame('.workflow.article.transition.0', (string) $transitions[0]);
        $this->assertSame([
            'request_review',
            [
                'draft',
            ],
            [
                'wait_for_journalist', 'wait_for_spellchecker',
            ],
        ], $container->getDefinition($transitions[0])->getArguments());

        $this->assertSame('.workflow.article.transition.1', (string) $transitions[1]);
        $this->assertSame([
            'journalist_approval',
            [
                'wait_for_journalist',
            ],
            [
                'approved_by_journalist',
            ],
        ], $container->getDefinition($transitions[1])->getArguments());

        $this->assertSame('.workflow.article.transition.2', (string) $transitions[2]);
        $this->assertSame([
            'spellchecker_approval',
            [
                'wait_for_spellchecker',
            ],
            [
                'approved_by_spellchecker',
            ],
        ], $container->getDefinition($transitions[2])->getArguments());

        $this->assertSame('.workflow.article.transition.3', (string) $transitions[3]);
        $this->assertSame([
            'publish',
            [
                'approved_by_journalist',
                'approved_by_spellchecker',
            ],
            [
                'published',
            ],
        ], $container->getDefinition($transitions[3])->getArguments());

        $this->assertSame('.workflow.article.transition.4', (string) $transitions[4]);
        $this->assertSame([
            'publish',
            [
                'draft',
            ],
            [
                'published',
            ],
        ], $container->getDefinition($transitions[4])->getArguments());
    }

    public function testWorkflowGuardExpressions()
    {
        $container = $this->createContainerFromFile('workflow_with_guard_expression');

        $this->assertTrue($container->hasDefinition('.workflow.article.listener.guard'), 'Workflow guard listener is registered as a service');
        $this->assertTrue($container->hasParameter('workflow.has_guard_listeners'), 'Workflow guard listeners parameter exists');
        $this->assertTrue(true === $container->getParameter('workflow.has_guard_listeners'), 'Workflow guard listeners parameter is enabled');
        $guardDefinition = $container->getDefinition('.workflow.article.listener.guard');
        $this->assertSame([
            [
                'event' => 'workflow.article.guard.publish',
                'method' => 'onTransition',
            ],
        ], $guardDefinition->getTag('kernel.event_listener'));
        $guardsConfiguration = $guardDefinition->getArgument(0);
        $this->assertTrue(1 === \count($guardsConfiguration), 'Workflow guard configuration contains one element per transition name');
        $transitionGuardExpressions = $guardsConfiguration['workflow.article.guard.publish'];
        $this->assertSame('.workflow.article.transition.3', (string) $transitionGuardExpressions[0]->getArgument(0));
        $this->assertSame('!!true', $transitionGuardExpressions[0]->getArgument(1));
        $this->assertSame('.workflow.article.transition.4', (string) $transitionGuardExpressions[1]->getArgument(0));
        $this->assertSame('!!false', $transitionGuardExpressions[1]->getArgument(1));
    }

    public function testWorkflowServicesCanBeEnabled()
    {
        $container = $this->createContainerFromFile('workflows_enabled');

        $this->assertTrue($container->has(Workflow\Registry::class));
        $this->assertTrue($container->hasDefinition('console.command.workflow_dump'));
    }

    public function testWorkflowsExplicitlyEnabled()
    {
        $container = $this->createContainerFromFile('workflows_explicitly_enabled');

        $this->assertTrue($container->hasDefinition('workflow.foo.definition'));
    }

    public function testWorkflowsNamedExplicitlyEnabled()
    {
        $container = $this->createContainerFromFile('workflows_explicitly_enabled_named_workflows');

        $this->assertTrue($container->hasDefinition('workflow.workflows.definition'));
    }

    public function testWorkflowsWithNoDispatchedEvents()
    {
        $container = $this->createContainerFromFile('workflow_with_no_events_to_dispatch');

        $eventsToDispatch = $container->getDefinition('state_machine.my_workflow')->getArgument('index_4');

        $this->assertSame([], $eventsToDispatch);
    }

    public function testWorkflowsWithSpecifiedDispatchedEvents()
    {
        $container = $this->createContainerFromFile('workflow_with_specified_events_to_dispatch');

        $eventsToDispatch = $container->getDefinition('state_machine.my_workflow')->getArgument('index_4');

        $this->assertSame([WorkflowEvents::LEAVE, WorkflowEvents::COMPLETED], $eventsToDispatch);
    }

    public function testEnabledPhpErrorsConfig()
    {
        $container = $this->createContainerFromFile('php_errors_enabled');

        $definition = $container->getDefinition('debug.error_handler_configurator');
        $this->assertEquals(new Reference('monolog.logger.php', ContainerInterface::NULL_ON_INVALID_REFERENCE), $definition->getArgument(0));
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
        $this->assertEquals(new Reference('monolog.logger.php', ContainerInterface::NULL_ON_INVALID_REFERENCE), $definition->getArgument(0));
        $this->assertSame(8, $definition->getArgument(1));
    }

    public function testPhpErrorsWithLogLevels()
    {
        $container = $this->createContainerFromFile('php_errors_log_levels');

        $definition = $container->getDefinition('debug.error_handler_configurator');
        $this->assertEquals(new Reference('monolog.logger.php', ContainerInterface::NULL_ON_INVALID_REFERENCE), $definition->getArgument(0));
        $this->assertSame([
            \E_NOTICE => \Psr\Log\LogLevel::ERROR,
            \E_WARNING => \Psr\Log\LogLevel::ERROR,
        ], $definition->getArgument(1));
    }

    public function testExceptionsConfig()
    {
        $container = $this->createContainerFromFile('exceptions');

        $configuration = $container->getDefinition('exception_listener')->getArgument(3);

        $this->assertSame([
            \Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            \Symfony\Component\HttpKernel\Exception\ConflictHttpException::class,
            \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException::class,
        ], array_keys($configuration));

        $this->assertEqualsCanonicalizing([
            'log_level' => 'info',
            'status_code' => 422,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class]);

        $this->assertEqualsCanonicalizing([
            'log_level' => 'info',
            'status_code' => null,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class]);

        $this->assertEqualsCanonicalizing([
            'log_level' => 'info',
            'status_code' => null,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class]);

        $this->assertEqualsCanonicalizing([
            'log_level' => null,
            'status_code' => 500,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException::class]);
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

    public function testRouterRequiresResourceOption()
    {
        $this->expectException(InvalidConfigurationException::class);
        $container = $this->createContainer();
        $loader = new FrameworkExtension();
        $loader->load([['http_method_override' => false, 'router' => true]], $container);
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
        $this->assertEquals(22, $options['sid_length']);
        $this->assertEquals(4, $options['sid_bits_per_character']);

        $this->assertEquals('/path/to/sessions', $container->getParameter('session.save_path'));
    }

    public function testNullSessionHandler()
    {
        $container = $this->createContainerFromFile('session');

        $this->assertNull($container->getParameter('session.save_path'));
        $this->assertSame('session.handler.native', (string) $container->getAlias('session.handler'));

        $expected = ['session_factory', 'logger', 'session_collector'];
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

    public function testWebLink()
    {
        $container = $this->createContainerFromFile('web_link');
        $this->assertTrue($container->hasDefinition('web_link.add_link_header_listener'));
    }

    public function testMessengerServicesRemovedWhenDisabled()
    {
        $container = $this->createContainerFromFile('messenger_disabled');
        $messengerDefinitions = array_filter(
            $container->getDefinitions(),
            static fn ($name) => str_starts_with($name, 'messenger.'),
            \ARRAY_FILTER_USE_KEY
        );

        $this->assertEmpty($messengerDefinitions);
        $this->assertFalse($container->hasDefinition('console.command.messenger_consume_messages'));
        $this->assertFalse($container->hasDefinition('console.command.messenger_debug'));
        $this->assertFalse($container->hasDefinition('console.command.messenger_stop_workers'));
        $this->assertFalse($container->hasDefinition('console.command.messenger_setup_transports'));
        $this->assertFalse($container->hasDefinition('console.command.messenger_failed_messages_retry'));
        $this->assertFalse($container->hasDefinition('console.command.messenger_failed_messages_show'));
        $this->assertFalse($container->hasDefinition('console.command.messenger_failed_messages_remove'));
        $this->assertFalse($container->hasDefinition('cache.messenger.restart_workers_signal'));
    }

    /**
     * @group legacy
     */
    public function testMessengerWithExplictResetOnMessageLegacy()
    {
        $this->expectDeprecation('Since symfony/framework-bundle 6.1: Option "reset_on_message" at "framework.messenger" is deprecated. It does nothing and will be removed in version 7.0.');

        $container = $this->createContainerFromFile('messenger_with_explict_reset_on_message_legacy');

        $this->assertTrue($container->hasDefinition('console.command.messenger_consume_messages'));
        $this->assertTrue($container->hasAlias('messenger.default_bus'));
        $this->assertTrue($container->getAlias('messenger.default_bus')->isPublic());
        $this->assertTrue($container->hasDefinition('messenger.transport.amqp.factory'));
        $this->assertTrue($container->hasDefinition('messenger.transport.redis.factory'));
        $this->assertTrue($container->hasDefinition('messenger.transport_factory'));
        $this->assertSame(TransportFactory::class, $container->getDefinition('messenger.transport_factory')->getClass());
        $this->assertTrue($container->hasDefinition('messenger.listener.reset_services'));
        $this->assertSame('messenger.listener.reset_services', (string) $container->getDefinition('console.command.messenger_consume_messages')->getArgument(5));
    }

    public function testMessenger()
    {
        $container = $this->createContainerFromFile('messenger', [], true, false);
        $container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $container->compile();

        $expectedFactories = [
            new Reference('scheduler.messenger_transport_factory'),
        ];

        if (class_exists(AmqpTransportFactory::class)) {
            $expectedFactories[] = 'messenger.transport.amqp.factory';
        }

        if (class_exists(RedisTransportFactory::class)) {
            $expectedFactories[] = 'messenger.transport.redis.factory';
        }

        $expectedFactories[] = 'messenger.transport.sync.factory';
        $expectedFactories[] = 'messenger.transport.in_memory.factory';

        if (class_exists(AmazonSqsTransportFactory::class)) {
            $expectedFactories[] = 'messenger.transport.sqs.factory';
        }

        if (class_exists(BeanstalkdTransportFactory::class)) {
            $expectedFactories[] = 'messenger.transport.beanstalkd.factory';
        }

        $this->assertTrue($container->hasDefinition('messenger.receiver_locator'));
        $this->assertTrue($container->hasDefinition('console.command.messenger_consume_messages'));
        $this->assertTrue($container->hasAlias('messenger.default_bus'));
        $this->assertTrue($container->getAlias('messenger.default_bus')->isPublic());
        $this->assertTrue($container->hasDefinition('messenger.transport_factory'));
        $this->assertSame(TransportFactory::class, $container->getDefinition('messenger.transport_factory')->getClass());
        $this->assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition('messenger.transport_factory')->getArgument(0));
        $this->assertEquals($expectedFactories, $container->getDefinition('messenger.transport_factory')->getArgument(0)->getValues());
        $this->assertTrue($container->hasDefinition('messenger.listener.reset_services'));
        $this->assertSame('messenger.listener.reset_services', (string) $container->getDefinition('console.command.messenger_consume_messages')->getArgument(5));
    }

    public function testMessengerWithoutConsole()
    {
        $extension = $this->createPartialMock(FrameworkExtension::class, ['hasConsole', 'getAlias']);
        $extension->method('hasConsole')->willReturn(false);
        $extension->method('getAlias')->willReturn((new FrameworkExtension())->getAlias());

        $container = $this->createContainerFromFile('messenger', [], true, false, $extension);
        $container->compile();

        $this->assertFalse($container->hasDefinition('console.command.messenger_consume_messages'));
        $this->assertTrue($container->hasAlias('messenger.default_bus'));
        $this->assertTrue($container->getAlias('messenger.default_bus')->isPublic());
        $this->assertTrue($container->hasDefinition('messenger.transport_factory'));
        $this->assertFalse($container->hasDefinition('messenger.listener.reset_services'));
    }

    public function testMessengerMultipleFailureTransports()
    {
        $container = $this->createContainerFromFile('messenger_multiple_failure_transports');

        $failureTransport1Definition = $container->getDefinition('messenger.transport.failure_transport_1');
        $failureTransport1Tags = $failureTransport1Definition->getTag('messenger.receiver')[0];

        $this->assertEquals([
            'alias' => 'failure_transport_1',
            'is_failure_transport' => true,
        ], $failureTransport1Tags);

        $failureTransport3Definition = $container->getDefinition('messenger.transport.failure_transport_3');
        $failureTransport3Tags = $failureTransport3Definition->getTag('messenger.receiver')[0];

        $this->assertEquals([
            'alias' => 'failure_transport_3',
            'is_failure_transport' => true,
        ], $failureTransport3Tags);

        // transport 2 exists but does not appear in the mapping
        $this->assertFalse($container->hasDefinition('messenger.transport.failure_transport_2'));

        $failureTransportsByTransportNameServiceLocator = $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')->getArgument(0);
        $failureTransports = $container->getDefinition((string) $failureTransportsByTransportNameServiceLocator)->getArgument(0);
        $expectedTransportsByFailureTransports = [
            'transport_1' => new Reference('messenger.transport.failure_transport_1'),
            'transport_3' => new Reference('messenger.transport.failure_transport_3'),
        ];

        $failureTransportsReferences = array_map(function (ServiceClosureArgument $serviceClosureArgument) {
            $values = $serviceClosureArgument->getValues();

            return array_shift($values);
        }, $failureTransports);
        $this->assertEquals($expectedTransportsByFailureTransports, $failureTransportsReferences);
    }

    public function testMessengerMultipleFailureTransportsWithGlobalFailureTransport()
    {
        $container = $this->createContainerFromFile('messenger_multiple_failure_transports_global');

        $this->assertEquals('messenger.transport.failure_transport_global', (string) $container->getAlias('messenger.failure_transports.default'));

        $failureTransport1Definition = $container->getDefinition('messenger.transport.failure_transport_1');
        $failureTransport1Tags = $failureTransport1Definition->getTag('messenger.receiver')[0];

        $this->assertEquals([
            'alias' => 'failure_transport_1',
            'is_failure_transport' => true,
        ], $failureTransport1Tags);

        $failureTransport3Definition = $container->getDefinition('messenger.transport.failure_transport_3');
        $failureTransport3Tags = $failureTransport3Definition->getTag('messenger.receiver')[0];

        $this->assertEquals([
            'alias' => 'failure_transport_3',
            'is_failure_transport' => true,
        ], $failureTransport3Tags);

        $failureTransportsByTransportNameServiceLocator = $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')->getArgument(0);
        $failureTransports = $container->getDefinition((string) $failureTransportsByTransportNameServiceLocator)->getArgument(0);
        $expectedTransportsByFailureTransports = [
            'failure_transport_1' => new Reference('messenger.transport.failure_transport_global'),
            'failure_transport_3' => new Reference('messenger.transport.failure_transport_global'),
            'failure_transport_global' => new Reference('messenger.transport.failure_transport_global'),
            'transport_1' => new Reference('messenger.transport.failure_transport_1'),
            'transport_2' => new Reference('messenger.transport.failure_transport_global'),
            'transport_3' => new Reference('messenger.transport.failure_transport_3'),
        ];

        $failureTransportsReferences = array_map(function (ServiceClosureArgument $serviceClosureArgument) {
            $values = $serviceClosureArgument->getValues();

            return array_shift($values);
        }, $failureTransports);
        $this->assertEquals($expectedTransportsByFailureTransports, $failureTransportsReferences);
    }

    public function testMessengerTransports()
    {
        $container = $this->createContainerFromFile('messenger_transports');
        $this->assertTrue($container->hasDefinition('messenger.transport.default'));
        $this->assertTrue($container->getDefinition('messenger.transport.default')->hasTag('messenger.receiver'));
        $this->assertEquals([
            ['alias' => 'default', 'is_failure_transport' => false], ], $container->getDefinition('messenger.transport.default')->getTag('messenger.receiver'));
        $transportArguments = $container->getDefinition('messenger.transport.default')->getArguments();
        $this->assertEquals(new Reference('messenger.default_serializer'), $transportArguments[2]);

        $this->assertTrue($container->hasDefinition('messenger.transport.customised'));
        $transportFactory = $container->getDefinition('messenger.transport.customised')->getFactory();
        $transportArguments = $container->getDefinition('messenger.transport.customised')->getArguments();

        $this->assertEquals([new Reference('messenger.transport_factory'), 'createTransport'], $transportFactory);
        $this->assertCount(3, $transportArguments);
        $this->assertSame('amqp://localhost/%2f/messages?exchange_name=exchange_name', $transportArguments[0]);
        $this->assertEquals(['queue' => ['name' => 'Queue'], 'transport_name' => 'customised'], $transportArguments[1]);
        $this->assertEquals(new Reference('messenger.transport.native_php_serializer'), $transportArguments[2]);

        $this->assertTrue($container->hasDefinition('messenger.transport.amqp.factory'));

        $this->assertTrue($container->hasDefinition('messenger.transport.redis'));
        $transportFactory = $container->getDefinition('messenger.transport.redis')->getFactory();
        $transportArguments = $container->getDefinition('messenger.transport.redis')->getArguments();

        $this->assertEquals([new Reference('messenger.transport_factory'), 'createTransport'], $transportFactory);
        $this->assertCount(3, $transportArguments);
        $this->assertSame('redis://127.0.0.1:6379/messages', $transportArguments[0]);

        $this->assertTrue($container->hasDefinition('messenger.transport.redis.factory'));

        $this->assertTrue($container->hasDefinition('messenger.transport.beanstalkd'));
        $transportFactory = $container->getDefinition('messenger.transport.beanstalkd')->getFactory();
        $transportArguments = $container->getDefinition('messenger.transport.beanstalkd')->getArguments();

        $this->assertEquals([new Reference('messenger.transport_factory'), 'createTransport'], $transportFactory);
        $this->assertCount(3, $transportArguments);
        $this->assertSame('beanstalkd://127.0.0.1:11300', $transportArguments[0]);

        $this->assertTrue($container->hasDefinition('messenger.transport.beanstalkd.factory'));

        $this->assertTrue($container->hasDefinition('messenger.transport.schedule'));
        $transportFactory = $container->getDefinition('messenger.transport.schedule')->getFactory();
        $transportArguments = $container->getDefinition('messenger.transport.schedule')->getArguments();

        $this->assertEquals([new Reference('messenger.transport_factory'), 'createTransport'], $transportFactory);
        $this->assertCount(3, $transportArguments);
        $this->assertSame('schedule://default', $transportArguments[0]);

        $this->assertSame(10, $container->getDefinition('messenger.retry.multiplier_retry_strategy.customised')->getArgument(0));
        $this->assertSame(7, $container->getDefinition('messenger.retry.multiplier_retry_strategy.customised')->getArgument(1));
        $this->assertSame(3, $container->getDefinition('messenger.retry.multiplier_retry_strategy.customised')->getArgument(2));
        $this->assertSame(100, $container->getDefinition('messenger.retry.multiplier_retry_strategy.customised')->getArgument(3));

        $failureTransportsByTransportNameServiceLocator = $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')->getArgument(0);
        $failureTransports = $container->getDefinition((string) $failureTransportsByTransportNameServiceLocator)->getArgument(0);
        $expectedTransportsByFailureTransports = [
            'beanstalkd' => new Reference('messenger.transport.failed'),
            'customised' => new Reference('messenger.transport.failed'),
            'default' => new Reference('messenger.transport.failed'),
            'failed' => new Reference('messenger.transport.failed'),
            'redis' => new Reference('messenger.transport.failed'),
            'schedule' => new Reference('messenger.transport.failed'),
        ];

        $failureTransportsReferences = array_map(function (ServiceClosureArgument $serviceClosureArgument) {
            $values = $serviceClosureArgument->getValues();

            return array_shift($values);
        }, $failureTransports);
        $this->assertEquals($expectedTransportsByFailureTransports, $failureTransportsReferences);

        $rateLimitedTransports = $container->getDefinition('messenger.rate_limiter_locator')->getArgument(0);
        $expectedRateLimitersByRateLimitedTransports = [
            'customised' => new Reference('limiter.customised_worker'),
        ];
        $this->assertEquals($expectedRateLimitersByRateLimitedTransports, $rateLimitedTransports);
    }

    public function testMessengerRouting()
    {
        $container = $this->createContainerFromFile('messenger_routing');
        $senderLocatorDefinition = $container->getDefinition('messenger.senders_locator');

        $sendersMapping = $senderLocatorDefinition->getArgument(0);
        $this->assertEquals(['amqp', 'messenger.transport.audit'], $sendersMapping[DummyMessage::class]);
        $sendersLocator = $container->getDefinition((string) $senderLocatorDefinition->getArgument(1));
        $this->assertSame(['amqp', 'audit', 'messenger.transport.amqp', 'messenger.transport.audit'], array_keys($sendersLocator->getArgument(0)));
        $this->assertEquals(new Reference('messenger.transport.amqp'), $sendersLocator->getArgument(0)['amqp']->getValues()[0]);
        $this->assertEquals(new Reference('messenger.transport.audit'), $sendersLocator->getArgument(0)['messenger.transport.audit']->getValues()[0]);
    }

    public function testMessengerRoutingSingle()
    {
        $container = $this->createContainerFromFile('messenger_routing_single');
        $senderLocatorDefinition = $container->getDefinition('messenger.senders_locator');

        $sendersMapping = $senderLocatorDefinition->getArgument(0);
        $this->assertEquals(['amqp'], $sendersMapping[DummyMessage::class]);
    }

    public function testMessengerTransportConfiguration()
    {
        $container = $this->createContainerFromFile('messenger_transport');

        $this->assertSame('messenger.transport.symfony_serializer', (string) $container->getAlias('messenger.default_serializer'));

        $serializerTransportDefinition = $container->getDefinition('messenger.transport.symfony_serializer');
        $this->assertSame('csv', $serializerTransportDefinition->getArgument(1));
        $this->assertSame(['enable_max_depth' => true], $serializerTransportDefinition->getArgument(2));
    }

    public function testMessengerWithMultipleBuses()
    {
        $container = $this->createContainerFromFile('messenger_multiple_buses');

        $this->assertTrue($container->has('messenger.bus.commands'));
        $this->assertSame([], $container->getDefinition('messenger.bus.commands')->getArgument(0));
        $this->assertEquals([
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => ['messenger.bus.commands']],
            ['id' => 'reject_redelivered_message_middleware'],
            ['id' => 'dispatch_after_current_bus'],
            ['id' => 'failed_message_processing_middleware'],
            ['id' => 'send_message', 'arguments' => [true]],
            ['id' => 'handle_message', 'arguments' => [false]],
        ], $container->getParameter('messenger.bus.commands.middleware'));
        $this->assertTrue($container->has('messenger.bus.events'));
        $this->assertSame([], $container->getDefinition('messenger.bus.events')->getArgument(0));
        $this->assertEquals([
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => ['messenger.bus.events']],
            ['id' => 'reject_redelivered_message_middleware'],
            ['id' => 'dispatch_after_current_bus'],
            ['id' => 'failed_message_processing_middleware'],
            ['id' => 'with_factory', 'arguments' => ['foo', true, ['bar' => 'baz']]],
            ['id' => 'send_message', 'arguments' => [true]],
            ['id' => 'handle_message', 'arguments' => [false]],
        ], $container->getParameter('messenger.bus.events.middleware'));
        $this->assertTrue($container->has('messenger.bus.queries'));
        $this->assertSame([], $container->getDefinition('messenger.bus.queries')->getArgument(0));
        $this->assertEquals([
            ['id' => 'send_message', 'arguments' => []],
            ['id' => 'handle_message', 'arguments' => []],
        ], $container->getParameter('messenger.bus.queries.middleware'));

        $this->assertTrue($container->hasAlias('messenger.default_bus'));
        $this->assertSame('messenger.bus.commands', (string) $container->getAlias('messenger.default_bus'));
    }

    public function testMessengerMiddlewareFactoryErroneousFormat()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid middleware at path "framework.messenger": a map with a single factory id as key and its arguments as value was expected, {"foo":["qux"],"bar":["baz"]} given.');
        $this->createContainerFromFile('messenger_middleware_factory_erroneous_format');
    }

    public function testMessengerInvalidTransportRouting()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid Messenger routing configuration: invalid namespace "Symfony\*\DummyMessage" wildcard.');
        $this->createContainerFromFile('messenger_routing_invalid_wildcard');
    }

    public function testMessengerInvalidWildcardRouting()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid Messenger routing configuration: the "Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyMessage" class is being routed to a sender called "invalid". This is not a valid transport or service id.');
        $this->createContainerFromFile('messenger_routing_invalid_transport');
    }

    /**
     * @group legacy
     */
    public function testMessengerWithDisabledResetOnMessage()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "framework.messenger.reset_on_message" configuration option can be set to "true" only. To prevent services resetting after each message you can set the "--no-reset" option in "messenger:consume" command.');

        $this->createContainerFromFile('messenger_with_disabled_reset_on_message');
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
            fn ($directory) => !file_exists($directory)
        );

        $this->assertNotEmpty($nonExistingDirectories, 'FrameworkBundle should pass non existing directories to Translator');

        $this->assertSame('Fixtures/translations', $options['cache_vary']['scanned_directories'][3]);
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

    public function testValidation()
    {
        $container = $this->createContainerFromFile('full');
        $projectDir = $container->getParameter('kernel.project_dir');

        $ref = new \ReflectionClass(Form::class);
        $xmlMappings = [
            \dirname($ref->getFileName()).'/Resources/config/validation.xml',
            strtr($projectDir.'/config/validator/foo.xml', '/', \DIRECTORY_SEPARATOR),
        ];

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $annotations = !class_exists(FullStack::class) && class_exists(Annotation::class);

        $this->assertCount($annotations ? 8 : 6, $calls);
        $this->assertSame('setConstraintValidatorFactory', $calls[0][0]);
        $this->assertEquals([new Reference('validator.validator_factory')], $calls[0][1]);
        $this->assertSame('setTranslator', $calls[1][0]);
        $this->assertEquals([new Reference('translator', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE)], $calls[1][1]);
        $this->assertSame('setTranslationDomain', $calls[2][0]);
        $this->assertSame(['%validator.translation_domain%'], $calls[2][1]);
        $this->assertSame('addXmlMappings', $calls[3][0]);
        $this->assertSame([$xmlMappings], $calls[3][1]);
        $i = 3;
        if ($annotations) {
            $this->assertSame('enableAnnotationMapping', $calls[++$i][0]);
            $this->assertSame('setDoctrineAnnotationReader', $calls[++$i][0]);
        }
        $this->assertSame('addMethodMapping', $calls[++$i][0]);
        $this->assertSame(['loadValidatorMetadata'], $calls[$i][1]);
        $this->assertSame('setMappingCache', $calls[++$i][0]);
        $this->assertEquals([new Reference('validator.mapping.cache.adapter')], $calls[$i][1]);
    }

    public function testValidationService()
    {
        $container = $this->createContainerFromFile('validation_annotations', ['kernel.charset' => 'UTF-8'], false);

        $this->assertInstanceOf(ValidatorInterface::class, $container->get('validator.alias'));
    }

    public function testAnnotations()
    {
        $container = $this->createContainerFromFile('full', [], true, false);
        $container->addCompilerPass(new TestAnnotationsPass());
        $container->compile();

        $this->assertEquals($container->getParameter('kernel.cache_dir').'/annotations', $container->getDefinition('annotations.filesystem_cache_adapter')->getArgument(2));
        $this->assertSame('annotations.filesystem_cache_adapter', (string) $container->getDefinition('annotation_reader')->getArgument(1));
    }

    public function testFileLinkFormat()
    {
        if (\ini_get('xdebug.file_link_format') || get_cfg_var('xdebug.file_link_format')) {
            $this->markTestSkipped('A custom file_link_format is defined.');
        }

        $container = $this->createContainerFromFile('full');

        $this->assertEquals('file%link%format', $container->getParameter('debug.file_link_format'));
    }

    public function testValidationAnnotations()
    {
        $container = $this->createContainerFromFile('validation_annotations');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $this->assertCount(8, $calls);
        $this->assertSame('enableAnnotationMapping', $calls[4][0]);
        $this->assertSame('setDoctrineAnnotationReader', $calls[5][0]);
        $this->assertEquals([new Reference('annotation_reader')], $calls[5][1]);
        $this->assertSame('addMethodMapping', $calls[6][0]);
        $this->assertSame(['loadValidatorMetadata'], $calls[6][1]);
        $this->assertSame('setMappingCache', $calls[7][0]);
        $this->assertEquals([new Reference('validator.mapping.cache.adapter')], $calls[7][1]);
        // no cache this time
    }

    public function testValidationPaths()
    {
        require_once __DIR__.'/Fixtures/TestBundle/TestBundle.php';

        $container = $this->createContainerFromFile('validation_annotations', [
            'kernel.bundles' => ['TestBundle' => 'Symfony\\Bundle\\FrameworkBundle\\Tests\\TestBundle'],
            'kernel.bundles_metadata' => ['TestBundle' => ['namespace' => 'Symfony\\Bundle\\FrameworkBundle\\Tests', 'path' => __DIR__.'/Fixtures/TestBundle']],
        ]);

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $this->assertCount(9, $calls);
        $this->assertSame('addXmlMappings', $calls[3][0]);
        $this->assertSame('addYamlMappings', $calls[4][0]);
        $this->assertSame('enableAnnotationMapping', $calls[5][0]);
        $this->assertSame('setDoctrineAnnotationReader', $calls[6][0]);
        $this->assertSame('addMethodMapping', $calls[7][0]);
        $this->assertSame(['loadValidatorMetadata'], $calls[7][1]);
        $this->assertSame('setMappingCache', $calls[8][0]);
        $this->assertEquals([new Reference('validator.mapping.cache.adapter')], $calls[8][1]);

        $xmlMappings = $calls[3][1][0];
        $this->assertCount(3, $xmlMappings);
        try {
            // Testing symfony/symfony
            $this->assertStringEndsWith('Component'.\DIRECTORY_SEPARATOR.'Form/Resources/config/validation.xml', $xmlMappings[0]);
        } catch (\Exception $e) {
            // Testing symfony/framework-bundle with deps=high
            $this->assertStringEndsWith('symfony'.\DIRECTORY_SEPARATOR.'form/Resources/config/validation.xml', $xmlMappings[0]);
        }
        $this->assertStringEndsWith('TestBundle/Resources/config/validation.xml', $xmlMappings[1]);

        $yamlMappings = $calls[4][1][0];
        $this->assertCount(1, $yamlMappings);
        $this->assertStringEndsWith('TestBundle/Resources/config/validation.yml', $yamlMappings[0]);
    }

    public function testValidationPathsUsingCustomBundlePath()
    {
        require_once __DIR__.'/Fixtures/CustomPathBundle/src/CustomPathBundle.php';

        $container = $this->createContainerFromFile('validation_annotations', [
            'kernel.bundles' => ['CustomPathBundle' => 'Symfony\\Bundle\\FrameworkBundle\\Tests\\CustomPathBundle'],
            'kernel.bundles_metadata' => ['TestBundle' => ['namespace' => 'Symfony\\Bundle\\FrameworkBundle\\Tests', 'path' => __DIR__.'/Fixtures/CustomPathBundle']],
        ]);

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();
        $xmlMappings = $calls[3][1][0];
        $this->assertCount(3, $xmlMappings);

        try {
            // Testing symfony/symfony
            $this->assertStringEndsWith('Component'.\DIRECTORY_SEPARATOR.'Form/Resources/config/validation.xml', $xmlMappings[0]);
        } catch (\Exception $e) {
            // Testing symfony/framework-bundle with deps=high
            $this->assertStringEndsWith('symfony'.\DIRECTORY_SEPARATOR.'form/Resources/config/validation.xml', $xmlMappings[0]);
        }
        $this->assertStringEndsWith('CustomPathBundle/Resources/config/validation.xml', $xmlMappings[1]);

        $yamlMappings = $calls[4][1][0];
        $this->assertCount(1, $yamlMappings);
        $this->assertStringEndsWith('CustomPathBundle/Resources/config/validation.yml', $yamlMappings[0]);
    }

    public function testValidationNoStaticMethod()
    {
        $container = $this->createContainerFromFile('validation_no_static_method');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $annotations = !class_exists(FullStack::class) && class_exists(Annotation::class);

        $this->assertCount($annotations ? 7 : 5, $calls);
        $this->assertSame('addXmlMappings', $calls[3][0]);
        $i = 3;
        if ($annotations) {
            $this->assertSame('enableAnnotationMapping', $calls[++$i][0]);
            $this->assertSame('setDoctrineAnnotationReader', $calls[++$i][0]);
        }
        $this->assertSame('setMappingCache', $calls[++$i][0]);
        $this->assertEquals([new Reference('validator.mapping.cache.adapter')], $calls[$i][1]);
        // no cache, no annotations, no static methods
    }

    public function testEmailValidationModeIsPassedToEmailValidator()
    {
        $container = $this->createContainerFromFile('validation_email_validation_mode');

        $this->assertSame('html5', $container->getDefinition('validator.email')->getArgument(0));
    }

    public function testValidationTranslationDomain()
    {
        $container = $this->createContainerFromFile('validation_translation_domain');

        $this->assertSame('messages', $container->getParameter('validator.translation_domain'));
    }

    public function testValidationMapping()
    {
        $container = $this->createContainerFromFile('validation_mapping');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $this->assertSame('addXmlMappings', $calls[3][0]);
        $this->assertCount(3, $calls[3][1][0]);

        $this->assertSame('addYamlMappings', $calls[4][0]);
        $this->assertCount(3, $calls[4][1][0]);
        $this->assertStringContainsString('foo.yml', $calls[4][1][0][0]);
        $this->assertStringContainsString('validation.yml', $calls[4][1][0][1]);
        $this->assertStringContainsString('validation.yaml', $calls[4][1][0][2]);
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
        $this->assertEquals(AnnotationLoader::class, $argument[0]->getClass());
        $this->assertEquals(new Reference('serializer.name_converter.camel_case_to_snake_case'), $container->getDefinition('serializer.name_converter.metadata_aware')->getArgument(1));
        $this->assertEquals(new Reference('property_info', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE), $container->getDefinition('serializer.normalizer.object')->getArgument(3));
        $this->assertArrayHasKey('circular_reference_handler', $container->getDefinition('serializer.normalizer.object')->getArgument(6));
        $this->assertArrayHasKey('max_depth_handler', $container->getDefinition('serializer.normalizer.object')->getArgument(6));
        $this->assertEquals($container->getDefinition('serializer.normalizer.object')->getArgument(6)['max_depth_handler'], new Reference('my.max.depth.handler'));
    }

    public function testRegisterSerializerExtractor()
    {
        $container = $this->createContainerFromFile('full');

        $serializerExtractorDefinition = $container->getDefinition('property_info.serializer_extractor');

        $this->assertEquals('serializer.mapping.class_metadata_factory', $serializerExtractorDefinition->getArgument(0)->__toString());
        $this->assertTrue(!$serializerExtractorDefinition->isPublic() || $serializerExtractorDefinition->isPrivate());
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
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.object');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(ObjectNormalizer::class, $definition->getClass());
        $this->assertEquals(-1000, $tag[0]['priority']);
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

    public function testSerializerCacheActivated()
    {
        $container = $this->createContainerFromFile('serializer_enabled');

        $this->assertTrue($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));

        $cache = $container->getDefinition('serializer.mapping.cache_class_metadata_factory')->getArgument(1);
        $this->assertEquals(new Reference('serializer.mapping.cache.symfony'), $cache);
    }

    public function testSerializerCacheUsedWithoutAnnotationsAndMappingFiles()
    {
        $container = $this->createContainerFromFile('serializer_mapping_without_annotations', ['kernel.debug' => true, 'kernel.container_class' => __CLASS__]);
        $this->assertTrue($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));
    }

    public function testSerializerCacheNotActivatedWithAnnotations()
    {
        $container = $this->createContainerFromFile('serializer_mapping', ['kernel.debug' => true, 'kernel.container_class' => __CLASS__]);
        $this->assertFalse($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));
    }

    public function testSerializerMapping()
    {
        $container = $this->createContainerFromFile('serializer_mapping', ['kernel.bundles_metadata' => ['TestBundle' => ['namespace' => 'Symfony\\Bundle\\FrameworkBundle\\Tests', 'path' => __DIR__.'/Fixtures/TestBundle']]]);
        $projectDir = $container->getParameter('kernel.project_dir');
        $configDir = __DIR__.'/Fixtures/TestBundle/Resources/config';
        $expectedLoaders = [
            new Definition(AnnotationLoader::class, [new Reference('annotation_reader', ContainerInterface::NULL_ON_INVALID_REFERENCE)]),
            new Definition(XmlFileLoader::class, [$configDir.'/serialization.xml']),
            new Definition(YamlFileLoader::class, [$configDir.'/serialization.yml']),
            new Definition(YamlFileLoader::class, [$projectDir.'/config/serializer/foo.yml']),
            new Definition(XmlFileLoader::class, [$configDir.'/serializer_mapping/files/foo.xml']),
            new Definition(YamlFileLoader::class, [$configDir.'/serializer_mapping/files/foo.yml']),
            new Definition(YamlFileLoader::class, [$configDir.'/serializer_mapping/serialization.yml']),
            new Definition(YamlFileLoader::class, [$configDir.'/serializer_mapping/serialization.yaml']),
        ];

        foreach ($expectedLoaders as $definition) {
            if (is_file($arg = $definition->getArgument(0))) {
                $definition->replaceArgument(0, strtr($arg, '/', \DIRECTORY_SEPARATOR));
            }
            $definition->setPublic(false);
        }

        $loaders = $container->getDefinition('serializer.mapping.chain_loader')->getArgument(0);
        foreach ($loaders as $loader) {
            if (is_file($arg = $loader->getArgument(0))) {
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

    public function testPropertyInfoEnabled()
    {
        $container = $this->createContainerFromFile('property_info');
        $this->assertTrue($container->has('property_info'));
    }

    public function testPropertyInfoCacheActivated()
    {
        $container = $this->createContainerFromFile('property_info');

        $this->assertTrue($container->hasDefinition('property_info.cache'));

        $cache = $container->getDefinition('property_info.cache')->getArgument(1);
        $this->assertEquals(new Reference('cache.property_info'), $cache);
    }

    public function testPropertyInfoCacheDisabled()
    {
        $container = $this->createContainerFromFile('property_info', ['kernel.debug' => true, 'kernel.container_class' => __CLASS__]);
        $this->assertFalse($container->hasDefinition('property_info.cache'));
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

    public function testCacheDefaultRedisProvider()
    {
        $container = $this->createContainerFromFile('cache');

        $redisUrl = 'redis://localhost';
        $providerId = '.cache_connection.'.ContainerBuilder::hash($redisUrl);

        $this->assertTrue($container->hasDefinition($providerId));

        $url = $container->getDefinition($providerId)->getArgument(0);

        $this->assertSame($redisUrl, $url);
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
                    ->replaceArgument(0, new Reference('.cache_connection.kYdiLgf'))
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

        if (method_exists(TagAwareAdapter::class, 'setLogger')) {
            $this->assertEquals([
                ['setLogger', [new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]],
            ], $tagAwareDefinition->getMethodCalls());
            $this->assertSame([['channel' => 'cache']], $tagAwareDefinition->getTag('monolog.logger'));
        }
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
            $aliasesForArguments[] = sprintf('%s $%s', TagAwareCacheInterface::class, $argumentName);
            $aliasesForArguments[] = sprintf('%s $%s', CacheInterface::class, $argumentName);
            $aliasesForArguments[] = sprintf('%s $%s', CacheItemPoolInterface::class, $argumentName);
        }

        foreach ($aliasesForArguments as $aliasForArgumentStr) {
            $aliasForArgument = $container->getAlias($aliasForArgumentStr);
            $this->assertNotNull($aliasForArgument, sprintf("No alias found for '%s'", $aliasForArgumentStr));

            $def = $container->getDefinition((string) $aliasForArgument);
            $this->assertInstanceOf(ChildDefinition::class, $def, sprintf("No definition found for '%s'", $aliasForArgumentStr));

            $defParent = $container->getDefinition($def->getParent());
            if ($defParent instanceof ChildDefinition) {
                $defParent = $container->getDefinition($defParent->getParent());
            }

            $this->assertSame(RedisTagAwareAdapter::class, $defParent->getClass(), sprintf("'%s' is not %s", $aliasForArgumentStr, RedisTagAwareAdapter::class));
        }
    }

    /**
     * @dataProvider appRedisTagAwareConfigProvider
     */
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

    /**
     * @dataProvider appRedisTagAwareConfigProvider
     */
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

    public function testRemovesResourceCheckerConfigCacheFactoryArgumentOnlyIfNoDebug()
    {
        $container = $this->createContainer(['kernel.debug' => true]);
        (new FrameworkExtension())->load([['http_method_override' => false]], $container);
        $this->assertCount(1, $container->getDefinition('config_cache_factory')->getArguments());

        $container = $this->createContainer(['kernel.debug' => false]);
        (new FrameworkExtension())->load([['http_method_override' => false]], $container);
        $this->assertEmpty($container->getDefinition('config_cache_factory')->getArguments());
    }

    public function testLoggerAwareRegistration()
    {
        $container = $this->createContainerFromFile('full', [], true, false);
        $container->addCompilerPass(new ResolveInstanceofConditionalsPass());
        $container->register('foo', LoggerAwareInterface::class)
            ->setAutoconfigured(true);
        $container->compile();

        $calls = $container->findDefinition('foo')->getMethodCalls();

        $this->assertCount(1, $calls, 'Definition should contain 1 method call');
        $this->assertSame('setLogger', $calls[0][0], 'Method name should be "setLogger"');
        $this->assertInstanceOf(Reference::class, $calls[0][1][0]);
        $this->assertSame('logger', (string) $calls[0][1][0], 'Argument should be a reference to "logger"');
    }

    public function testSessionCookieSecureAuto()
    {
        $container = $this->createContainerFromFile('session_cookie_secure_auto');

        $expected = ['session_factory', 'logger', 'session_collector'];
        $this->assertEquals($expected, array_keys($container->getDefinition('session_listener')->getArgument(0)->getValues()));
    }

    public function testRobotsTagListenerIsRegisteredInDebugMode()
    {
        $container = $this->createContainer(['kernel.debug' => true]);
        (new FrameworkExtension())->load([['http_method_override' => false]], $container);
        $this->assertTrue($container->has('disallow_search_engine_index_response_listener'), 'DisallowRobotsIndexingListener should be registered');

        $definition = $container->getDefinition('disallow_search_engine_index_response_listener');
        $this->assertTrue($definition->hasTag('kernel.event_subscriber'), 'DisallowRobotsIndexingListener should have the correct tag');

        $container = $this->createContainer(['kernel.debug' => true]);
        (new FrameworkExtension())->load([['http_method_override' => false, 'disallow_search_engine_index' => false]], $container);
        $this->assertFalse(
            $container->has('disallow_search_engine_index_response_listener'),
            'DisallowRobotsIndexingListener should not be registered when explicitly disabled'
        );

        $container = $this->createContainer(['kernel.debug' => false]);
        (new FrameworkExtension())->load([['http_method_override' => false]], $container);
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

        $this->assertTrue($container->hasDefinition('foo'), 'should have the "foo" service.');
        $this->assertSame(ScopingHttpClient::class, $container->getDefinition('foo')->getClass());
    }

    public function testScopedHttpClientWithoutQueryOption()
    {
        $container = $this->createContainerFromFile('http_client_scoped_without_query_option');

        $this->assertTrue($container->hasDefinition('foo'), 'should have the "foo" service.');
        $this->assertSame(ScopingHttpClient::class, $container->getDefinition('foo')->getClass());
    }

    public function testHttpClientOverrideDefaultOptions()
    {
        $container = $this->createContainerFromFile('http_client_override_default_options');

        $this->assertSame(['foo' => 'bar'], $container->getDefinition('http_client.transport')->getArgument(0)['headers']);
        $this->assertSame(['foo' => 'bar'], $container->getDefinition('http_client.transport')->getArgument(0)['extra']);
        $this->assertSame(4, $container->getDefinition('http_client.transport')->getArgument(1));
        $this->assertSame('http://example.com', $container->getDefinition('foo')->getArgument(1));

        $expected = [
            'headers' => [
                'bar' => 'baz',
            ],
            'extra' => [
                'bar' => 'baz',
            ],
            'query' => [],
            'resolve' => [],
        ];
        $this->assertEquals($expected, $container->getDefinition('foo')->getArgument(2));
    }

    public function testHttpClientRetry()
    {
        if (!class_exists(RetryableHttpClient::class)) {
            $this->expectException(LogicException::class);
        }
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
        $this->assertSame($expected, $container->getDefinition('foo')->getArgument(2)['query']);

        $expected = [
            'host' => '127.0.0.1',
        ];
        $this->assertSame($expected, $container->getDefinition('foo')->getArgument(2)['resolve']);
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

    public static function provideMailer(): array
    {
        return [
            ['mailer_with_dsn', ['main' => 'smtp://example.com']],
            ['mailer_with_transports', [
                'transport1' => 'smtp://example1.com',
                'transport2' => 'smtp://example2.com',
            ]],
        ];
    }

    /**
     * @dataProvider provideMailer
     */
    public function testMailer(string $configFile, array $expectedTransports)
    {
        $container = $this->createContainerFromFile($configFile);

        $this->assertTrue($container->hasAlias('mailer'));
        $this->assertTrue($container->hasDefinition('mailer.transports'));
        $this->assertSame($expectedTransports, $container->getDefinition('mailer.transports')->getArgument(0));
        $this->assertTrue($container->hasDefinition('mailer.default_transport'));
        $this->assertSame(current($expectedTransports), $container->getDefinition('mailer.default_transport')->getArgument(0));
        $this->assertTrue($container->hasDefinition('mailer.envelope_listener'));
        $l = $container->getDefinition('mailer.envelope_listener');
        $this->assertSame('sender@example.org', $l->getArgument(0));
        $this->assertSame(['redirected@example.org', 'redirected1@example.org'], $l->getArgument(1));
        $this->assertEquals(new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition('mailer.mailer')->getArgument(1));

        $this->assertTrue($container->hasDefinition('mailer.message_listener'));
        $l = $container->getDefinition('mailer.message_listener');
        $h = $l->getArgument(0);
        $this->assertCount(3, $h->getMethodCalls());
    }

    public function testMailerWithDisabledMessageBus()
    {
        $container = $this->createContainerFromFile('mailer_with_disabled_message_bus');

        $this->assertNull($container->getDefinition('mailer.mailer')->getArgument(1));
    }

    public function testMailerWithSpecificMessageBus()
    {
        $container = $this->createContainerFromFile('mailer_with_specific_message_bus');

        $this->assertEquals(new Reference('app.another_bus'), $container->getDefinition('mailer.mailer')->getArgument(1));
    }

    public function testHttpClientMockResponseFactory()
    {
        $container = $this->createContainerFromFile('http_client_mock_response_factory');

        $definition = $container->getDefinition('http_client.mock_client');

        $this->assertSame(MockHttpClient::class, $definition->getClass());
        $this->assertCount(1, $definition->getArguments());

        $argument = $definition->getArgument(0);

        $this->assertInstanceOf(Reference::class, $argument);
        $this->assertSame('http_client.transport', current($definition->getDecoratedService()));
        $this->assertSame('my_response_factory', (string) $argument);
    }

    public function testRegisterParameterCollectingBehaviorDescribingTags()
    {
        $container = $this->createContainerFromFile('default_config');

        $this->assertTrue($container->hasParameter('container.behavior_describing_tags'));
        $this->assertEquals([
            'annotations.cached_reader',
            'container.do_not_inline',
            'container.service_locator',
            'container.service_subscriber',
            'kernel.event_subscriber',
            'kernel.event_listener',
            'kernel.locale_aware',
            'kernel.reset',
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
            $this->assertTrue($container->hasDefinition('notifier.transport_factory.'.$transportFactoryName), sprintf('Did you forget to add the "%s" TransportFactory to the $classToServices array in FrameworkExtension?', $bridgeDirectory->getFilename()));
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

        $localeAwareServices = array_map(fn (Reference $r) => (string) $r, $switcherDef->getArgument(1)->getValues());

        $this->assertNotContains('translation.locale_switcher', $localeAwareServices);
    }

    public function testHtmlSanitizer()
    {
        $container = $this->createContainerFromFile('html_sanitizer');

        // html_sanitizer service
        $this->assertSame(HtmlSanitizer::class, $container->getDefinition('html_sanitizer.sanitizer.custom')->getClass());
        $this->assertCount(1, $args = $container->getDefinition('html_sanitizer.sanitizer.custom')->getArguments());
        $this->assertSame('html_sanitizer.config.custom', (string) $args[0]);

        // config
        $this->assertTrue($container->hasDefinition('html_sanitizer.config.custom'), '->registerHtmlSanitizerConfiguration() loads custom sanitizer');
        $this->assertSame(HtmlSanitizerConfig::class, $container->getDefinition('html_sanitizer.config.custom')->getClass());
        $this->assertCount(23, $calls = $container->getDefinition('html_sanitizer.config.custom')->getMethodCalls());
        $this->assertSame(
            [
                ['allowSafeElements', [], true],
                ['allowStaticElements', [], true],
                ['allowElement', ['iframe', 'src'], true],
                ['allowElement', ['custom-tag', ['data-attr', 'data-attr-1']], true],
                ['allowElement', ['custom-tag-2', '*'], true],
                ['blockElement', ['section'], true],
                ['dropElement', ['video'], true],
                ['allowAttribute', ['src', $this instanceof XmlFrameworkExtensionTest ? 'iframe' : ['iframe']], true],
                ['allowAttribute', ['data-attr', '*'], true],
                ['dropAttribute', ['data-attr', $this instanceof XmlFrameworkExtensionTest ? 'custom-tag' : ['custom-tag']], true],
                ['dropAttribute', ['data-attr-1', []], true],
                ['dropAttribute', ['data-attr-2', '*'], true],
                ['forceAttribute', ['a', 'rel', 'noopener noreferrer'], true],
                ['forceAttribute', ['h1', 'class', 'bp4-heading'], true],
                ['forceHttpsUrls', [true], true],
                ['allowLinkSchemes', [['http', 'https', 'mailto']], true],
                ['allowLinkHosts', [['symfony.com']], true],
                ['allowRelativeLinks', [true], true],
                ['allowMediaSchemes', [['http', 'https', 'data']], true],
                ['allowMediaHosts', [['symfony.com']], true],
                ['allowRelativeMedias', [true], true],
                ['withAttributeSanitizer', ['@App\\Sanitizer\\CustomAttributeSanitizer'], true],
                ['withoutAttributeSanitizer', ['@App\\Sanitizer\\OtherCustomAttributeSanitizer'], true],
            ],

            // Convert references to their names for easier assertion
            array_map(
                static function ($call) {
                    foreach ($call[1] as $k => $arg) {
                        $call[1][$k] = $arg instanceof Reference ? '@'.$arg : $arg;
                    }

                    return $call;
                },
                $calls
            )
        );

        // Named alias
        $this->assertSame('html_sanitizer.sanitizer.all.sanitizer', (string) $container->getAlias(HtmlSanitizerInterface::class.' $allSanitizer'));
        $this->assertFalse($container->hasAlias(HtmlSanitizerInterface::class.' $default'));
    }

    public function testHtmlSanitizerDefaultNullAllowedLinkMediaHost()
    {
        $container = $this->createContainerFromFile('html_sanitizer_default_allowed_link_and_media_hosts');

        $calls = $container->getDefinition('html_sanitizer.config.custom_default')->getMethodCalls();
        $this->assertContains(['allowLinkHosts', [null], true], $calls);
        $this->assertContains(['allowRelativeLinks', [false], true], $calls);
        $this->assertContains(['allowMediaHosts', [null], true], $calls);
        $this->assertContains(['allowRelativeMedias', [false], true], $calls);
    }

    public function testHtmlSanitizerDefaultConfig()
    {
        $container = $this->createContainerFromFile('html_sanitizer_default_config');

        // html_sanitizer service
        $this->assertTrue($container->hasAlias('html_sanitizer'), '->registerHtmlSanitizerConfiguration() loads default_config');
        $this->assertSame('html_sanitizer.sanitizer.default', (string) $container->getAlias('html_sanitizer'));
        $this->assertSame(HtmlSanitizer::class, $container->getDefinition('html_sanitizer.sanitizer.default')->getClass());
        $this->assertCount(1, $args = $container->getDefinition('html_sanitizer.sanitizer.default')->getArguments());
        $this->assertSame('html_sanitizer.config.default', (string) $args[0]);

        // config
        $this->assertTrue($container->hasDefinition('html_sanitizer.config.default'), '->registerHtmlSanitizerConfiguration() loads custom sanitizer');
        $this->assertSame(HtmlSanitizerConfig::class, $container->getDefinition('html_sanitizer.config.default')->getClass());
        $this->assertCount(1, $calls = $container->getDefinition('html_sanitizer.config.default')->getMethodCalls());
        $this->assertSame(
            ['allowSafeElements', [], true],
            $calls[0]
        );

        // Named alias
        $this->assertFalse($container->hasAlias(HtmlSanitizerInterface::class.' $default'));

        // Default alias
        $this->assertSame('html_sanitizer', (string) $container->getAlias(HtmlSanitizerInterface::class));
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

    protected function createContainer(array $data = [])
    {
        return new ContainerBuilder(new EnvPlaceholderParameterBag(array_merge([
            'kernel.bundles' => ['FrameworkBundle' => FrameworkBundle::class],
            'kernel.bundles_metadata' => ['FrameworkBundle' => ['namespace' => 'Symfony\\Bundle\\FrameworkBundle', 'path' => __DIR__.'/../..']],
            'kernel.cache_dir' => __DIR__,
            'kernel.build_dir' => __DIR__,
            'kernel.project_dir' => __DIR__,
            'kernel.debug' => false,
            'kernel.environment' => 'test',
            'kernel.name' => 'kernel',
            'kernel.container_class' => 'testContainer',
            'container.build_hash' => 'Abc1234',
            'container.build_id' => hash('crc32', 'Abc123423456789'),
            'container.build_time' => 23456789,
        ], $data)));
    }

    protected function createContainerFromFile($file, $data = [], $resetCompilerPasses = true, $compile = true, FrameworkExtension $extension = null)
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
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([new LoggerPass()]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([new AddConstraintValidatorsPass(), new TranslatorPass()]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([new AddAnnotationsCachedReaderPass()]);

        if (!$compile) {
            return $container;
        }
        $container->compile();

        return self::$containerCache[$cacheKey] = $container;
    }

    protected function createContainerFromClosure($closure, $data = [])
    {
        $container = $this->createContainer($data);
        $container->registerExtension(new FrameworkExtension());
        $loader = new ClosureLoader($container);
        $loader->load($closure);

        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

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
        $this->assertTrue($container->has($id), sprintf('Service definition "%s" for cache pool of type "%s" is registered', $id, $adapter));

        $poolDefinition = $container->getDefinition($id);

        $this->assertInstanceOf(ChildDefinition::class, $poolDefinition, sprintf('Cache pool "%s" is based on an abstract cache pool.', $id));

        $this->assertTrue($poolDefinition->hasTag('cache.pool'), sprintf('Service definition "%s" is tagged with the "cache.pool" tag.', $id));
        $this->assertFalse($poolDefinition->isAbstract(), sprintf('Service definition "%s" is not abstract.', $id));

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

/**
 * Simulates ReplaceAliasByActualDefinitionPass.
 */
class TestAnnotationsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->setDefinition('annotation_reader', $container->getDefinition('annotations.cached_reader'));
        $container->removeDefinition('annotations.cached_reader');
    }
}
