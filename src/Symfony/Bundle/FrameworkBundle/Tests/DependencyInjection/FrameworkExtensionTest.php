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
use Psr\Log\LoggerAwareInterface;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddAnnotationsCachedReaderPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
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
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\DependencyInjection\LoggerPass;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Translation\DependencyInjection\TranslatorPass;
use Symfony\Component\Validator\DependencyInjection\AddConstraintValidatorsPass;
use Symfony\Component\Workflow;

abstract class FrameworkExtensionTest extends TestCase
{
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

    public function testPropertyAccessWithDefaultValue()
    {
        $container = $this->createContainerFromFile('full');

        $def = $container->getDefinition('property_accessor');
        $this->assertFalse($def->getArgument(0));
        $this->assertFalse($def->getArgument(1));
    }

    public function testPropertyAccessWithOverriddenValues()
    {
        $container = $this->createContainerFromFile('property_accessor');
        $def = $container->getDefinition('property_accessor');
        $this->assertTrue($def->getArgument(0));
        $this->assertTrue($def->getArgument(1));
    }

    public function testPropertyAccessCache()
    {
        $container = $this->createContainerFromFile('property_accessor');

        if (!method_exists(PropertyAccessor::class, 'createCache')) {
            return $this->assertFalse($container->hasDefinition('cache.property_access'));
        }

        $cache = $container->getDefinition('cache.property_access');
        $this->assertSame([PropertyAccessor::class, 'createCache'], $cache->getFactory(), 'PropertyAccessor::createCache() should be used in non-debug mode');
        $this->assertSame(AdapterInterface::class, $cache->getClass());
    }

