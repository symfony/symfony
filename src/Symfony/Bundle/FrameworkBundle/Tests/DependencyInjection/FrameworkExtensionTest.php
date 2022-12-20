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
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyMessage;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FullStack;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\DoctrineAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
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
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\LoggerPass;
use Symfony\Component\HttpKernel\Fragment\FragmentUriGeneratorInterface;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\FormErrorNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Translation\DependencyInjection\TranslatorPass;
use Symfony\Component\Validator\DependencyInjection\AddConstraintValidatorsPass;
use Symfony\Component\Validator\Mapping\Loader\PropertyInfoLoader;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow;
use Symfony\Component\Workflow\Exception\InvalidDefinitionException;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\WorkflowEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

abstract class FrameworkExtensionTest extends TestCase
{
    use ExpectDeprecationTrait;

    private static $containerCache = [];

    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testFormCsrfProtection()
    {
        $container = $this->createContainerFromFile('full');

        $def = $container->getDefinition('form.type_extension.csrf');

        self::assertTrue($container->getParameter('form.type_extension.csrf.enabled'));
        self::assertEquals('%form.type_extension.csrf.enabled%', $def->getArgument(1));
        self::assertEquals('_csrf', $container->getParameter('form.type_extension.csrf.field_name'));
        self::assertEquals('%form.type_extension.csrf.field_name%', $def->getArgument(2));
    }

    public function testFormCsrfProtectionWithCsrfDisabled()
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('To use form CSRF protection, "framework.csrf_protection" must be enabled.');