    public function testPropertyAccessCacheWithDebug()
    {
        $container = $this->createContainerFromFile('property_accessor', ['kernel.debug' => true]);

        if (!method_exists(PropertyAccessor::class, 'createCache')) {
            return $this->assertFalse($container->hasDefinition('cache.property_access'));
        }

        $cache = $container->getDefinition('cache.property_access');
        $this->assertNull($cache->getFactory());
        $this->assertSame(ArrayAdapter::class, $cache->getClass(), 'ArrayAdapter should be used in debug mode');
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage CSRF protection needs sessions to be enabled.
     */
    public function testCsrfProtectionNeedsSessionToBeEnabled()
    {
        $this->createContainerFromFile('csrf_needs_session');
    }

    public function testCsrfProtectionForFormsEnablesCsrfProtectionAutomatically()
    {
        $container = $this->createContainerFromFile('csrf');

        $this->assertTrue($container->hasDefinition('security.csrf.token_manager'));
    }

    public function testHttpMethodOverride()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertFalse($container->getParameter('kernel.http_method_override'));
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

    public function testWorkflows()
    {
        $container = $this->createContainerFromFile('workflows');

        $this->assertTrue($container->hasDefinition('workflow.article'), 'Workflow is registered as a service');
        $this->assertSame('workflow.abstract', $container->getDefinition('workflow.article')->getParent());
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
        $this->assertSame(['workflow.definition' => [['name' => 'article', 'type' => 'workflow', 'marking_store' => 'multiple_state']]], $workflowDefinition->getTags());
        $this->assertCount(4, $workflowDefinition->getArgument(1));
        $this->assertSame('draft', $workflowDefinition->getArgument(2));

        $this->assertTrue($container->hasDefinition('state_machine.pull_request'), 'State machine is registered as a service');
        $this->assertSame('state_machine.abstract', $container->getDefinition('state_machine.pull_request')->getParent());
        $this->assertTrue($container->hasDefinition('state_machine.pull_request.definition'), 'State machine definition is registered as a service');

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
        $this->assertSame(['workflow.definition' => [['name' => 'pull_request', 'type' => 'state_machine', 'marking_store' => 'single_state']]], $stateMachineDefinition->getTags());
        $this->assertCount(9, $stateMachineDefinition->getArgument(1));
        $this->assertSame('start', $stateMachineDefinition->getArgument(2));

        $metadataStoreDefinition = $stateMachineDefinition->getArgument(3);
        $this->assertInstanceOf(Definition::class, $metadataStoreDefinition);
        $this->assertSame(Workflow\Metadata\InMemoryMetadataStore::class, $metadataStoreDefinition->getClass());

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
        $this->assertSame('state_machine.pull_request.transition.0', (string) $params[0]);

        $serviceMarkingStoreWorkflowDefinition = $container->getDefinition('workflow.service_marking_store_workflow');
        /** @var Reference $markingStoreRef */
        $markingStoreRef = $serviceMarkingStoreWorkflowDefinition->getArgument(1);
        $this->assertInstanceOf(Reference::class, $markingStoreRef);
        $this->assertEquals('workflow_service', (string) $markingStoreRef);

        $this->assertTrue($container->hasDefinition('workflow.registry'), 'Workflow registry is registered as a service');
        $registryDefinition = $container->getDefinition('workflow.registry');
        $this->assertGreaterThan(0, \count($registryDefinition->getMethodCalls()));
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage "type" and "service" cannot be used together.
     */
    public function testWorkflowCannotHaveBothTypeAndService()
    {
        $this->createContainerFromFile('workflow_with_type_and_service');
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage "supports" and "support_strategy" cannot be used together.
     */
    public function testWorkflowCannotHaveBothSupportsAndSupportStrategy()
    {
        $this->createContainerFromFile('workflow_with_support_and_support_strategy');
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage "supports" or "support_strategy" should be configured.
     */
    public function testWorkflowShouldHaveOneOfSupportsAndSupportStrategy()
    {
        $this->createContainerFromFile('workflow_without_support_and_support_strategy');
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage "arguments" and "service" cannot be used together.
     */
    public function testWorkflowCannotHaveBothArgumentsAndService()
    {
        $this->createContainerFromFile('workflow_with_arguments_and_service');
    }

    public function testWorkflowMultipleTransitionsWithSameName()
    {
        $container = $this->createContainerFromFile('workflow_with_multiple_transitions_with_same_name');

        $this->assertTrue($container->hasDefinition('workflow.article'), 'Workflow is registered as a service');
        $this->assertTrue($container->hasDefinition('workflow.article.definition'), 'Workflow definition is registered as a service');

        $workflowDefinition = $container->getDefinition('workflow.article.definition');

        $transitions = $workflowDefinition->getArgument(1);

        $this->assertCount(5, $transitions);

        $this->assertSame('workflow.article.transition.0', (string) $transitions[0]);
        $this->assertSame([
            'request_review',
            [
                'draft',
            ],
            [
                'wait_for_journalist', 'wait_for_spellchecker',
            ],
        ], $container->getDefinition($transitions[0])->getArguments());

        $this->assertSame('workflow.article.transition.1', (string) $transitions[1]);
        $this->assertSame([
            'journalist_approval',
            [
                'wait_for_journalist',
            ],
            [
                'approved_by_journalist',
            ],
        ], $container->getDefinition($transitions[1])->getArguments());

        $this->assertSame('workflow.article.transition.2', (string) $transitions[2]);
        $this->assertSame([
            'spellchecker_approval',
            [
                'wait_for_spellchecker',
            ],
            [
                'approved_by_spellchecker',
            ],
        ], $container->getDefinition($transitions[2])->getArguments());

        $this->assertSame('workflow.article.transition.3', (string) $transitions[3]);
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

        $this->assertSame('workflow.article.transition.4', (string) $transitions[4]);
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

    public function testGuardExpressions()
    {
        $container = $this->createContainerFromFile('workflow_with_guard_expression');

        $this->assertTrue($container->hasDefinition('workflow.article.listener.guard'), 'Workflow guard listener is registered as a service');
        $this->assertTrue($container->hasParameter('workflow.has_guard_listeners'), 'Workflow guard listeners parameter exists');
        $this->assertTrue(true === $container->getParameter('workflow.has_guard_listeners'), 'Workflow guard listeners parameter is enabled');
        $guardDefinition = $container->getDefinition('workflow.article.listener.guard');
        $this->assertSame([
            [
                'event' => 'workflow.article.guard.publish',
                'method' => 'onTransition',
            ],
        ], $guardDefinition->getTag('kernel.event_listener'));
        $guardsConfiguration = $guardDefinition->getArgument(0);
        $this->assertTrue(1 === \count($guardsConfiguration), 'Workflow guard configuration contains one element per transition name');
        $transitionGuardExpressions = $guardsConfiguration['workflow.article.guard.publish'];
        $this->assertSame('workflow.article.transition.3', (string) $transitionGuardExpressions[0]->getArgument(0));
        $this->assertSame('!!true', $transitionGuardExpressions[0]->getArgument(1));
        $this->assertSame('workflow.article.transition.4', (string) $transitionGuardExpressions[1]->getArgument(0));
        $this->assertSame('!!false', $transitionGuardExpressions[1]->getArgument(1));
    }

    public function testWorkflowServicesCanBeEnabled()
    {
        $container = $this->createContainerFromFile('workflows_enabled');

        $this->assertTrue($container->has(Workflow\Registry::class));
        $this->assertTrue($container->hasDefinition('console.command.workflow_dump'));
    }

    public function testExplicitlyEnabledWorkflows()
    {
        $container = $this->createContainerFromFile('workflows_explicitly_enabled');

        $this->assertTrue($container->hasDefinition('workflow.foo.definition'));
    }

    public function testExplicitlyEnabledWorkflowNamedWorkflows()
    {
        $container = $this->createContainerFromFile('workflows_explicitly_enabled_named_workflows');

        $this->assertTrue($container->hasDefinition('workflow.workflows.definition'));
    }

    public function testEnabledPhpErrorsConfig()
    {
        $container = $this->createContainerFromFile('php_errors_enabled');

        $definition = $container->getDefinition('debug.debug_handlers_listener');
        $this->assertEquals(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $definition->getArgument(1));
        $this->assertNull($definition->getArgument(2));
        $this->assertSame(-1, $container->getParameter('debug.error_handler.throw_at'));
    }

    public function testDisabledPhpErrorsConfig()
    {
        $container = $this->createContainerFromFile('php_errors_disabled');

        $definition = $container->getDefinition('debug.debug_handlers_listener');
        $this->assertNull($definition->getArgument(1));
        $this->assertNull($definition->getArgument(2));
        $this->assertSame(0, $container->getParameter('debug.error_handler.throw_at'));
    }

    public function testPhpErrorsWithLogLevel()
    {
        $container = $this->createContainerFromFile('php_errors_log_level');

        $definition = $container->getDefinition('debug.debug_handlers_listener');
        $this->assertEquals(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $definition->getArgument(1));
        $this->assertSame(8, $definition->getArgument(2));
    }

    public function testRouter()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->has('router'), '->registerRouterConfiguration() loads routing.xml');
        $arguments = $container->findDefinition('router')->getArguments();
        $this->assertEquals($container->getParameter('kernel.project_dir').'/config/routing.xml', $container->getParameter('router.resource'), '->registerRouterConfiguration() sets routing resource');
        $this->assertEquals('%router.resource%', $arguments[1], '->registerRouterConfiguration() sets routing resource');
        $this->assertEquals('xml', $arguments[2]['resource_type'], '->registerRouterConfiguration() sets routing resource type');
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testRouterRequiresResourceOption()
    {
        $container = $this->createContainer();
        $loader = new FrameworkExtension();
        $loader->load([['router' => true]], $container);
    }

    public function testSession()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('session'), '->registerSessionConfiguration() loads session.xml');
        $this->assertEquals('fr', $container->getParameter('kernel.default_locale'));
        $this->assertEquals('session.storage.native', (string) $container->getAlias('session.storage'));
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

        $this->assertTrue($container->hasDefinition('session'), '->registerSessionConfiguration() loads session.xml');
        $this->assertNull($container->getDefinition('session.storage.native')->getArgument(1));
        $this->assertNull($container->getDefinition('session.storage.php_bridge')->getArgument(0));

        $expected = ['session', 'initialized_session'];
        $this->assertEquals($expected, array_keys($container->getDefinition('session_listener')->getArgument(0)->getValues()));
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

    public function testTemplating()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('templating.name_parser'), '->registerTemplatingConfiguration() loads templating.xml');

        $this->assertEquals('templating.engine.delegating', (string) $container->getAlias('templating'), '->registerTemplatingConfiguration() configures delegating loader if multiple engines are provided');

        $this->assertEquals($container->getDefinition('templating.loader.chain'), $container->getDefinition('templating.loader.wrapped'), '->registerTemplatingConfiguration() configures loader chain if multiple loaders are provided');

        $this->assertEquals($container->getDefinition('templating.loader'), $container->getDefinition('templating.loader.cache'), '->registerTemplatingConfiguration() configures the loader to use cache');

        $this->assertEquals('%templating.loader.cache.path%', $container->getDefinition('templating.loader.cache')->getArgument(1));
        $this->assertEquals('/path/to/cache', $container->getParameter('templating.loader.cache.path'));

        $this->assertEquals(['php', 'twig'], $container->getParameter('templating.engines'), '->registerTemplatingConfiguration() sets a templating.engines parameter');

        $this->assertEquals(['FrameworkBundle:Form', 'theme1', 'theme2'], $container->getParameter('templating.helper.form.resources'), '->registerTemplatingConfiguration() registers the theme and adds the base theme');
        $this->assertEquals('global_hinclude_template', $container->getParameter('fragment.renderer.hinclude.global_template'), '->registerTemplatingConfiguration() registers the global hinclude.js template');
    }

    public function testTemplatingCanBeDisabled()
    {
        $container = $this->createContainerFromFile('templating_disabled');

        $this->assertFalse($container->hasParameter('templating.engines'), '"templating.engines" container parameter is not registered when templating is disabled.');
    }

    public function testAssets()
    {
        $container = $this->createContainerFromFile('assets');
        $packages = $container->getDefinition('assets.packages');

        // default package
        $defaultPackage = $container->getDefinition((string) $packages->getArgument(0));
        $this->assertUrlPackage($container, $defaultPackage, ['http://cdn.example.com'], 'SomeVersionScheme', '%%s?version=%%s');

        // packages
        $packages = $packages->getArgument(1);
        $this->assertCount(6, $packages);

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
    }

    public function testAssetsDefaultVersionStrategyAsService()
    {
        $container = $this->createContainerFromFile('assets_version_strategy_as_service');
        $packages = $container->getDefinition('assets.packages');

        // default package
        $defaultPackage = $container->getDefinition((string) $packages->getArgument(0));
        $this->assertEquals('assets.custom_version_strategy', (string) $defaultPackage->getArgument(1));
    }

    public function testAssetsCanBeDisabled()
    {
        $container = $this->createContainerFromFile('assets_disabled');

        $this->assertFalse($container->has('templating.helper.assets'), 'The templating.helper.assets helper service is removed when assets are disabled.');
    }

    public function testWebLink()
    {
        $container = $this->createContainerFromFile('web_link');
        $this->assertTrue($container->hasDefinition('web_link.add_link_header_listener'));
    }

    public function testMessenger()
    {
        $container = $this->createContainerFromFile('messenger');
        $this->assertTrue($container->hasAlias('message_bus'));
        $this->assertTrue($container->getAlias('message_bus')->isPublic());
        $this->assertFalse($container->hasDefinition('messenger.transport.amqp.factory'));
        $this->assertTrue($container->hasDefinition('messenger.transport_factory'));
        $this->assertSame(TransportFactory::class, $container->getDefinition('messenger.transport_factory')->getClass());
    }

    public function testMessengerTransports()
    {
        $container = $this->createContainerFromFile('messenger_transports');
        $this->assertTrue($container->hasDefinition('messenger.transport.default'));
        $this->assertTrue($container->getDefinition('messenger.transport.default')->hasTag('messenger.receiver'));
        $this->assertEquals([['alias' => 'default']], $container->getDefinition('messenger.transport.default')->getTag('messenger.receiver'));

        $this->assertTrue($container->hasDefinition('messenger.transport.customised'));
        $transportFactory = $container->getDefinition('messenger.transport.customised')->getFactory();
        $transportArguments = $container->getDefinition('messenger.transport.customised')->getArguments();

        $this->assertEquals([new Reference('messenger.transport_factory'), 'createTransport'], $transportFactory);
        $this->assertCount(2, $transportArguments);
        $this->assertSame('amqp://localhost/%2f/messages?exchange_name=exchange_name', $transportArguments[0]);
        $this->assertSame(['queue' => ['name' => 'Queue']], $transportArguments[1]);

        $this->assertTrue($container->hasDefinition('messenger.transport.amqp.factory'));
    }

    public function testMessengerRouting()
    {
        $container = $this->createContainerFromFile('messenger_routing');
        $senderLocatorDefinition = $container->getDefinition('messenger.senders_locator');

        $messageToSendAndHandleMapping = [
            DummyMessage::class => false,
            SecondMessage::class => true,
            '*' => false,
        ];

        $this->assertSame($messageToSendAndHandleMapping, $senderLocatorDefinition->getArgument(1));
        $sendersMapping = $senderLocatorDefinition->getArgument(0);
        $this->assertEquals([
            'amqp' => new Reference('messenger.transport.amqp'),
            'audit' => new Reference('audit'),
        ], $sendersMapping[DummyMessage::class]->getValues());
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\LogicException
     * @expectedExceptionMessage The default Messenger serializer cannot be enabled as the Serializer support is not available. Try enabling it or running "composer require symfony/serializer-pack".
     */
    public function testMessengerTransportConfigurationWithoutSerializer()
    {
        $this->createContainerFromFile('messenger_transport_no_serializer');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\LogicException
     * @expectedExceptionMessage The default AMQP transport is not available. Make sure you have installed and enabled the Serializer component. Try enabling it or running "composer require symfony/serializer-pack".
     */
    public function testMessengerAMQPTransportConfigurationWithoutSerializer()
    {
        $this->createContainerFromFile('messenger_amqp_transport_no_serializer');
    }

    public function testMessengerTransportConfiguration()
    {
        $container = $this->createContainerFromFile('messenger_transport');

        $this->assertSame('messenger.transport.symfony_serializer', (string) $container->getAlias('messenger.transport.serializer'));

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
            ['id' => 'logging'],
            ['id' => 'send_message'],
            ['id' => 'handle_message'],
        ], $container->getParameter('messenger.bus.commands.middleware'));
        $this->assertTrue($container->has('messenger.bus.events'));
        $this->assertSame([], $container->getDefinition('messenger.bus.events')->getArgument(0));
        $this->assertEquals([
            ['id' => 'logging'],
            ['id' => 'with_factory', 'arguments' => ['foo', true, ['bar' => 'baz']]],
            ['id' => 'send_message'],
            ['id' => 'handle_message'],
        ], $container->getParameter('messenger.bus.events.middleware'));
        $this->assertTrue($container->has('messenger.bus.queries'));
        $this->assertSame([], $container->getDefinition('messenger.bus.queries')->getArgument(0));
        $this->assertEquals([
            ['id' => 'send_message', 'arguments' => []],
            ['id' => 'handle_message', 'arguments' => []],
        ], $container->getParameter('messenger.bus.queries.middleware'));

        $this->assertTrue($container->hasAlias('message_bus'));
        $this->assertSame('messenger.bus.commands', (string) $container->getAlias('message_bus'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid middleware at path "framework.messenger": a map with a single factory id as key and its arguments as value was expected, {"foo":["qux"],"bar":["baz"]} given.
     */
    public function testMessengerMiddlewareFactoryErroneousFormat()
    {
        $this->createContainerFromFile('messenger_middleware_factory_erroneous_format');
    }

    public function testTranslator()
    {
        $container = $this->createContainerFromFile('full');
        $this->assertTrue($container->hasDefinition('translator.default'), '->registerTranslatorConfiguration() loads translation.xml');
        $this->assertEquals('translator.default', (string) $container->getAlias('translator'), '->registerTranslatorConfiguration() redefines translator service from identity to real translator');
        $options = $container->getDefinition('translator.default')->getArgument(4);

        $files = array_map('realpath', $options['resource_files']['en']);
        $ref = new \ReflectionClass('Symfony\Component\Validator\Validation');
        $this->assertContains(
            strtr(\dirname($ref->getFileName()).'/Resources/translations/validators.en.xlf', '/', \DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds Validator translation resources'
        );
        $ref = new \ReflectionClass('Symfony\Component\Form\Form');
        $this->assertContains(
            strtr(\dirname($ref->getFileName()).'/Resources/translations/validators.en.xlf', '/', \DIRECTORY_SEPARATOR),
            $files,
            '->registerTranslatorConfiguration() finds Form translation resources'
        );
        $ref = new \ReflectionClass('Symfony\Component\Security\Core\Security');
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

        $calls = $container->getDefinition('translator.default')->getMethodCalls();
        $this->assertEquals(['fr'], $calls[1][1][0]);
    }

    /**
     * @group legacy
     * @expectedDeprecation Translations directory "%s/Resources/translations" is deprecated since Symfony 4.2, use "%s/translations" instead.
     */
    public function testLegacyTranslationsDirectory()
    {
        $container = $this->createContainerFromFile('full', ['kernel.root_dir' => __DIR__.'/Fixtures']);
        $options = $container->getDefinition('translator.default')->getArgument(4);
        $files = array_map('realpath', $options['resource_files']['en']);

        $dir = str_replace('/', \DIRECTORY_SEPARATOR, __DIR__.'/Fixtures/Resources/translations/test_default.en.xlf');
        $this->assertContains($dir, $files, '->registerTranslatorConfiguration() finds translation resources in legacy directory');
    }

    public function testTranslatorMultipleFallbacks()
    {
        $container = $this->createContainerFromFile('translator_fallbacks');

        $calls = $container->getDefinition('translator.default')->getMethodCalls();
        $this->assertEquals(['en', 'fr'], $calls[1][1][0]);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testTemplatingRequiresAtLeastOneEngine()
    {
        $container = $this->createContainer();
        $loader = new FrameworkExtension();
        $loader->load([['templating' => null]], $container);
    }

    public function testValidation()
    {
        $container = $this->createContainerFromFile('full');
        $projectDir = $container->getParameter('kernel.project_dir');

        $ref = new \ReflectionClass('Symfony\Component\Form\Form');
        $xmlMappings = [
            \dirname($ref->getFileName()).'/Resources/config/validation.xml',
            strtr($projectDir.'/config/validator/foo.xml', '/', \DIRECTORY_SEPARATOR),
        ];

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $annotations = !class_exists(FullStack::class) && class_exists(Annotation::class);

        $this->assertCount($annotations ? 7 : 6, $calls);
        $this->assertSame('setConstraintValidatorFactory', $calls[0][0]);
        $this->assertEquals([new Reference('validator.validator_factory')], $calls[0][1]);
        $this->assertSame('setTranslator', $calls[1][0]);
        $this->assertEquals([new Reference('translator')], $calls[1][1]);
        $this->assertSame('setTranslationDomain', $calls[2][0]);
        $this->assertSame(['%validator.translation_domain%'], $calls[2][1]);
        $this->assertSame('addXmlMappings', $calls[3][0]);
        $this->assertSame([$xmlMappings], $calls[3][1]);
        $i = 3;
        if ($annotations) {
            $this->assertSame('enableAnnotationMapping', $calls[++$i][0]);
        }
        $this->assertSame('addMethodMapping', $calls[++$i][0]);
        $this->assertSame(['loadValidatorMetadata'], $calls[$i][1]);
        $this->assertSame('setMetadataCache', $calls[++$i][0]);
        $this->assertEquals([new Reference('validator.mapping.cache.symfony')], $calls[$i][1]);
    }

    public function testValidationService()
    {
        $container = $this->createContainerFromFile('validation_annotations', ['kernel.charset' => 'UTF-8'], false);

        $this->assertInstanceOf('Symfony\Component\Validator\Validator\ValidatorInterface', $container->get('validator'));
    }

    public function testAnnotations()
    {
        $container = $this->createContainerFromFile('full', [], true, false);
        $container->addCompilerPass(new TestAnnotationsPass());
        $container->compile();

        $this->assertEquals($container->getParameter('kernel.cache_dir').'/annotations', $container->getDefinition('annotations.filesystem_cache')->getArgument(0));
        $this->assertSame('annotations.filesystem_cache', (string) $container->getDefinition('annotation_reader')->getArgument(1));
    }

    public function testFileLinkFormat()
    {
        if (ini_get('xdebug.file_link_format') || get_cfg_var('xdebug.file_link_format')) {
            $this->markTestSkipped('A custom file_link_format is defined.');
        }

        $container = $this->createContainerFromFile('full');

        $this->assertEquals('file%link%format', $container->getParameter('debug.file_link_format'));
    }

    public function testValidationAnnotations()
    {
        $container = $this->createContainerFromFile('validation_annotations');

        $calls = $container->getDefinition('validator.builder')->getMethodCalls();

        $this->assertCount(7, $calls);
        $this->assertSame('enableAnnotationMapping', $calls[4][0]);
        $this->assertEquals([new Reference('annotation_reader')], $calls[4][1]);
        $this->assertSame('addMethodMapping', $calls[5][0]);
        $this->assertSame(['loadValidatorMetadata'], $calls[5][1]);
        $this->assertSame('setMetadataCache', $calls[6][0]);
        $this->assertEquals([new Reference('validator.mapping.cache.symfony')], $calls[6][1]);
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

        $this->assertCount(8, $calls);
        $this->assertSame('addXmlMappings', $calls[3][0]);
        $this->assertSame('addYamlMappings', $calls[4][0]);
        $this->assertSame('enableAnnotationMapping', $calls[5][0]);
        $this->assertSame('addMethodMapping', $calls[6][0]);
        $this->assertSame(['loadValidatorMetadata'], $calls[6][1]);
        $this->assertSame('setMetadataCache', $calls[7][0]);
        $this->assertEquals([new Reference('validator.mapping.cache.symfony')], $calls[7][1]);

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

        $this->assertCount($annotations ? 6 : 5, $calls);
        $this->assertSame('addXmlMappings', $calls[3][0]);
        $i = 3;
        if ($annotations) {
            $this->assertSame('enableAnnotationMapping', $calls[++$i][0]);
        }
        $this->assertSame('setMetadataCache', $calls[++$i][0]);
        $this->assertEquals([new Reference('validator.mapping.cache.symfony')], $calls[$i][1]);
        // no cache, no annotations, no static methods
    }

    /**
     * @group legacy
     * @expectedDeprecation The "framework.validation.strict_email" configuration key has been deprecated in Symfony 4.1. Use the "framework.validation.email_validation_mode" configuration key instead.
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage "strict_email" and "email_validation_mode" cannot be used together.
     */
    public function testCannotConfigureStrictEmailAndEmailValidationModeAtTheSameTime()
    {
        $this->createContainerFromFile('validation_strict_email_and_validation_mode');
    }

    /**
     * @group legacy
     * @expectedDeprecation The "framework.validation.strict_email" configuration key has been deprecated in Symfony 4.1. Use the "framework.validation.email_validation_mode" configuration key instead.
     */
    public function testEnabledStrictEmailOptionIsMappedToStrictEmailValidationMode()
    {
        $container = $this->createContainerFromFile('validation_strict_email_enabled');

        $this->assertSame('strict', $container->getDefinition('validator.email')->getArgument(0));
    }

    /**
     * @group legacy
     * @expectedDeprecation The "framework.validation.strict_email" configuration key has been deprecated in Symfony 4.1. Use the "framework.validation.email_validation_mode" configuration key instead.
     */
    public function testDisabledStrictEmailOptionIsMappedToLooseEmailValidationMode()
    {
        $container = $this->createContainerFromFile('validation_strict_email_disabled');

        $this->assertSame('loose', $container->getDefinition('validator.email')->getArgument(0));
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
        $this->assertContains('foo.yml', $calls[4][1][0][0]);
        $this->assertContains('validation.yml', $calls[4][1][0][1]);
        $this->assertContains('validation.yaml', $calls[4][1][0][2]);
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
        $this->assertEquals('Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader', $argument[0]->getClass());
        $this->assertNull($container->getDefinition('serializer.mapping.class_metadata_factory')->getArgument(1));
        $this->assertEquals(new Reference('serializer.name_converter.camel_case_to_snake_case'), $container->getDefinition('serializer.name_converter.metadata_aware')->getArgument(1));
        $this->assertEquals(new Reference('property_info', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE), $container->getDefinition('serializer.normalizer.object')->getArgument(3));
        $this->assertEquals(['setCircularReferenceHandler', [new Reference('my.circular.reference.handler')]], $container->getDefinition('serializer.normalizer.object')->getMethodCalls()[0]);
        $this->assertEquals(['setMaxDepthHandler', [new Reference('my.max.depth.handler')]], $container->getDefinition('serializer.normalizer.object')->getMethodCalls()[1]);
    }

    public function testRegisterSerializerExtractor()
    {
        $container = $this->createContainerFromFile('full');

        $serializerExtractorDefinition = $container->getDefinition('property_info.serializer_extractor');

        $this->assertEquals('serializer.mapping.class_metadata_factory', $serializerExtractorDefinition->getArgument(0)->__toString());
        $this->assertFalse($serializerExtractorDefinition->isPublic());
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
        if (!class_exists(DateIntervalNormalizer::class)) {
            $this->markTestSkipped('The DateIntervalNormalizer has been introduced in the Serializer Component version 3.4.');
        }

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

    public function testJsonSerializableNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.json_serializable');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals(JsonSerializableNormalizer::class, $definition->getClass());
        $this->assertEquals(-900, $tag[0]['priority']);
    }

    public function testObjectNormalizerRegistered()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('serializer.normalizer.object');
        $tag = $definition->getTag('serializer.normalizer');

        $this->assertEquals('Symfony\Component\Serializer\Normalizer\ObjectNormalizer', $definition->getClass());
        $this->assertEquals(-1000, $tag[0]['priority']);
    }

    public function testSerializerCacheActivated()
    {
        $container = $this->createContainerFromFile('serializer_enabled');

        $this->assertTrue($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));

        $cache = $container->getDefinition('serializer.mapping.cache_class_metadata_factory')->getArgument(1);
        $this->assertEquals(new Reference('serializer.mapping.cache.symfony'), $cache);
    }

    public function testSerializerCacheDisabled()
    {
        $container = $this->createContainerFromFile('serializer_enabled', ['kernel.debug' => true, 'kernel.container_class' => __CLASS__]);
        $this->assertFalse($container->hasDefinition('serializer.mapping.cache_class_metadata_factory'));
    }

    public function testSerializerMapping()
    {
        $container = $this->createContainerFromFile('serializer_mapping', ['kernel.bundles_metadata' => ['TestBundle' => ['namespace' => 'Symfony\\Bundle\\FrameworkBundle\\Tests', 'path' => __DIR__.'/Fixtures/TestBundle']]]);
        $projectDir = $container->getParameter('kernel.project_dir');
        $configDir = __DIR__.'/Fixtures/TestBundle/Resources/config';
        $expectedLoaders = [
            new Definition(AnnotationLoader::class, [new Reference('annotation_reader')]),
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

    public function testAssetHelperWhenAssetsAreEnabled()
    {
        $container = $this->createContainerFromFile('full');
        $packages = $container->getDefinition('templating.helper.assets')->getArgument(0);

        $this->assertSame('assets.packages', (string) $packages);
    }

    public function testAssetHelperWhenTemplatesAreEnabledAndNoAssetsConfiguration()
    {
        $container = $this->createContainerFromFile('templating_no_assets');
        $packages = $container->getDefinition('templating.helper.assets')->getArgument(0);

        $this->assertSame('assets.packages', (string) $packages);
    }

    public function testAssetsHelperIsRemovedWhenPhpTemplatingEngineIsEnabledAndAssetsAreDisabled()
    {
        $container = $this->createContainerFromFile('templating_php_assets_disabled');

        $this->assertTrue(!$container->has('templating.helper.assets'), 'The templating.helper.assets helper service is removed when assets are disabled.');
    }

    public function testAssetHelperWhenAssetsAndTemplatesAreDisabled()
    {
        $container = $this->createContainerFromFile('default_config');

        $this->assertFalse($container->hasDefinition('templating.helper.assets'));
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

    public function testCacheDefaultRedisProviderWithEnvVar()
    {
        $container = $this->createContainerFromFile('cache_env_var');

        $redisUrl = 'redis://paas.com';
        $providerId = '.cache_connection.'.ContainerBuilder::hash($redisUrl);

        $this->assertTrue($container->hasDefinition($providerId));

        $url = $container->getDefinition($providerId)->getArgument(0);

        $this->assertSame($redisUrl, $url);
    }

    public function testCachePoolServices()
    {
        $container = $this->createContainerFromFile('cache');

        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.foo', 'cache.adapter.apcu', 30);
        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.bar', 'cache.adapter.doctrine', 5);
        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.baz', 'cache.adapter.filesystem', 7);
        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.foobar', 'cache.adapter.psr6', 10);
        $this->assertCachePoolServiceDefinitionIsCreated($container, 'cache.def', 'cache.app', 11);
    }

    public function testRemovesResourceCheckerConfigCacheFactoryArgumentOnlyIfNoDebug()
    {
        $container = $this->createContainer(['kernel.debug' => true]);
        (new FrameworkExtension())->load([], $container);
        $this->assertCount(1, $container->getDefinition('config_cache_factory')->getArguments());

        $container = $this->createContainer(['kernel.debug' => false]);
        (new FrameworkExtension())->load([], $container);
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

        $expected = ['session', 'initialized_session', 'session_storage', 'request_stack'];
        $this->assertEquals($expected, array_keys($container->getDefinition('session_listener')->getArgument(0)->getValues()));
    }

    protected function createContainer(array $data = [])
    {
        return new ContainerBuilder(new ParameterBag(array_merge([
            'kernel.bundles' => ['FrameworkBundle' => 'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'],
            'kernel.bundles_metadata' => ['FrameworkBundle' => ['namespace' => 'Symfony\\Bundle\\FrameworkBundle', 'path' => __DIR__.'/../..']],
            'kernel.cache_dir' => __DIR__,
            'kernel.project_dir' => __DIR__,
            'kernel.debug' => false,
            'kernel.environment' => 'test',
            'kernel.name' => 'kernel',
            'kernel.root_dir' => __DIR__,
            'kernel.container_class' => 'testContainer',
            'container.build_hash' => 'Abc1234',
            'container.build_id' => hash('crc32', 'Abc123423456789'),
            'container.build_time' => 23456789,
        ], $data)));
    }

    protected function createContainerFromFile($file, $data = [], $resetCompilerPasses = true, $compile = true)
    {
        $cacheKey = md5(\get_class($this).$file.serialize($data));
        if ($compile && isset(self::$containerCache[$cacheKey])) {
            return self::$containerCache[$cacheKey];
        }
        $container = $this->createContainer($data);
        $container->registerExtension(new FrameworkExtension());
        $this->loadFromFile($container, $file);

        if ($resetCompilerPasses) {
            $container->getCompilerPassConfig()->setOptimizationPasses([]);
            $container->getCompilerPassConfig()->setRemovingPasses([]);
        }
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([new LoggerPass()]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([new AddConstraintValidatorsPass(), new TranslatorPass('translator.default', 'translation.reader')]);
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

        switch ($adapter) {
            case 'cache.adapter.apcu':
                $this->assertSame(ApcuAdapter::class, $parentDefinition->getClass());
                break;
            case 'cache.adapter.doctrine':
                $this->assertSame(DoctrineAdapter::class, $parentDefinition->getClass());
                break;
            case 'cache.app':
                if (ChainAdapter::class === $parentDefinition->getClass()) {
                    break;
                }
                // no break
            case 'cache.adapter.filesystem':
                $this->assertSame(FilesystemAdapter::class, $parentDefinition->getClass());
                break;
            case 'cache.adapter.psr6':
                $this->assertSame(ProxyAdapter::class, $parentDefinition->getClass());
                break;
            case 'cache.adapter.redis':
                $this->assertSame(RedisAdapter::class, $parentDefinition->getClass());
                break;
            default:
                $this->fail('Unresolved adapter: '.$adapter);
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