        $this->createContainerFromFile('form_csrf_disabled');
    }

    public function testPropertyAccessWithDefaultValue()
    {
        $container = $this->createContainerFromFile('full');

        $def = $container->getDefinition('property_accessor');
        self::assertSame(PropertyAccessor::MAGIC_SET | PropertyAccessor::MAGIC_GET, $def->getArgument(0));
        self::assertSame(PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH, $def->getArgument(1));
    }

    public function testPropertyAccessWithOverriddenValues()
    {
        $container = $this->createContainerFromFile('property_accessor');
        $def = $container->getDefinition('property_accessor');
        self::assertSame(PropertyAccessor::MAGIC_GET | PropertyAccessor::MAGIC_CALL, $def->getArgument(0));
        self::assertSame(PropertyAccessor::THROW_ON_INVALID_INDEX, $def->getArgument(1));
    }

    public function testPropertyAccessCache()
    {
        $container = $this->createContainerFromFile('property_accessor');

        if (!method_exists(PropertyAccessor::class, 'createCache')) {
            self::assertFalse($container->hasDefinition('cache.property_access'));

            return;
        }

        $cache = $container->getDefinition('cache.property_access');
        self::assertSame([PropertyAccessor::class, 'createCache'], $cache->getFactory(), 'PropertyAccessor::createCache() should be used in non-debug mode');
        self::assertSame(AdapterInterface::class, $cache->getClass());
    }

    public function testPropertyAccessCacheWithDebug()
    {
        $container = $this->createContainerFromFile('property_accessor', ['kernel.debug' => true]);

        if (!method_exists(PropertyAccessor::class, 'createCache')) {
            self::assertFalse($container->hasDefinition('cache.property_access'));

            return;
        }

        $cache = $container->getDefinition('cache.property_access');
        self::assertNull($cache->getFactory());
        self::assertSame(ArrayAdapter::class, $cache->getClass(), 'ArrayAdapter should be used in debug mode');
    }

    public function testCsrfProtectionNeedsSessionToBeEnabled()
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('CSRF protection needs sessions to be enabled.');
        $this->createContainerFromFile('csrf_needs_session');
    }

    public function testCsrfProtectionForFormsEnablesCsrfProtectionAutomatically()
    {
        $container = $this->createContainerFromFile('csrf');

        self::assertTrue($container->hasDefinition('security.csrf.token_manager'));
    }

    public function testFormsCsrfIsEnabledByDefault()
    {
        if (class_exists(FullStack::class)) {
            self::markTestSkipped('testing with the FullStack prevents verifying default values');
        }
        $container = $this->createContainerFromFile('form_default_csrf');

        self::assertTrue($container->hasDefinition('security.csrf.token_manager'));
        self::assertTrue($container->hasParameter('form.type_extension.csrf.enabled'));
        self::assertTrue($container->getParameter('form.type_extension.csrf.enabled'));
    }

    public function testHttpMethodOverride()
    {
        $container = $this->createContainerFromFile('full');

        self::assertFalse($container->getParameter('kernel.http_method_override'));
    }

    public function testEsi()
    {
        $container = $this->createContainerFromFile('full');

        self::assertTrue($container->hasDefinition('esi'), '->registerEsiConfiguration() loads esi.xml');
        self::assertTrue($container->hasDefinition('fragment.renderer.esi'), 'The ESI fragment renderer is registered');
    }

    public function testEsiDisabled()
    {
        $container = $this->createContainerFromFile('esi_disabled');

        self::assertFalse($container->hasDefinition('fragment.renderer.esi'), 'The ESI fragment renderer is not registered');
        self::assertFalse($container->hasDefinition('esi'));
    }

    public function testFragmentsAndHinclude()
    {
        $container = $this->createContainerFromFile('fragments_and_hinclude');
        self::assertTrue($container->has('fragment.uri_generator'));
        self::assertTrue($container->hasAlias(FragmentUriGeneratorInterface::class));
        self::assertTrue($container->hasParameter('fragment.renderer.hinclude.global_template'));
        self::assertEquals('global_hinclude_template', $container->getParameter('fragment.renderer.hinclude.global_template'));
    }

    public function testSsi()
    {
        $container = $this->createContainerFromFile('full');

        self::assertTrue($container->hasDefinition('ssi'), '->registerSsiConfiguration() loads ssi.xml');
        self::assertTrue($container->hasDefinition('fragment.renderer.ssi'), 'The SSI fragment renderer is registered');
    }

    public function testSsiDisabled()
    {
        $container = $this->createContainerFromFile('ssi_disabled');

        self::assertFalse($container->hasDefinition('fragment.renderer.ssi'), 'The SSI fragment renderer is not registered');
        self::assertFalse($container->hasDefinition('ssi'));
    }

    public function testEsiAndSsiWithoutFragments()
    {
        $container = $this->createContainerFromFile('esi_and_ssi_without_fragments');

        self::assertFalse($container->hasDefinition('fragment.renderer.hinclude'), 'The HInclude fragment renderer is not registered');
        self::assertTrue($container->hasDefinition('fragment.renderer.esi'), 'The ESI fragment renderer is registered');
        self::assertTrue($container->hasDefinition('fragment.renderer.ssi'), 'The SSI fragment renderer is registered');
    }

    public function testEnabledProfiler()
    {
        $container = $this->createContainerFromFile('profiler');

        self::assertTrue($container->hasDefinition('profiler'), '->registerProfilerConfiguration() loads profiling.xml');
        self::assertTrue($container->hasDefinition('data_collector.config'), '->registerProfilerConfiguration() loads collectors.xml');
    }

    public function testDisabledProfiler()
    {
        $container = $this->createContainerFromFile('full');

        self::assertFalse($container->hasDefinition('profiler'), '->registerProfilerConfiguration() does not load profiling.xml');
        self::assertFalse($container->hasDefinition('data_collector.config'), '->registerProfilerConfiguration() does not load collectors.xml');
    }

    public function testWorkflows()
    {
        $container = $this->createContainerFromFile('workflows');

        self::assertTrue($container->hasDefinition('workflow.article'), 'Workflow is registered as a service');
        self::assertSame('workflow.abstract', $container->getDefinition('workflow.article')->getParent());

        $args = $container->getDefinition('workflow.article')->getArguments();
        self::assertArrayHasKey('index_0', $args);
        self::assertArrayHasKey('index_1', $args);
        self::assertArrayHasKey('index_3', $args);
        self::assertArrayHasKey('index_4', $args);
        self::assertNull($args['index_4'], 'Workflows has eventsToDispatch=null');

        self::assertTrue($container->hasDefinition('workflow.article.definition'), 'Workflow definition is registered as a service');

        $workflowDefinition = $container->getDefinition('workflow.article.definition');

        self::assertSame([
            'draft',
            'wait_for_journalist',
            'approved_by_journalist',
            'wait_for_spellchecker',
            'approved_by_spellchecker',
            'published',
        ], $workflowDefinition->getArgument(0), 'Places are passed to the workflow definition');
        self::assertCount(4, $workflowDefinition->getArgument(1));
        self::assertSame(['draft'], $workflowDefinition->getArgument(2));
        $metadataStoreDefinition = $container->getDefinition('workflow.article.metadata_store');
        self::assertSame(InMemoryMetadataStore::class, $metadataStoreDefinition->getClass());
        self::assertSame([
            'title' => 'article workflow',
            'description' => 'workflow for articles',
        ], $metadataStoreDefinition->getArgument(0));

        self::assertTrue($container->hasDefinition('state_machine.pull_request'), 'State machine is registered as a service');
        self::assertSame('state_machine.abstract', $container->getDefinition('state_machine.pull_request')->getParent());
        self::assertTrue($container->hasDefinition('state_machine.pull_request.definition'), 'State machine definition is registered as a service');

        $stateMachineDefinition = $container->getDefinition('state_machine.pull_request.definition');

        self::assertSame([
            'start',
            'coding',
            'travis',
            'review',
            'merged',
            'closed',
        ], $stateMachineDefinition->getArgument(0), 'Places are passed to the state machine definition');
        self::assertCount(9, $stateMachineDefinition->getArgument(1));
        self::assertSame(['start'], $stateMachineDefinition->getArgument(2));

        $metadataStoreReference = $stateMachineDefinition->getArgument(3);
        self::assertInstanceOf(Reference::class, $metadataStoreReference);
        self::assertSame('state_machine.pull_request.metadata_store', (string) $metadataStoreReference);

        $metadataStoreDefinition = $container->getDefinition('state_machine.pull_request.metadata_store');
        self::assertSame(Workflow\Metadata\InMemoryMetadataStore::class, $metadataStoreDefinition->getClass());
        self::assertSame(InMemoryMetadataStore::class, $metadataStoreDefinition->getClass());

        $workflowMetadata = $metadataStoreDefinition->getArgument(0);
        self::assertSame(['title' => 'workflow title'], $workflowMetadata);

        $placesMetadata = $metadataStoreDefinition->getArgument(1);
        self::assertArrayHasKey('start', $placesMetadata);
        self::assertSame(['title' => 'place start title'], $placesMetadata['start']);

        $transitionsMetadata = $metadataStoreDefinition->getArgument(2);
        self::assertSame(\SplObjectStorage::class, $transitionsMetadata->getClass());
        $transitionsMetadataCall = $transitionsMetadata->getMethodCalls()[0];
        self::assertSame('attach', $transitionsMetadataCall[0]);
        $params = $transitionsMetadataCall[1];
        self::assertCount(2, $params);
        self::assertInstanceOf(Reference::class, $params[0]);
        self::assertSame('.state_machine.pull_request.transition.0', (string) $params[0]);

        $serviceMarkingStoreWorkflowDefinition = $container->getDefinition('workflow.service_marking_store_workflow');
        /** @var Reference $markingStoreRef */
        $markingStoreRef = $serviceMarkingStoreWorkflowDefinition->getArgument(1);
        self::assertInstanceOf(Reference::class, $markingStoreRef);
        self::assertEquals('workflow_service', (string) $markingStoreRef);

        self::assertTrue($container->hasDefinition('workflow.registry'), 'Workflow registry is registered as a service');
        $registryDefinition = $container->getDefinition('workflow.registry');
        self::assertGreaterThan(0, \count($registryDefinition->getMethodCalls()));
    }

    public function testWorkflowAreValidated()
    {
        self::expectException(InvalidDefinitionException::class);
        self::expectExceptionMessage('A transition from a place/state must have an unique name. Multiple transitions named "go" from place/state "first" were found on StateMachine "my_workflow".');
        $this->createContainerFromFile('workflow_not_valid');
    }

    public function testWorkflowCannotHaveBothSupportsAndSupportStrategy()
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('"supports" and "support_strategy" cannot be used together.');
        $this->createContainerFromFile('workflow_with_support_and_support_strategy');
    }

    public function testWorkflowShouldHaveOneOfSupportsAndSupportStrategy()
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('"supports" or "support_strategy" should be configured.');
        $this->createContainerFromFile('workflow_without_support_and_support_strategy');
    }

    public function testWorkflowMultipleTransitionsWithSameName()
    {
        $container = $this->createContainerFromFile('workflow_with_multiple_transitions_with_same_name');

        self::assertTrue($container->hasDefinition('workflow.article'), 'Workflow is registered as a service');
        self::assertTrue($container->hasDefinition('workflow.article.definition'), 'Workflow definition is registered as a service');

        $workflowDefinition = $container->getDefinition('workflow.article.definition');

        $transitions = $workflowDefinition->getArgument(1);

        self::assertCount(5, $transitions);

        self::assertSame('.workflow.article.transition.0', (string) $transitions[0]);
        self::assertSame([
            'request_review',
            [
                'draft',
            ],
            [
                'wait_for_journalist', 'wait_for_spellchecker',
            ],
        ], $container->getDefinition($transitions[0])->getArguments());

        self::assertSame('.workflow.article.transition.1', (string) $transitions[1]);
        self::assertSame([
            'journalist_approval',
            [
                'wait_for_journalist',
            ],
            [
                'approved_by_journalist',
            ],
        ], $container->getDefinition($transitions[1])->getArguments());

        self::assertSame('.workflow.article.transition.2', (string) $transitions[2]);
        self::assertSame([
            'spellchecker_approval',
            [
                'wait_for_spellchecker',
            ],
            [
                'approved_by_spellchecker',
            ],
        ], $container->getDefinition($transitions[2])->getArguments());

        self::assertSame('.workflow.article.transition.3', (string) $transitions[3]);
        self::assertSame([
            'publish',
            [
                'approved_by_journalist',
                'approved_by_spellchecker',
            ],
            [
                'published',
            ],
        ], $container->getDefinition($transitions[3])->getArguments());

        self::assertSame('.workflow.article.transition.4', (string) $transitions[4]);
        self::assertSame([
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

        self::assertTrue($container->hasDefinition('.workflow.article.listener.guard'), 'Workflow guard listener is registered as a service');
        self::assertTrue($container->hasParameter('workflow.has_guard_listeners'), 'Workflow guard listeners parameter exists');
        self::assertTrue(true === $container->getParameter('workflow.has_guard_listeners'), 'Workflow guard listeners parameter is enabled');
        $guardDefinition = $container->getDefinition('.workflow.article.listener.guard');
        self::assertSame([
            [
                'event' => 'workflow.article.guard.publish',
                'method' => 'onTransition',
            ],
        ], $guardDefinition->getTag('kernel.event_listener'));
        $guardsConfiguration = $guardDefinition->getArgument(0);
        self::assertTrue(1 === \count($guardsConfiguration), 'Workflow guard configuration contains one element per transition name');
        $transitionGuardExpressions = $guardsConfiguration['workflow.article.guard.publish'];
        self::assertSame('.workflow.article.transition.3', (string) $transitionGuardExpressions[0]->getArgument(0));
        self::assertSame('!!true', $transitionGuardExpressions[0]->getArgument(1));
        self::assertSame('.workflow.article.transition.4', (string) $transitionGuardExpressions[1]->getArgument(0));
        self::assertSame('!!false', $transitionGuardExpressions[1]->getArgument(1));
    }

    public function testWorkflowServicesCanBeEnabled()
    {
        $container = $this->createContainerFromFile('workflows_enabled');

        self::assertTrue($container->has(Workflow\Registry::class));
        self::assertTrue($container->hasDefinition('console.command.workflow_dump'));
    }

    public function testWorkflowsExplicitlyEnabled()
    {
        $container = $this->createContainerFromFile('workflows_explicitly_enabled');

        self::assertTrue($container->hasDefinition('workflow.foo.definition'));
    }

    public function testWorkflowsNamedExplicitlyEnabled()
    {
        $container = $this->createContainerFromFile('workflows_explicitly_enabled_named_workflows');

        self::assertTrue($container->hasDefinition('workflow.workflows.definition'));
    }

    public function testWorkflowsWithNoDispatchedEvents()
    {
        $container = $this->createContainerFromFile('workflow_with_no_events_to_dispatch');

        $eventsToDispatch = $container->getDefinition('state_machine.my_workflow')->getArgument('index_4');

        self::assertSame([], $eventsToDispatch);
    }

    public function testWorkflowsWithSpecifiedDispatchedEvents()
    {
        $container = $this->createContainerFromFile('workflow_with_specified_events_to_dispatch');

        $eventsToDispatch = $container->getDefinition('state_machine.my_workflow')->getArgument('index_4');

        self::assertSame([WorkflowEvents::LEAVE, WorkflowEvents::COMPLETED], $eventsToDispatch);
    }

    public function testEnabledPhpErrorsConfig()
    {
        $container = $this->createContainerFromFile('php_errors_enabled');

        $definition = $container->getDefinition('debug.debug_handlers_listener');
        self::assertEquals(new Reference('monolog.logger.php', ContainerInterface::NULL_ON_INVALID_REFERENCE), $definition->getArgument(1));
        self::assertNull($definition->getArgument(2));
        self::assertSame(-1, $container->getParameter('debug.error_handler.throw_at'));
    }

    public function testDisabledPhpErrorsConfig()
    {
        $container = $this->createContainerFromFile('php_errors_disabled');

        $definition = $container->getDefinition('debug.debug_handlers_listener');
        self::assertNull($definition->getArgument(1));
        self::assertNull($definition->getArgument(2));
        self::assertSame(0, $container->getParameter('debug.error_handler.throw_at'));
    }

    public function testPhpErrorsWithLogLevel()
    {
        $container = $this->createContainerFromFile('php_errors_log_level');

        $definition = $container->getDefinition('debug.debug_handlers_listener');
        self::assertEquals(new Reference('monolog.logger.php', ContainerInterface::NULL_ON_INVALID_REFERENCE), $definition->getArgument(1));
        self::assertSame(8, $definition->getArgument(2));
    }

    public function testPhpErrorsWithLogLevels()
    {
        $container = $this->createContainerFromFile('php_errors_log_levels');

        $definition = $container->getDefinition('debug.debug_handlers_listener');
        self::assertEquals(new Reference('monolog.logger.php', ContainerInterface::NULL_ON_INVALID_REFERENCE), $definition->getArgument(1));
        self::assertSame([
            \E_NOTICE => \Psr\Log\LogLevel::ERROR,
            \E_WARNING => \Psr\Log\LogLevel::ERROR,
        ], $definition->getArgument(2));
    }

    public function testExceptionsConfig()
    {
        $container = $this->createContainerFromFile('exceptions');

        $configuration = $container->getDefinition('exception_listener')->getArgument(3);

        self::assertSame([
            \Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            \Symfony\Component\HttpKernel\Exception\ConflictHttpException::class,
            \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException::class,
        ], array_keys($configuration));

        self::assertEqualsCanonicalizing([
            'log_level' => 'info',
            'status_code' => 422,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class]);

        self::assertEqualsCanonicalizing([
            'log_level' => 'info',
            'status_code' => null,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class]);

        self::assertEqualsCanonicalizing([
            'log_level' => 'info',
            'status_code' => null,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class]);

        self::assertEqualsCanonicalizing([
            'log_level' => null,
            'status_code' => 500,
        ], $configuration[\Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException::class]);
    }

    public function testRouter()
    {
        $container = $this->createContainerFromFile('full');

        self::assertTrue($container->has('router'), '->registerRouterConfiguration() loads routing.xml');
        $arguments = $container->findDefinition('router')->getArguments();
        self::assertEquals($container->getParameter('kernel.project_dir').'/config/routing.xml', $container->getParameter('router.resource'), '->registerRouterConfiguration() sets routing resource');
        self::assertEquals('%router.resource%', $arguments[1], '->registerRouterConfiguration() sets routing resource');
        self::assertEquals('xml', $arguments[2]['resource_type'], '->registerRouterConfiguration() sets routing resource type');

        self::assertSame(['_locale' => 'fr|en'], $container->getDefinition('routing.loader')->getArgument(2));
    }

    /**
     * @group legacy
     */
    public function testRouterWithLegacyTranslatorEnabledLocales()
    {
        $container = $this->createContainerFromFile('legacy_translator_enabled_locales');

        self::assertTrue($container->has('router'), '->registerRouterConfiguration() loads routing.xml');
        $arguments = $container->findDefinition('router')->getArguments();
        self::assertEquals($container->getParameter('kernel.project_dir').'/config/routing.xml', $container->getParameter('router.resource'), '->registerRouterConfiguration() sets routing resource');
        self::assertEquals('%router.resource%', $arguments[1], '->registerRouterConfiguration() sets routing resource');
        self::assertEquals('xml', $arguments[2]['resource_type'], '->registerRouterConfiguration() sets routing resource type');

        self::assertSame(['_locale' => 'fr|en'], $container->getDefinition('routing.loader')->getArgument(2));
    }

    public function testRouterRequiresResourceOption()
    {
        self::expectException(InvalidConfigurationException::class);
        $container = $this->createContainer();
        $loader = new FrameworkExtension();
        $loader->load([['router' => true]], $container);
    }

    public function testSession()
    {
        $container = $this->createContainerFromFile('full');

        self::assertTrue($container->hasAlias(SessionInterface::class), '->registerSessionConfiguration() loads session.xml');
        self::assertEquals('fr', $container->getParameter('kernel.default_locale'));
        self::assertEquals('session.storage.factory.native', (string) $container->getAlias('session.storage.factory'));
        self::assertFalse($container->has('session.storage'));
        self::assertFalse($container->has('session.storage.native'));
        self::assertFalse($container->has('session.storage.php_bridge'));
        self::assertEquals('session.handler.native_file', (string) $container->getAlias('session.handler'));

        $options = $container->getParameter('session.storage.options');
        self::assertEquals('_SYMFONY', $options['name']);
        self::assertEquals(86400, $options['cookie_lifetime']);
        self::assertEquals('/', $options['cookie_path']);
        self::assertEquals('example.com', $options['cookie_domain']);
        self::assertTrue($options['cookie_secure']);
        self::assertFalse($options['cookie_httponly']);
        self::assertTrue($options['use_cookies']);
        self::assertEquals(108, $options['gc_divisor']);
        self::assertEquals(1, $options['gc_probability']);
        self::assertEquals(90000, $options['gc_maxlifetime']);
        self::assertEquals(22, $options['sid_length']);
        self::assertEquals(4, $options['sid_bits_per_character']);

        self::assertEquals('/path/to/sessions', $container->getParameter('session.save_path'));
    }

    public function testNullSessionHandler()
    {
        $container = $this->createContainerFromFile('session');

        self::assertTrue($container->hasAlias(SessionInterface::class), '->registerSessionConfiguration() loads session.xml');
        self::assertNull($container->getDefinition('session.storage.factory.native')->getArgument(1));
        self::assertNull($container->getDefinition('session.storage.factory.php_bridge')->getArgument(0));
        self::assertSame('session.handler.native_file', (string) $container->getAlias('session.handler'));

        $expected = ['session_factory', 'session', 'initialized_session', 'logger', 'session_collector'];
        self::assertEquals($expected, array_keys($container->getDefinition('session_listener')->getArgument(0)->getValues()));
        self::assertFalse($container->getDefinition('session.storage.factory.native')->getArgument(3));
    }

    /**
     * @group legacy
     */
    public function testNullSessionHandlerLegacy()
    {
        $this->expectDeprecation('Since symfony/framework-bundle 5.3: Not setting the "framework.session.storage_factory_id" configuration option is deprecated, it will default to "session.storage.factory.native" and will replace the "framework.session.storage_id" configuration option in version 6.0.');

        $container = $this->createContainerFromFile('session_legacy');

        self::assertTrue($container->hasAlias(SessionInterface::class), '->registerSessionConfiguration() loads session.xml');
        self::assertNull($container->getDefinition('session.storage.native')->getArgument(1));
        self::assertNull($container->getDefinition('session.storage.php_bridge')->getArgument(0));
        self::assertSame('session.handler.native_file', (string) $container->getAlias('session.handler'));

        $expected = ['session_factory', 'session', 'initialized_session', 'logger', 'session_collector'];
        self::assertEquals($expected, array_keys($container->getDefinition('session_listener')->getArgument(0)->getValues()));
        self::assertFalse($container->getDefinition('session.storage.factory.native')->getArgument(3));
    }

    public function testRequest()
    {
        $container = $this->createContainerFromFile('full');

        self::assertTrue($container->hasDefinition('request.add_request_formats_listener'), '->registerRequestConfiguration() loads request.xml');
        $listenerDef = $container->getDefinition('request.add_request_formats_listener');
        self::assertEquals(['csv' => ['text/csv', 'text/plain'], 'pdf' => ['application/pdf']], $listenerDef->getArgument(0));
    }

    public function testEmptyRequestFormats()
    {
        $container = $this->createContainerFromFile('request');

        self::assertFalse($container->hasDefinition('request.add_request_formats_listener'), '->registerRequestConfiguration() does not load request.xml when no request formats are defined');
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
        self::assertCount(10, $packageTags);

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
        self::assertEquals('assets.custom_version_strategy', (string) $package->getArgument(1));

        $package = $container->getDefinition((string) $packages['json_manifest_strategy']);
        $versionStrategy = $container->getDefinition((string) $package->getArgument(1));
        self::assertEquals('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        self::assertEquals('/path/to/manifest.json', $versionStrategy->getArgument(0));
        self::assertFalse($versionStrategy->getArgument(2));

        $package = $container->getDefinition($packages['remote_manifest']);
        $versionStrategy = $container->getDefinition($package->getArgument(1));
        self::assertSame('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        self::assertSame('https://cdn.example.com/manifest.json', $versionStrategy->getArgument(0));

        $package = $container->getDefinition($packages['var_manifest']);
        $versionStrategy = $container->getDefinition($package->getArgument(1));
        self::assertSame('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        self::assertSame('https://cdn.example.com/manifest.json', $versionStrategy->getArgument(0));
        self::assertFalse($versionStrategy->getArgument(2));

        $package = $container->getDefinition($packages['env_manifest']);
        $versionStrategy = $container->getDefinition($package->getArgument(1));
        self::assertSame('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        self::assertStringMatchesFormat('env_%s', $versionStrategy->getArgument(0));
        self::assertFalse($versionStrategy->getArgument(2));

        $package = $container->getDefinition((string) $packages['strict_manifest_strategy']);
        $versionStrategy = $container->getDefinition((string) $package->getArgument(1));
        self::assertEquals('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        self::assertEquals('/path/to/manifest.json', $versionStrategy->getArgument(0));
        self::assertTrue($versionStrategy->getArgument(2));
    }

    public function testAssetsDefaultVersionStrategyAsService()
    {
        $container = $this->createContainerFromFile('assets_version_strategy_as_service');
        $packages = $container->getDefinition('assets.packages');

        // default package
        $defaultPackage = $container->getDefinition((string) $packages->getArgument(0));
        self::assertEquals('assets.custom_version_strategy', (string) $defaultPackage->getArgument(1));
    }

    public function testWebLink()
    {
        $container = $this->createContainerFromFile('web_link');
        self::assertTrue($container->hasDefinition('web_link.add_link_header_listener'));
    }

    public function testMessengerServicesRemovedWhenDisabled()
    {
        $container = $this->createContainerFromFile('messenger_disabled');
        self::assertFalse($container->hasDefinition('console.command.messenger_consume_messages'));
        self::assertFalse($container->hasDefinition('console.command.messenger_debug'));
        self::assertFalse($container->hasDefinition('console.command.messenger_stop_workers'));
        self::assertFalse($container->hasDefinition('console.command.messenger_setup_transports'));
        self::assertFalse($container->hasDefinition('console.command.messenger_failed_messages_retry'));
        self::assertFalse($container->hasDefinition('console.command.messenger_failed_messages_show'));
        self::assertFalse($container->hasDefinition('console.command.messenger_failed_messages_remove'));
        self::assertFalse($container->hasDefinition('cache.messenger.restart_workers_signal'));
    }

    /**
     * @group legacy
     */
    public function testMessengerWithoutResetOnMessageLegacy()
    {
        $this->expectDeprecation('Since symfony/framework-bundle 5.4: Not setting the "framework.messenger.reset_on_message" configuration option is deprecated, it will default to "true" in version 6.0.');

        $container = $this->createContainerFromFile('messenger_without_reset_on_message_legacy');

        self::assertTrue($container->hasDefinition('console.command.messenger_consume_messages'));
        self::assertTrue($container->hasAlias('messenger.default_bus'));
        self::assertTrue($container->getAlias('messenger.default_bus')->isPublic());
        self::assertTrue($container->hasDefinition('messenger.transport.amqp.factory'));
        self::assertTrue($container->hasDefinition('messenger.transport.redis.factory'));
        self::assertTrue($container->hasDefinition('messenger.transport_factory'));
        self::assertSame(TransportFactory::class, $container->getDefinition('messenger.transport_factory')->getClass());
        self::assertFalse($container->hasDefinition('messenger.listener.reset_services'));
        self::assertNull($container->getDefinition('console.command.messenger_consume_messages')->getArgument(5));
    }

    public function testMessenger()
    {
        $container = $this->createContainerFromFile('messenger');
        self::assertTrue($container->hasDefinition('console.command.messenger_consume_messages'));
        self::assertTrue($container->hasAlias('messenger.default_bus'));
        self::assertTrue($container->getAlias('messenger.default_bus')->isPublic());
        self::assertTrue($container->hasDefinition('messenger.transport.amqp.factory'));
        self::assertTrue($container->hasDefinition('messenger.transport.redis.factory'));
        self::assertTrue($container->hasDefinition('messenger.transport_factory'));
        self::assertSame(TransportFactory::class, $container->getDefinition('messenger.transport_factory')->getClass());
        self::assertTrue($container->hasDefinition('messenger.listener.reset_services'));
        self::assertSame('messenger.listener.reset_services', (string) $container->getDefinition('console.command.messenger_consume_messages')->getArgument(5));
    }

    public function testMessengerWithoutConsole()
    {
        $extension = self::createPartialMock(FrameworkExtension::class, ['hasConsole', 'getAlias']);
        $extension->method('hasConsole')->willReturn(false);
        $extension->method('getAlias')->willReturn((new FrameworkExtension())->getAlias());

        $container = $this->createContainerFromFile('messenger', [], true, false, $extension);
        $container->compile();

        self::assertFalse($container->hasDefinition('console.command.messenger_consume_messages'));
        self::assertTrue($container->hasAlias('messenger.default_bus'));
        self::assertTrue($container->getAlias('messenger.default_bus')->isPublic());
        self::assertTrue($container->hasDefinition('messenger.transport.amqp.factory'));
        self::assertTrue($container->hasDefinition('messenger.transport.redis.factory'));
        self::assertTrue($container->hasDefinition('messenger.transport_factory'));
        self::assertSame(TransportFactory::class, $container->getDefinition('messenger.transport_factory')->getClass());
        self::assertFalse($container->hasDefinition('messenger.listener.reset_services'));
    }

    public function testMessengerMultipleFailureTransports()
    {
        $container = $this->createContainerFromFile('messenger_multiple_failure_transports');

        $failureTransport1Definition = $container->getDefinition('messenger.transport.failure_transport_1');
        $failureTransport1Tags = $failureTransport1Definition->getTag('messenger.receiver')[0];

        self::assertEquals([
            'alias' => 'failure_transport_1',
            'is_failure_transport' => true,
        ], $failureTransport1Tags);

        $failureTransport3Definition = $container->getDefinition('messenger.transport.failure_transport_3');
        $failureTransport3Tags = $failureTransport3Definition->getTag('messenger.receiver')[0];

        self::assertEquals([
            'alias' => 'failure_transport_3',
            'is_failure_transport' => true,
        ], $failureTransport3Tags);

        // transport 2 exists but does not appear in the mapping
        self::assertFalse($container->hasDefinition('messenger.transport.failure_transport_2'));

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
        self::assertEquals($expectedTransportsByFailureTransports, $failureTransportsReferences);
    }

    public function testMessengerMultipleFailureTransportsWithGlobalFailureTransport()
    {
        $container = $this->createContainerFromFile('messenger_multiple_failure_transports_global');

        self::assertEquals('messenger.transport.failure_transport_global', (string) $container->getAlias('messenger.failure_transports.default'));

        $failureTransport1Definition = $container->getDefinition('messenger.transport.failure_transport_1');
        $failureTransport1Tags = $failureTransport1Definition->getTag('messenger.receiver')[0];

        self::assertEquals([
            'alias' => 'failure_transport_1',
            'is_failure_transport' => true,
        ], $failureTransport1Tags);

        $failureTransport3Definition = $container->getDefinition('messenger.transport.failure_transport_3');
        $failureTransport3Tags = $failureTransport3Definition->getTag('messenger.receiver')[0];

        self::assertEquals([
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
        self::assertEquals($expectedTransportsByFailureTransports, $failureTransportsReferences);
    }

    public function testMessengerTransports()
    {
        $container = $this->createContainerFromFile('messenger_transports');
        self::assertTrue($container->hasDefinition('messenger.transport.default'));
        self::assertTrue($container->getDefinition('messenger.transport.default')->hasTag('messenger.receiver'));
        self::assertEquals([
            ['alias' => 'default', 'is_failure_transport' => false], ], $container->getDefinition('messenger.transport.default')->getTag('messenger.receiver'));
        $transportArguments = $container->getDefinition('messenger.transport.default')->getArguments();
        self::assertEquals(new Reference('messenger.default_serializer'), $transportArguments[2]);

        self::assertTrue($container->hasDefinition('messenger.transport.customised'));
        $transportFactory = $container->getDefinition('messenger.transport.customised')->getFactory();
        $transportArguments = $container->getDefinition('messenger.transport.customised')->getArguments();

        self::assertEquals([new Reference('messenger.transport_factory'), 'createTransport'], $transportFactory);
        self::assertCount(3, $transportArguments);
        self::assertSame('amqp://localhost/%2f/messages?exchange_name=exchange_name', $transportArguments[0]);
        self::assertEquals(['queue' => ['name' => 'Queue'], 'transport_name' => 'customised'], $transportArguments[1]);
        self::assertEquals(new Reference('messenger.transport.native_php_serializer'), $transportArguments[2]);

        self::assertTrue($container->hasDefinition('messenger.transport.amqp.factory'));

        self::assertTrue($container->hasDefinition('messenger.transport.redis'));
        $transportFactory = $container->getDefinition('messenger.transport.redis')->getFactory();
        $transportArguments = $container->getDefinition('messenger.transport.redis')->getArguments();

        self::assertEquals([new Reference('messenger.transport_factory'), 'createTransport'], $transportFactory);
        self::assertCount(3, $transportArguments);
        self::assertSame('redis://127.0.0.1:6379/messages', $transportArguments[0]);

        self::assertTrue($container->hasDefinition('messenger.transport.redis.factory'));

        self::assertTrue($container->hasDefinition('messenger.transport.beanstalkd'));
        $transportFactory = $container->getDefinition('messenger.transport.beanstalkd')->getFactory();
        $transportArguments = $container->getDefinition('messenger.transport.beanstalkd')->getArguments();

        self::assertEquals([new Reference('messenger.transport_factory'), 'createTransport'], $transportFactory);
        self::assertCount(3, $transportArguments);
        self::assertSame('beanstalkd://127.0.0.1:11300', $transportArguments[0]);

        self::assertTrue($container->hasDefinition('messenger.transport.beanstalkd.factory'));

        self::assertSame(10, $container->getDefinition('messenger.retry.multiplier_retry_strategy.customised')->getArgument(0));
        self::assertSame(7, $container->getDefinition('messenger.retry.multiplier_retry_strategy.customised')->getArgument(1));
        self::assertSame(3, $container->getDefinition('messenger.retry.multiplier_retry_strategy.customised')->getArgument(2));
        self::assertSame(100, $container->getDefinition('messenger.retry.multiplier_retry_strategy.customised')->getArgument(3));

        $failureTransportsByTransportNameServiceLocator = $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')->getArgument(0);
        $failureTransports = $container->getDefinition((string) $failureTransportsByTransportNameServiceLocator)->getArgument(0);
        $expectedTransportsByFailureTransports = [
            'beanstalkd' => new Reference('messenger.transport.failed'),
            'customised' => new Reference('messenger.transport.failed'),
            'default' => new Reference('messenger.transport.failed'),
            'failed' => new Reference('messenger.transport.failed'),
            'redis' => new Reference('messenger.transport.failed'),
        ];

        $failureTransportsReferences = array_map(function (ServiceClosureArgument $serviceClosureArgument) {
            $values = $serviceClosureArgument->getValues();

            return array_shift($values);
        }, $failureTransports);
        self::assertEquals($expectedTransportsByFailureTransports, $failureTransportsReferences);
    }

    public function testMessengerRouting()
    {
        $container = $this->createContainerFromFile('messenger_routing');
        $senderLocatorDefinition = $container->getDefinition('messenger.senders_locator');

        $sendersMapping = $senderLocatorDefinition->getArgument(0);
        self::assertEquals(['amqp', 'messenger.transport.audit'], $sendersMapping[DummyMessage::class]);
        $sendersLocator = $container->getDefinition((string) $senderLocatorDefinition->getArgument(1));
        self::assertSame(['amqp', 'audit', 'messenger.transport.amqp', 'messenger.transport.audit'], array_keys($sendersLocator->getArgument(0)));
        self::assertEquals(new Reference('messenger.transport.amqp'), $sendersLocator->getArgument(0)['amqp']->getValues()[0]);
        self::assertEquals(new Reference('messenger.transport.audit'), $sendersLocator->getArgument(0)['messenger.transport.audit']->getValues()[0]);
    }

    public function testMessengerRoutingSingle()
    {
        $container = $this->createContainerFromFile('messenger_routing_single');
        $senderLocatorDefinition = $container->getDefinition('messenger.senders_locator');

        $sendersMapping = $senderLocatorDefinition->getArgument(0);
        self::assertEquals(['amqp'], $sendersMapping[DummyMessage::class]);
    }

    public function testMessengerTransportConfiguration()
    {
        $container = $this->createContainerFromFile('messenger_transport');

        self::assertSame('messenger.transport.symfony_serializer', (string) $container->getAlias('messenger.default_serializer'));

        $serializerTransportDefinition = $container->getDefinition('messenger.transport.symfony_serializer');
        self::assertSame('csv', $serializerTransportDefinition->getArgument(1));
        self::assertSame(['enable_max_depth' => true], $serializerTransportDefinition->getArgument(2));
    }

    public function testMessengerWithMultipleBuses()
    {
        $container = $this->createContainerFromFile('messenger_multiple_buses');

        self::assertTrue($container->has('messenger.bus.commands'));
        self::assertSame([], $container->getDefinition('messenger.bus.commands')->getArgument(0));
        self::assertEquals([
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => ['messenger.bus.commands']],
            ['id' => 'reject_redelivered_message_middleware'],
            ['id' => 'dispatch_after_current_bus'],
            ['id' => 'failed_message_processing_middleware'],
            ['id' => 'send_message'],
            ['id' => 'handle_message'],
        ], $container->getParameter('messenger.bus.commands.middleware'));
        self::assertTrue($container->has('messenger.bus.events'));
        self::assertSame([], $container->getDefinition('messenger.bus.events')->getArgument(0));
        self::assertEquals([
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => ['messenger.bus.events']],
            ['id' => 'reject_redelivered_message_middleware'],
            ['id' => 'dispatch_after_current_bus'],
            ['id' => 'failed_message_processing_middleware'],
            ['id' => 'with_factory', 'arguments' => ['foo', true, ['bar' => 'baz']]],
            ['id' => 'send_message'],
            ['id' => 'handle_message'],
        ], $container->getParameter('messenger.bus.events.middleware'));
        self::assertTrue($container->has('messenger.bus.queries'));
        self::assertSame([], $container->getDefinition('messenger.bus.queries')->getArgument(0));
        self::assertEquals([
            ['id' => 'send_message', 'arguments' => []],
            ['id' => 'handle_message', 'arguments' => []],
        ], $container->getParameter('messenger.bus.queries.middleware'));

        self::assertTrue($container->hasAlias('messenger.default_bus'));
        self::assertSame('messenger.bus.commands', (string) $container->getAlias('messenger.default_bus'));
    }

    public function testMessengerMiddlewareFactoryErroneousFormat()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid middleware at path "framework.messenger": a map with a single factory id as key and its arguments as value was expected, {"foo":["qux"],"bar":["baz"]} given.');
        $this->createContainerFromFile('messenger_middleware_factory_erroneous_format');
    }

    public function testMessengerInvalidTransportRouting()
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Invalid Messenger routing configuration: the "Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyMessage" class is being routed to a sender called "invalid". This is not a valid transport or service id.');
        $this->createContainerFromFile('messenger_routing_invalid_transport');
    }

    public function testMessengerWithDisabledResetOnMessage()
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage('The "framework.messenger.reset_on_message" configuration option can be set to "true" only. To prevent services resetting after each message you can set the "--no-reset" option in "messenger:consume" command.');

        $this->createContainerFromFile('messenger_with_disabled_reset_on_message');
    }

    public function testTranslator()
    {
        $container = $this->createContainerFromFile('full');
        self::assertTrue($container->hasDefinition('translator.default'), '->registerTranslatorConfiguration() loads translation.php');
        self::assertEquals('translator.default', (string) $container->getAlias('translator'), '->registerTranslatorConfiguration() redefines translator service from identity to real translator');
        $options = $container->getDefinition('translator.default')->getArgument(4);

        self::assertArrayHasKey('cache_dir', $options);
        self::assertSame($container->getParameter('kernel.cache_dir').'/translations', $options['cache_dir']);

        $files = array_map('realpath', $options['resource_files']['en']);
        $ref = new \ReflectionClass(Validation::class);
        self::assertContains(strtr(\dirname($ref->getFileName()).'/Resources/translations/validators.en.xlf', '/', \DIRECTORY_SEPARATOR), $files, '->registerTranslatorConfiguration() finds Validator translation resources');
        $ref = new \ReflectionClass(Form::class);
        self::assertContains(strtr(\dirname($ref->getFileName()).'/Resources/translations/validators.en.xlf', '/', \DIRECTORY_SEPARATOR), $files, '->registerTranslatorConfiguration() finds Form translation resources');
        $ref = new \ReflectionClass(Security::class);
        self::assertContains(strtr(\dirname($ref->getFileName()).'/Resources/translations/security.en.xlf', '/', \DIRECTORY_SEPARATOR), $files, '->registerTranslatorConfiguration() finds Security translation resources');
        self::assertContains(strtr(__DIR__.'/Fixtures/translations/test_paths.en.yml', '/', \DIRECTORY_SEPARATOR), $files, '->registerTranslatorConfiguration() finds translation resources in custom paths');
        self::assertContains(strtr(__DIR__.'/translations/test_default.en.xlf', '/', \DIRECTORY_SEPARATOR), $files, '->registerTranslatorConfiguration() finds translation resources in default path');
        self::assertContains(strtr(__DIR__.'/Fixtures/translations/domain.with.dots.en.yml', '/', \DIRECTORY_SEPARATOR), $files, '->registerTranslatorConfiguration() finds translation resources with dots in domain');
        self::assertContains(strtr(__DIR__.'/translations/security.en.yaml', '/', \DIRECTORY_SEPARATOR), $files);

        $positionOverridingTranslationFile = array_search(strtr(realpath(__DIR__.'/translations/security.en.yaml'), '/', \DIRECTORY_SEPARATOR), $files);

        if (false !== $positionCoreTranslationFile = array_search(strtr(realpath(__DIR__.'/../../../../Component/Security/Core/Resources/translations/security.en.xlf'), '/', \DIRECTORY_SEPARATOR), $files)) {
            self::assertContains(strtr(realpath(__DIR__.'/../../../../Component/Security/Core/Resources/translations/security.en.xlf'), '/', \DIRECTORY_SEPARATOR), $files);
        } else {
            self::assertContains(strtr(realpath(__DIR__.'/../../vendor/symfony/security-core/Resources/translations/security.en.xlf'), '/', \DIRECTORY_SEPARATOR), $files);

            $positionCoreTranslationFile = array_search(strtr(realpath(__DIR__.'/../../vendor/symfony/security-core/Resources/translations/security.en.xlf'), '/', \DIRECTORY_SEPARATOR), $files);
        }

        self::assertGreaterThan($positionCoreTranslationFile, $positionOverridingTranslationFile);

        $calls = $container->getDefinition('translator.default')->getMethodCalls();
        self::assertEquals(['fr'], $calls[1][1][0]);

        $nonExistingDirectories = array_filter(
            $options['scanned_directories'],
            function ($directory) {
                return !file_exists($directory);
            }
        );

        self::assertNotEmpty($nonExistingDirectories, 'FrameworkBundle should pass non existing directories to Translator');

        self::assertSame('Fixtures/translations', $options['cache_vary']['scanned_directories'][3]);
    }

    public function testTranslatorMultipleFallbacks()
    {
        $container = $this->createContainerFromFile('translator_fallbacks');

        $calls = $container->getDefinition('translator.default')->getMethodCalls();
        self::assertEquals(['en', 'fr'], $calls[1][1][0]);
    }

    public function testTranslatorCacheDirDisabled()
    {
        $container = $this->createContainerFromFile('translator_cache_dir_disabled');
        $options = $container->getDefinition('translator.default')->getArgument(4);
        self::assertNull($options['cache_dir']);
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

        self::assertCount($annotations ? 8 : 6, $calls);
        self::assertSame('setConstraintValidatorFactory', $calls[0][0]);
        self::assertEquals([new Reference('validator.validator_factory')], $calls[0][1]);
        self::assertSame('setTranslator', $calls[1][0]);
        self::assertEquals([new Reference('translator', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE)], $calls[1][1]);
        self::assertSame('setTranslationDomain', $calls[2][0]);
        self::assertSame(['%validator.translation_domain%'], $calls[2][1]);
        self::assertSame('addXmlMappings', $calls[3][0]);
        self::assertSame([$xmlMappings], $calls[3][1]);
        $i = 3;
        if ($annotations) {
            self::assertSame('enableAnnotationMapping', $calls[++$i][0]);
            self::assertSame('setDoctrineAnnotationReader', $calls[++$i][0]);
        }
        self::assertSame('addMethodMapping', $calls[++$i][0]);
        self::assertSame(['loadValidatorMetadata'], $calls[$i][1]);
        self::assertSame('setMappingCache', $calls[++$i][0]);
        self::assertEquals([new Reference('validator.mapping.cache.adapter')], $calls[$i][1]);
    }

    public function testValidationService()
    {
        $container = $this->createContainerFromFile('validation_annotations', ['kernel.charset' => 'UTF-8'], false);

        self::assertInstanceOf(ValidatorInterface::class, $container->get('validator.alias'));
    }

    public function testAnnotations()
    {
        $container = $this->createContainerFromFile('full', [], true, false);
        $container->addCompilerPass(new TestAnnotationsPass());
        $container->compile();

        self::assertEquals($container->getParameter('kernel.cache_dir').'/annotations', $container->getDefinition('annotations.filesystem_cache_adapter')->getArgument(2));
        self::assertSame('annotations.filesystem_cache_adapter', (string) $container->getDefinition('annotation_reader')->getArgument(1));
    }

    public function testFileLinkFormat()
    {
        if (\ini_get('xdebug.file_link_format') || get_cfg_var('xdebug.file_link_format')) {
            self::markTestSkipped('A custom file_link_format is defined.');
        }

        $container = $this->createContainerFromFile('full');

        self::assertEquals('file%link%format', $container->getParameter('debug.file_link_format'));
    }

    public function testValidationAnnotations()
    {
        $container = $this->createContainerFromFile('validation_annotations');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        self::assertCount(8, $calls);
        self::assertSame('enableAnnotationMapping', $calls[4][0]);
        self::assertSame('setDoctrineAnnotationReader', $calls[5][0]);
        self::assertEquals([new Reference('annotation_reader')], $calls[5][1]);
        self::assertSame('addMethodMapping', $calls[6][0]);
        self::assertSame(['loadValidatorMetadata'], $calls[6][1]);
        self::assertSame('setMappingCache', $calls[7][0]);
        self::assertEquals([new Reference('validator.mapping.cache.adapter')], $calls[7][1]);
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

        self::assertCount(9, $calls);
        self::assertSame('addXmlMappings', $calls[3][0]);
        self::assertSame('addYamlMappings', $calls[4][0]);
        self::assertSame('enableAnnotationMapping', $calls[5][0]);
        self::assertSame('setDoctrineAnnotationReader', $calls[6][0]);
        self::assertSame('addMethodMapping', $calls[7][0]);
        self::assertSame(['loadValidatorMetadata'], $calls[7][1]);
        self::assertSame('setMappingCache', $calls[8][0]);
        self::assertEquals([new Reference('validator.mapping.cache.adapter')], $calls[8][1]);

        $xmlMappings = $calls[3][1][0];
        self::assertCount(3, $xmlMappings);
        try {
            // Testing symfony/symfony
            self::assertStringEndsWith('Component'.\DIRECTORY_SEPARATOR.'Form/Resources/config/validation.xml', $xmlMappings[0]);
        } catch (\Exception $e) {
            // Testing symfony/framework-bundle with deps=high
            self::assertStringEndsWith('symfony'.\DIRECTORY_SEPARATOR.'form/Resources/config/validation.xml', $xmlMappings[0]);
        }
        self::assertStringEndsWith('TestBundle/Resources/config/validation.xml', $xmlMappings[1]);

        $yamlMappings = $calls[4][1][0];
        self::assertCount(1, $yamlMappings);
        self::assertStringEndsWith('TestBundle/Resources/config/validation.yml', $yamlMappings[0]);
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
        self::assertCount(3, $xmlMappings);

        try {
            // Testing symfony/symfony
            self::assertStringEndsWith('Component'.\DIRECTORY_SEPARATOR.'Form/Resources/config/validation.xml', $xmlMappings[0]);
        } catch (\Exception $e) {
            // Testing symfony/framework-bundle with deps=high
            self::assertStringEndsWith('symfony'.\DIRECTORY_SEPARATOR.'form/Resources/config/validation.xml', $xmlMappings[0]);
        }
        self::assertStringEndsWith('CustomPathBundle/Resources/config/validation.xml', $xmlMappings[1]);

        $yamlMappings = $calls[4][1][0];
        self::assertCount(1, $yamlMappings);
        self::assertStringEndsWith('CustomPathBundle/Resources/config/validation.yml', $yamlMappings[0]);
    }

    public function testValidationNoStaticMethod()
    {
        $container = $this->createContainerFromFile('validation_no_static_method');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $annotations = !class_exists(FullStack::class) && class_exists(Annotation::class);

        self::assertCount($annotations ? 7 : 5, $calls);
        self::assertSame('addXmlMappings', $calls[3][0]);
        $i = 3;
        if ($annotations) {
            self::assertSame('enableAnnotationMapping', $calls[++$i][0]);
            self::assertSame('setDoctrineAnnotationReader', $calls[++$i][0]);
        }
        self::assertSame('setMappingCache', $calls[++$i][0]);
        self::assertEquals([new Reference('validator.mapping.cache.adapter')], $calls[$i][1]);
        // no cache, no annotations, no static methods
    }

    public function testEmailValidationModeIsPassedToEmailValidator()
    {
        $container = $this->createContainerFromFile('validation_email_validation_mode');

        self::assertSame('html5', $container->getDefinition('validator.email')->getArgument(0));
    }

    public function testValidationTranslationDomain()
    {
        $container = $this->createContainerFromFile('validation_translation_domain');

        self::assertSame('messages', $container->getParameter('validator.translation_domain'));
    }

    public function testValidationMapping()
    {
        $container = $this->createContainerFromFile('validation_mapping');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        self::assertSame('addXmlMappings', $calls[3][0]);
        self::assertCount(3, $calls[3][1][0]);

        self::assertSame('addYamlMappings', $calls[4][0]);
        self::assertCount(3, $calls[4][1][0]);
        self::assertStringContainsString('foo.yml', $calls[4][1][0][0]);
        self::assertStringContainsString('validation.yml', $calls[4][1][0][1]);
        self::assertStringContainsString('validation.yaml', $calls[4][1][0][2]);
    }

    public function testValidationAutoMapping()
    {
        if (!class_exists(PropertyInfoLoader::class)) {
            self::markTestSkipped('Auto-mapping requires symfony/validation 4.2+');
        }

        $container = $this->createContainerFromFile('validation_auto_mapping');
        $parameter = [
            'App\\' => ['services' => ['foo', 'bar']],
            'Symfony\\' => ['services' => ['a', 'b']],
            'Foo\\' => ['services' => []],
        ];

        self::assertSame($parameter, $container->getParameter('validator.auto_mapping'));
        self::assertTrue($container->hasDefinition('validator.property_info_loader'));
    }

    public function testFormsCanBeEnabledWithoutCsrfProtection()
    {
        $container = $this->createContainerFromFile('form_no_csrf');

        self::assertFalse($container->getParameter('form.type_extension.csrf.enabled'));
    }

    /**
     * @group legacy
     */
    public function testFormsWithoutImprovedValidationMessages()
    {
        $this->expectDeprecation('Since symfony/framework-bundle 5.2: Setting the "framework.form.legacy_error_messages" option to "true" is deprecated. It will have no effect as of Symfony 6.0.');

        $this->createContainerFromFile('form_legacy_messages');
    }

    public function testStopwatchEnabledWithDebugModeEnabled()
    {
        $container = $this->createContainerFromFile('default_config', [
            'kernel.container_class' => 'foo',
            'kernel.debug' => true,
        ]);

        self::assertTrue($container->has('debug.stopwatch'));
    }

    public function testStopwatchEnabledWithDebugModeDisabled()
    {
        $container = $this->createContainerFromFile('default_config', [
            'kernel.container_class' => 'foo',
        ]);

        self::assertTrue($container->has('debug.stopwatch'));
    }

    public function testSerializerDisabled()
    {
        $container = $this->createContainerFromFile('default_config');
        self::assertSame(!class_exists(FullStack::class) && class_exists(Serializer::class), $container->has('serializer'));
    }

    public function testSerializerEnabled()
    {
        $container = $this->createContainerFromFile('full');
        self::assertTrue($container->has('serializer'));

        $argument = $container->getDefinition('serializer.mapping.chain_loader')->getArgument(0);

        self::assertCount(2, $argument);
        self::assertEquals('Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader', $argument[0]->getClass());
        self::assertEquals(new Reference('serializer.name_converter.camel_case_to_snake_case'), $container->getDefinition('serializer.name_converter.metadata_aware')->getArgument(1));
        self::assertEquals(new Reference('property_info', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE), $container->getDefinition('serializer.normalizer.object')->getArgument(3));
        self::assertArrayHasKey('circular_reference_handler', $container->getDefinition('serializer.normalizer.object')->getArgument(6));
        self::assertArrayHasKey('max_depth_handler', $container->getDefinition('serializer.normalizer.object')->getArgument(6));
        self::assertEquals($container->getDefinition('serializer.normalizer.object')->getArgument(6)['max_depth_handler'], new Reference('my.max.depth.handler'));
    }

    public function testRegisterSerializerExtractor()
    {
        $container = $this->createContainerFromFile('full');

        $serializerExtractorDefinition = $container->getDefinition('property_info.serializer_extractor');

        self::assertEquals('serializer.mapping.class_metadata_factory', $serializerExtractorDefinition->getArgument(0)->__toString());
        self::assertTrue(!$serializerExtractorDefinition->isPublic() || $serializerExtractorDefinition->isPrivate());
        $tag = $serializerExtractorDefinition->getTag('property_info.list_extractor');
        self::assertEquals(['priority' => -999], $tag[0]);
    }

    public function testDataUriNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.data_uri');
        $tag = $definition->getTag('serializer.normalizer');

        self::assertEquals(DataUriNormalizer::class, $definition->getClass());
        self::assertEquals(-920, $tag[0]['priority']);
    }

    public function testDateIntervalNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.dateinterval');
        $tag = $definition->getTag('serializer.normalizer');

        self::assertEquals(DateIntervalNormalizer::class, $definition->getClass());
        self::assertEquals(-915, $tag[0]['priority']);
    }

    public function testDateTimeNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.datetime');
        $tag = $definition->getTag('serializer.normalizer');

        self::assertEquals(DateTimeNormalizer::class, $definition->getClass());
        self::assertEquals(-910, $tag[0]['priority']);
    }

    public function testFormErrorNormalizerRegistred()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.form_error');
        $tag = $definition->getTag('serializer.normalizer');

        self::assertEquals(FormErrorNormalizer::class, $definition->getClass());
        self::assertEquals(-915, $tag[0]['priority']);
    }

    public function testJsonSerializableNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.json_serializable');
        $tag = $definition->getTag('serializer.normalizer');

        self::assertEquals(JsonSerializableNormalizer::class, $definition->getClass());
        self::assertEquals(-950, $tag[0]['priority']);
    }

    public function testObjectNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.object');
        $tag = $definition->getTag('serializer.normalizer');

        self::assertEquals('Symfony\Component\Serializer\Normalizer\ObjectNormalizer', $definition->getClass());
        self::assertEquals(-1000, $tag[0]['priority']);
    }

    public function testConstraintViolationListNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.constraint_violation_list');
        $tag = $definition->getTag('serializer.normalizer');

        self::assertEquals(ConstraintViolationListNormalizer::class, $definition->getClass());
        self::assertEquals(-915, $tag[0]['priority']);
        self::assertEquals(new Reference('serializer.name_converter.metadata_aware'), $definition->getArgument(1));
    }

    public function testSerializerCacheActivated()
    {
        $container = $this->createContainerFromFile('serializer_enabled');

        self::assertTrue($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));

        $cache = $container->getDefinition('serializer.mapping.cache_class_metadata_factory')->getArgument(1);
        self::assertEquals(new Reference('serializer.mapping.cache.symfony'), $cache);
    }

    public function testSerializerCacheNotActivatedDebug()
    {
        $container = $this->createContainerFromFile('serializer_enabled', ['kernel.debug' => true, 'kernel.container_class' => __CLASS__]);
        self::assertFalse($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));
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
        self::assertEquals($expectedLoaders, $loaders);
    }

    public function testSerializerServiceIsRegisteredWhenEnabled()
    {
        $container = $this->createContainerFromFile('serializer_enabled');

        self::assertTrue($container->hasDefinition('serializer'));
    }

    public function testSerializerServiceIsNotRegisteredWhenDisabled()
    {
        $container = $this->createContainerFromFile('serializer_disabled');

        self::assertFalse($container->hasDefinition('serializer'));
    }

    public function testPropertyInfoEnabled()
    {
        $container = $this->createContainerFromFile('property_info');
        self::assertTrue($container->has('property_info'));
    }

    public function testPropertyInfoCacheActivated()
    {
        $container = $this->createContainerFromFile('property_info');

        self::assertTrue($container->hasDefinition('property_info.cache'));

        $cache = $container->getDefinition('property_info.cache')->getArgument(1);
        self::assertEquals(new Reference('cache.property_info'), $cache);
    }

    public function testPropertyInfoCacheDisabled()
    {
        $container = $this->createContainerFromFile('property_info', ['kernel.debug' => true, 'kernel.container_class' => __CLASS__]);
        self::assertFalse($container->hasDefinition('property_info.cache'));
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
        self::assertInstanceOf(EventDispatcherInterface::class, $container->get('foo')->dispatcher);
    }

    public function testCacheDefaultRedisProvider()
    {
        $container = $this->createContainerFromFile('cache');

        $redisUrl = 'redis://localhost';
        $providerId = '.cache_connection.'.ContainerBuilder::hash($redisUrl);

        self::assertTrue($container->hasDefinition($providerId));

        $url = $container->getDefinition($providerId)->getArgument(0);

        self::assertSame($redisUrl, $url);
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

        self::assertSame(ChainAdapter::class, $chain->getClass());

        self::assertCount(2, $chain->getArguments());
        self::assertCount(3, $chain->getArguments()[0]);

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
        self::assertEquals($expected, $chain->getArguments());

        // Test "tags: true" wrapping logic
        $tagAwareDefinition = $container->getDefinition('cache.ccc');
        self::assertSame(TagAwareAdapter::class, $tagAwareDefinition->getClass());
        $this->assertCachePoolServiceDefinitionIsCreated($container, (string) $tagAwareDefinition->getArgument(0), 'cache.adapter.array', 410);

        if (method_exists(TagAwareAdapter::class, 'setLogger')) {
            self::assertEquals([
                ['setLogger', [new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]],
            ], $tagAwareDefinition->getMethodCalls());
            self::assertSame([['channel' => 'cache']], $tagAwareDefinition->getTag('monolog.logger'));
        }
    }

    /**
     * @group legacy
     */
    public function testDoctrineCache()
    {
        if (!class_exists(DoctrineAdapter::class)) {
            self::markTestSkipped('This test requires symfony/cache 5.4 or lower.');
        }

        $container = $this->createContainerFromFile('doctrine_cache', [], true, false);
        $container->setParameter('cache.prefix.seed', 'test');
        $container->addCompilerPass(new CachePoolPass());
        $container->compile();

        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.bar', 'cache.adapter.doctrine', 5);
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
            self::assertNotNull($aliasForArgument, sprintf("No alias found for '%s'", $aliasForArgumentStr));

            $def = $container->getDefinition((string) $aliasForArgument);
            self::assertInstanceOf(ChildDefinition::class, $def, sprintf("No definition found for '%s'", $aliasForArgumentStr));

            $defParent = $container->getDefinition($def->getParent());
            if ($defParent instanceof ChildDefinition) {
                $defParent = $container->getDefinition($defParent->getParent());
            }

            self::assertSame(RedisTagAwareAdapter::class, $defParent->getClass(), sprintf("'%s' is not %s", $aliasForArgumentStr, RedisTagAwareAdapter::class));
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

            self::assertSame(RedisTagAwareAdapter::class, $def->getClass());
        }
    }

    public function appRedisTagAwareConfigProvider(): array
    {
        return [
            ['cache_app_redis_tag_aware'],
            ['cache_app_redis_tag_aware_pool'],
        ];
    }

    public function testRemovesResourceCheckerConfigCacheFactoryArgumentOnlyIfNoDebug()
    {
        $container = $this->createContainer(['kernel.debug' => true]);
        (new FrameworkExtension())->load([], $container);
        self::assertCount(1, $container->getDefinition('config_cache_factory')->getArguments());

        $container = $this->createContainer(['kernel.debug' => false]);
        (new FrameworkExtension())->load([], $container);
        self::assertEmpty($container->getDefinition('config_cache_factory')->getArguments());
    }

    public function testLoggerAwareRegistration()
    {
        $container = $this->createContainerFromFile('full', [], true, false);
        $container->addCompilerPass(new ResolveInstanceofConditionalsPass());
        $container->register('foo', LoggerAwareInterface::class)
            ->setAutoconfigured(true);
        $container->compile();

        $calls = $container->findDefinition('foo')->getMethodCalls();

        self::assertCount(1, $calls, 'Definition should contain 1 method call');
        self::assertSame('setLogger', $calls[0][0], 'Method name should be "setLogger"');
        self::assertInstanceOf(Reference::class, $calls[0][1][0]);
        self::assertSame('logger', (string) $calls[0][1][0], 'Argument should be a reference to "logger"');
    }

    public function testSessionCookieSecureAuto()
    {
        $container = $this->createContainerFromFile('session_cookie_secure_auto');

        $expected = ['session_factory', 'session', 'initialized_session', 'logger', 'session_collector'];
        self::assertEquals($expected, array_keys($container->getDefinition('session_listener')->getArgument(0)->getValues()));
    }

    /**
     * @group legacy
     */
    public function testSessionCookieSecureAutoLegacy()
    {
        $this->expectDeprecation('Since symfony/framework-bundle 5.3: Not setting the "framework.session.storage_factory_id" configuration option is deprecated, it will default to "session.storage.factory.native" and will replace the "framework.session.storage_id" configuration option in version 6.0.');

        $container = $this->createContainerFromFile('session_cookie_secure_auto_legacy');

        $expected = ['session_factory', 'session', 'initialized_session', 'logger', 'session_collector', 'session_storage', 'request_stack'];
        self::assertEquals($expected, array_keys($container->getDefinition('session_listener')->getArgument(0)->getValues()));
    }

    public function testRobotsTagListenerIsRegisteredInDebugMode()
    {
        $container = $this->createContainer(['kernel.debug' => true]);
        (new FrameworkExtension())->load([], $container);
        self::assertTrue($container->has('disallow_search_engine_index_response_listener'), 'DisallowRobotsIndexingListener should be registered');

        $definition = $container->getDefinition('disallow_search_engine_index_response_listener');
        self::assertTrue($definition->hasTag('kernel.event_subscriber'), 'DisallowRobotsIndexingListener should have the correct tag');

        $container = $this->createContainer(['kernel.debug' => true]);
        (new FrameworkExtension())->load([['disallow_search_engine_index' => false]], $container);
        self::assertFalse($container->has('disallow_search_engine_index_response_listener'), 'DisallowRobotsIndexingListener should not be registered when explicitly disabled');

        $container = $this->createContainer(['kernel.debug' => false]);
        (new FrameworkExtension())->load([], $container);
        self::assertFalse($container->has('disallow_search_engine_index_response_listener'), 'DisallowRobotsIndexingListener should NOT be registered');
    }

    public function testHttpClientDefaultOptions()
    {
        $container = $this->createContainerFromFile('http_client_default_options');
        self::assertTrue($container->hasDefinition('http_client'), '->registerHttpClientConfiguration() loads http_client.xml');

        $defaultOptions = [
            'headers' => [],
            'resolve' => [],
        ];
        self::assertSame([$defaultOptions, 4], $container->getDefinition('http_client')->getArguments());

        self::assertTrue($container->hasDefinition('foo'), 'should have the "foo" service.');
        self::assertSame(ScopingHttpClient::class, $container->getDefinition('foo')->getClass());
    }

    public function testScopedHttpClientWithoutQueryOption()
    {
        $container = $this->createContainerFromFile('http_client_scoped_without_query_option');

        self::assertTrue($container->hasDefinition('foo'), 'should have the "foo" service.');
        self::assertSame(ScopingHttpClient::class, $container->getDefinition('foo')->getClass());
    }

    public function testHttpClientOverrideDefaultOptions()
    {
        $container = $this->createContainerFromFile('http_client_override_default_options');

        self::assertSame(['foo' => 'bar'], $container->getDefinition('http_client')->getArgument(0)['headers']);
        self::assertSame(4, $container->getDefinition('http_client')->getArgument(1));
        self::assertSame('http://example.com', $container->getDefinition('foo')->getArgument(1));

        $expected = [
            'headers' => [
                'bar' => 'baz',
            ],
            'query' => [],
            'resolve' => [],
        ];
        self::assertSame($expected, $container->getDefinition('foo')->getArgument(2));
    }

    public function testHttpClientRetry()
    {
        if (!class_exists(RetryableHttpClient::class)) {
            self::expectException(LogicException::class);
        }
        $container = $this->createContainerFromFile('http_client_retry');

        self::assertSame([429, 500 => ['GET', 'HEAD']], $container->getDefinition('http_client.retry_strategy')->getArgument(0));
        self::assertSame(100, $container->getDefinition('http_client.retry_strategy')->getArgument(1));
        self::assertSame(2, $container->getDefinition('http_client.retry_strategy')->getArgument(2));
        self::assertSame(0, $container->getDefinition('http_client.retry_strategy')->getArgument(3));
        self::assertSame(0.3, $container->getDefinition('http_client.retry_strategy')->getArgument(4));
        self::assertSame(2, $container->getDefinition('http_client.retryable')->getArgument(2));

        self::assertSame(RetryableHttpClient::class, $container->getDefinition('foo.retryable')->getClass());
        self::assertSame(4, $container->getDefinition('foo.retry_strategy')->getArgument(2));
    }

    public function testHttpClientWithQueryParameterKey()
    {
        $container = $this->createContainerFromFile('http_client_xml_key');

        $expected = [
            'key' => 'foo',
        ];
        self::assertSame($expected, $container->getDefinition('foo')->getArgument(2)['query']);

        $expected = [
            'host' => '127.0.0.1',
        ];
        self::assertSame($expected, $container->getDefinition('foo')->getArgument(2)['resolve']);
    }

    public function testHttpClientFullDefaultOptions()
    {
        $container = $this->createContainerFromFile('http_client_full_default_options');

        $defaultOptions = $container->getDefinition('http_client')->getArgument(0);

        self::assertSame(['X-powered' => 'PHP'], $defaultOptions['headers']);
        self::assertSame(2, $defaultOptions['max_redirects']);
        self::assertSame(2.0, (float) $defaultOptions['http_version']);
        self::assertSame(['localhost' => '127.0.0.1'], $defaultOptions['resolve']);
        self::assertSame('proxy.org', $defaultOptions['proxy']);
        self::assertSame(3.5, $defaultOptions['timeout']);
        self::assertSame(10.1, $defaultOptions['max_duration']);
        self::assertSame('127.0.0.1', $defaultOptions['bindto']);
        self::assertTrue($defaultOptions['verify_peer']);
        self::assertTrue($defaultOptions['verify_host']);
        self::assertSame('/etc/ssl/cafile', $defaultOptions['cafile']);
        self::assertSame('/etc/ssl', $defaultOptions['capath']);
        self::assertSame('/etc/ssl/cert.pem', $defaultOptions['local_cert']);
        self::assertSame('/etc/ssl/private_key.pem', $defaultOptions['local_pk']);
        self::assertSame('password123456', $defaultOptions['passphrase']);
        self::assertSame('RC4-SHA:TLS13-AES-128-GCM-SHA256', $defaultOptions['ciphers']);
        self::assertSame([
            'pin-sha256' => ['14s5erg62v1v8471g2revg48r7==', 'jsda84hjtyd4821bgfesd215bsfg5412='],
            'md5' => 'sdhtb481248721thbr=',
        ], $defaultOptions['peer_fingerprint']);
    }

    public function provideMailer(): array
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

        self::assertTrue($container->hasAlias('mailer'));
        self::assertTrue($container->hasDefinition('mailer.transports'));
        self::assertSame($expectedTransports, $container->getDefinition('mailer.transports')->getArgument(0));
        self::assertTrue($container->hasDefinition('mailer.default_transport'));
        self::assertSame(current($expectedTransports), $container->getDefinition('mailer.default_transport')->getArgument(0));
        self::assertTrue($container->hasDefinition('mailer.envelope_listener'));
        $l = $container->getDefinition('mailer.envelope_listener');
        self::assertSame('sender@example.org', $l->getArgument(0));
        self::assertSame(['redirected@example.org', 'redirected1@example.org'], $l->getArgument(1));
        self::assertEquals(new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition('mailer.mailer')->getArgument(1));

        self::assertTrue($container->hasDefinition('mailer.message_listener'));
        $l = $container->getDefinition('mailer.message_listener');
        $h = $l->getArgument(0);
        self::assertCount(3, $h->getMethodCalls());
    }

    public function testMailerWithDisabledMessageBus()
    {
        $container = $this->createContainerFromFile('mailer_with_disabled_message_bus');

        self::assertNull($container->getDefinition('mailer.mailer')->getArgument(1));
    }

    public function testMailerWithSpecificMessageBus()
    {
        $container = $this->createContainerFromFile('mailer_with_specific_message_bus');

        self::assertEquals(new Reference('app.another_bus'), $container->getDefinition('mailer.mailer')->getArgument(1));
    }

    public function testHttpClientMockResponseFactory()
    {
        $container = $this->createContainerFromFile('http_client_mock_response_factory');

        $definition = $container->getDefinition('http_client.mock_client');

        self::assertSame(MockHttpClient::class, $definition->getClass());
        self::assertCount(1, $definition->getArguments());

        $argument = $definition->getArgument(0);

        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame('http_client', current($definition->getDecoratedService()));
        self::assertSame('my_response_factory', (string) $argument);
    }

    public function testRegisterParameterCollectingBehaviorDescribingTags()
    {
        $container = $this->createContainerFromFile('default_config');

        self::assertTrue($container->hasParameter('container.behavior_describing_tags'));
        self::assertEquals([
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

        self::assertFalse($container->hasDefinition('notifier.channel.email'));
    }

    public function testNotifierWithoutMessenger()
    {
        $container = $this->createContainerFromFile('notifier_without_messenger');

        self::assertFalse($container->getDefinition('notifier.failed_message_listener')->hasTag('kernel.event_subscriber'));
    }

    public function testNotifierWithMailerAndMessenger()
    {
        $container = $this->createContainerFromFile('notifier');

        self::assertTrue($container->hasDefinition('notifier'));
        self::assertTrue($container->hasDefinition('chatter'));
        self::assertTrue($container->hasDefinition('texter'));
        self::assertTrue($container->hasDefinition('notifier.channel.chat'));
        self::assertTrue($container->hasDefinition('notifier.channel.email'));
        self::assertTrue($container->hasDefinition('notifier.channel.sms'));
        self::assertTrue($container->hasDefinition('notifier.channel_policy'));
        self::assertTrue($container->getDefinition('notifier.failed_message_listener')->hasTag('kernel.event_subscriber'));
    }

    public function testNotifierWithoutTransports()
    {
        $container = $this->createContainerFromFile('notifier_without_transports');

        self::assertTrue($container->hasDefinition('notifier'));
        self::assertFalse($container->hasDefinition('chatter'));
        self::assertFalse($container->hasAlias(ChatterInterface::class));
        self::assertFalse($container->hasDefinition('texter'));
        self::assertFalse($container->hasAlias(TexterInterface::class));
    }

    public function testIfNotifierTransportsAreKnownByFrameworkExtension()
    {
        if (!class_exists(FullStack::class)) {
            self::markTestSkipped('This test can only run in fullstack test suites');
        }

        $container = $this->createContainerFromFile('notifier');

        foreach ((new Finder())->in(\dirname(__DIR__, 4).'/Component/Notifier/Bridge')->directories()->depth(0)->exclude('Mercure') as $bridgeDirectory) {
            $transportFactoryName = strtolower(preg_replace('/(.)([A-Z])/', '$1-$2', $bridgeDirectory->getFilename()));
            self::assertTrue($container->hasDefinition('notifier.transport_factory.'.$transportFactoryName), sprintf('Did you forget to add the "%s" TransportFactory to the $classToServices array in FrameworkExtension?', $bridgeDirectory->getFilename()));
        }
    }

    protected function createContainer(array $data = [])
    {
        return new ContainerBuilder(new EnvPlaceholderParameterBag(array_merge([
            'kernel.bundles' => ['FrameworkBundle' => 'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'],
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
        self::assertEquals('assets.path_package', $package->getParent());
        self::assertEquals($basePath, $package->getArgument(0));
        $this->assertVersionStrategy($container, $package->getArgument(1), $version, $format);
    }

    private function assertUrlPackage(ContainerBuilder $container, ChildDefinition $package, $baseUrls, $version, $format)
    {
        self::assertEquals('assets.url_package', $package->getParent());
        self::assertEquals($baseUrls, $package->getArgument(0));
        $this->assertVersionStrategy($container, $package->getArgument(1), $version, $format);
    }

    private function assertVersionStrategy(ContainerBuilder $container, Reference $reference, $version, $format)
    {
        $versionStrategy = $container->getDefinition((string) $reference);
        if (null === $version) {
            self::assertEquals('assets.empty_version_strategy', (string) $reference);
        } else {
            self::assertEquals('assets.static_version_strategy', $versionStrategy->getParent());
            self::assertEquals($version, $versionStrategy->getArgument(0));
            self::assertEquals($format, $versionStrategy->getArgument(1));
        }
    }

    private function assertCachePoolServiceDefinitionIsCreated(ContainerBuilder $container, $id, $adapter, $defaultLifetime)
    {
        self::assertTrue($container->has($id), sprintf('Service definition "%s" for cache pool of type "%s" is registered', $id, $adapter));

        $poolDefinition = $container->getDefinition($id);

        self::assertInstanceOf(ChildDefinition::class, $poolDefinition, sprintf('Cache pool "%s" is based on an abstract cache pool.', $id));

        self::assertTrue($poolDefinition->hasTag('cache.pool'), sprintf('Service definition "%s" is tagged with the "cache.pool" tag.', $id));
        self::assertFalse($poolDefinition->isAbstract(), sprintf('Service definition "%s" is not abstract.', $id));

        $tag = $poolDefinition->getTag('cache.pool');
        self::assertArrayHasKey('default_lifetime', $tag[0], 'The default lifetime is stored as an attribute of the "cache.pool" tag.');
        self::assertSame($defaultLifetime, $tag[0]['default_lifetime'], 'The default lifetime is stored as an attribute of the "cache.pool" tag.');

        $parentDefinition = $poolDefinition;
        do {
            $parentId = $parentDefinition->getParent();
            $parentDefinition = $container->findDefinition($parentId);
        } while ($parentDefinition instanceof ChildDefinition);

        switch ($adapter) {
            case 'cache.adapter.apcu':
                self::assertSame(ApcuAdapter::class, $parentDefinition->getClass());
                break;
            case 'cache.adapter.doctrine':
                self::assertSame(DoctrineAdapter::class, $parentDefinition->getClass());
                break;
            case 'cache.app':
            case 'cache.adapter.filesystem':
                self::assertSame(FilesystemAdapter::class, $parentDefinition->getClass());
                break;
            case 'cache.adapter.psr6':
                self::assertSame(ProxyAdapter::class, $parentDefinition->getClass());
                break;
            case 'cache.adapter.redis':
                self::assertSame(RedisAdapter::class, $parentDefinition->getClass());
                break;
            case 'cache.adapter.array':
                self::assertSame(ArrayAdapter::class, $parentDefinition->getClass());
                break;
            default:
                self::fail('Unresolved adapter: '.$adapter);
        }
    }
}

/**
 * Simulates ReplaceAliasByActualDefinitionPass.
 */
class TestAnnotationsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $container->setDefinition('annotation_reader', $container->getDefinition('annotations.cached_reader'));
        $container->removeDefinition('annotations.cached_reader');
    }
}
