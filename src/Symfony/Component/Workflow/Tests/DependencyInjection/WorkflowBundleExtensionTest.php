<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\DependencyInjection\AddEventAliasesPass;
use Symfony\Component\Workflow\Arc;
use Symfony\Component\Workflow\Definition as WorkflowDefinition;
use Symfony\Component\Workflow\DependencyInjection\WorkflowDebugPass;
use Symfony\Component\Workflow\DependencyInjection\WorkflowGuardListenerPass;
use Symfony\Component\Workflow\DependencyInjection\WorkflowValidatorPass;
use Symfony\Component\Workflow\Exception\InvalidDefinitionException;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Tests\Fixtures\DefinitionValidator;
use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;
use Symfony\Component\Workflow\WorkflowBundle;
use Symfony\Component\Workflow\WorkflowEvents;

class WorkflowBundleExtensionTest extends TestCase
{
    public function testWorkflows()
    {
        DefinitionValidator::$called = false;

        $container = $this->createContainerFromFile('workflows', compile: false);
        $container->addCompilerPass(new WorkflowValidatorPass());
        $container->compile();

        $this->assertTrue($container->hasDefinition('workflow.article'), 'Workflow is registered as a service');
        $this->assertSame('workflow.abstract', $container->getDefinition('workflow.article')->getParent());

        $args = $container->getDefinition('workflow.article')->getArguments();
        $this->assertArrayHasKey('index_0', $args);
        $this->assertArrayHasKey('index_1', $args);
        $this->assertArrayHasKey('index_3', $args);
        $this->assertArrayHasKey('index_4', $args);
        $this->assertNull($args['index_4'], 'Workflows has eventsToDispatch=null');

        $tags = $container->getDefinition('workflow.article')->getTags();
        $this->assertArrayHasKey('workflow', $tags);
        $this->assertArrayHasKey('workflow.workflow', $tags);
        $this->assertSame([['name' => 'article']], $tags['workflow.workflow']);
        $this->assertSame('article', $tags['workflow'][0]['name'] ?? null);
        $this->assertSame([
            'title' => 'article workflow',
            'description' => 'workflow for articles',
        ], $tags['workflow'][0]['metadata'] ?? null);

        $this->assertTrue($container->hasDefinition('workflow.article.definition'), 'Workflow definition is registered as a service');
        $this->assertTrue(DefinitionValidator::$called, 'DefinitionValidator is called');

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

        $tags = $container->getDefinition('state_machine.pull_request')->getTags();
        $this->assertArrayHasKey('workflow', $tags);
        $this->assertArrayHasKey('workflow.state_machine', $tags);
        $this->assertSame([['name' => 'pull_request']], $tags['workflow.state_machine']);
        $this->assertSame('pull_request', $tags['workflow'][0]['name'] ?? null);
        $this->assertSame([
            'title' => 'workflow title',
        ], $tags['workflow'][0]['metadata'] ?? null);

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
        $this->assertSame(InMemoryMetadataStore::class, $metadataStoreDefinition->getClass());

        $workflowMetadata = $metadataStoreDefinition->getArgument(0);
        $this->assertSame(['title' => 'workflow title'], $workflowMetadata);

        $placesMetadata = $metadataStoreDefinition->getArgument(1);
        $this->assertArrayHasKey('start', $placesMetadata);
        $this->assertSame(['title' => 'place start title'], $placesMetadata['start']);

        $transitionsMetadata = $metadataStoreDefinition->getArgument(2);
        $this->assertSame(\SplObjectStorage::class, $transitionsMetadata->getClass());
        $transitionsMetadataCall = $transitionsMetadata->getMethodCalls()[0];
        $this->assertSame('offsetSet', $transitionsMetadataCall[0]);
        $params = $transitionsMetadataCall[1];
        $this->assertCount(2, $params);
        $this->assertInstanceOf(Reference::class, $params[0]);
        $this->assertSame('.state_machine.pull_request.transition.0', (string) $params[0]);

        $serviceMarkingStoreWorkflowDefinition = $container->getDefinition('workflow.service_marking_store_workflow');
        /** @var Reference $markingStoreRef */
        $markingStoreRef = $serviceMarkingStoreWorkflowDefinition->getArgument(1);
        $this->assertInstanceOf(Reference::class, $markingStoreRef);
        $this->assertEquals('workflow_service', (string) $markingStoreRef);

        $this->assertTrue($container->hasDefinition('workflow.registry'), 'Workflow registry is registered as a service');
        $registryDefinition = $container->getDefinition('workflow.registry');
        $this->assertGreaterThan(0, \count($registryDefinition->getMethodCalls()));
    }

    public function testWorkflowAreValidated()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('A transition from a place/state must have an unique name. Multiple transitions named "go" from place/state "first" were found on StateMachine "my_workflow".');
        $container = $this->createContainerFromFile('workflow_not_valid', compile: false);
        $container->addCompilerPass(new WorkflowValidatorPass());
        $container->compile();
    }

    public function testWorkflowValidationStateMachine()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('A transition from a place/state must have an unique name. Multiple transitions named "a_to_b" from place/state "a" were found on StateMachine "article".');

        $container = $this->createContainer();
        $container->loadFromExtension('workflow', [
            'article' => [
                'type' => 'state_machine',
                'supports' => [self::class],
                'places' => ['a', 'b', 'c'],
                'transitions' => [
                    'a_to_b' => ['from' => ['a'], 'to' => ['b', 'c']],
                ],
            ],
        ]);
        $container->addCompilerPass(new WorkflowValidatorPass());
        $container->compile();
    }

    #[DataProvider('provideWorkflowValidationCustomTests')]
    public function testWorkflowValidationCustomBroken(string $class, string $message)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        $container = $this->createContainer();
        $container->loadFromExtension('workflow', [
            'article' => [
                'type' => 'state_machine',
                'supports' => [self::class],
                'places' => ['a', 'b'],
                'transitions' => [
                    'a_to_b' => ['from' => ['a'], 'to' => ['b']],
                ],
                'definition_validators' => [$class],
            ],
        ]);
        $container->compile();
    }

    public static function provideWorkflowValidationCustomTests()
    {
        yield ['classDoesNotExist', 'Invalid configuration for path "workflow.workflows.article.definition_validators.0": The validation class "classDoesNotExist" does not exist.'];

        yield [\DateTime::class, 'Invalid configuration for path "workflow.workflows.article.definition_validators.0": The validation class "DateTime" is not an instance of "Symfony\Component\Workflow\Validator\DefinitionValidatorInterface".'];

        yield [WorkflowValidatorWithConstructor::class, 'Invalid configuration for path "workflow.workflows.article.definition_validators.0": The "Symfony\\\\Component\\\\Workflow\\\\Tests\\\\DependencyInjection\\\\WorkflowValidatorWithConstructor" validation class constructor must not have any arguments.'];
    }

    public function testWorkflowDefaultMarkingStoreDefinition()
    {
        $container = $this->createContainer();
        $container->loadFromExtension('workflow', [
            'workflow_a' => [
                'type' => 'state_machine',
                'marking_store' => ['type' => 'method', 'property' => 'status'],
                'supports' => [self::class],
                'places' => ['a', 'b'],
                'transitions' => [
                    'a_to_b' => ['from' => ['a'], 'to' => ['b']],
                ],
            ],
            'workflow_b' => [
                'type' => 'state_machine',
                'supports' => [self::class],
                'places' => ['a', 'b'],
                'transitions' => [
                    'a_to_b' => ['from' => ['a'], 'to' => ['b']],
                ],
            ],
        ]);
        $container->compile();

        $argumentsA = $container->getDefinition('state_machine.workflow_a')->getArguments();
        $this->assertArrayHasKey('index_1', $argumentsA, 'workflow_a has a marking_store argument');
        $this->assertNotNull($argumentsA['index_1'], 'workflow_a marking_store argument is not null');

        $argumentsB = $container->getDefinition('state_machine.workflow_b')->getArguments();
        $this->assertArrayHasKey('index_1', $argumentsB, 'workflow_b has a marking_store argument');
        $this->assertNull($argumentsB['index_1'], 'workflow_b marking_store argument is null');
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

    public function testWorkflowWithSimplisticPlaceFollowedByComplexPlace()
    {
        $container = $this->createContainerFromFile('workflow_with_simplistic_place_follow_by_complex_place_config');

        $this->assertTrue($container->hasDefinition('workflow.article'), 'Workflow is parsed and registered as a service');
    }

    public function testWorkflowWithComplexPlaceFollowedBySimplisticPlace()
    {
        $container = $this->createContainerFromFile('workflow_with_complex_place_follow_by_simplistic_place_config');

        $this->assertTrue($container->hasDefinition('workflow.article'), 'Workflow is parsed and registered as a service');
    }

    public function testWorkflowWithSimplisticPlaceFollowedByComplexPlaceWithAlternativeSyntax()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "wait_for_journalist" under "workflow.workflows.article.places.1". Available options are "metadata", "name".');
        $this->createContainerFromFile('workflow_with_simplistic_place_follow_by_complex_place_config_with_alternative_syntax');
    }

    public function testWorkflowWithComplexPlaceFollowedBySimplisticPlaceWithAlternativeSyntax()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "draft" under "workflow.workflows.article.places.0". Available options are "metadata", "name".');
        $this->createContainerFromFile('workflow_with_complex_place_follow_by_simplistic_place_config_with_alternative_syntax');
    }

    public function testWorkflowMultipleTransitionsWithSameName()
    {
        $container = $this->createContainerFromFile('workflow_with_multiple_transitions_with_same_name');

        $this->assertTrue($container->hasDefinition('workflow.article'), 'Workflow is registered as a service');
        $this->assertTrue($container->hasDefinition('workflow.article.definition'), 'Workflow definition is registered as a service');

        $workflowDefinition = $container->getDefinition('workflow.article.definition');

        $transitions = $workflowDefinition->getArgument(1);

        $this->assertCount(5, $transitions);

        $this->assertTransitionReference($container, '.workflow.article.transition.0', 'request_review', [['place' => 'draft', 'weight' => 1]], [['place' => 'wait_for_journalist', 'weight' => 1], ['place' => 'wait_for_spellchecker', 'weight' => 1]], $transitions[0]);
        $this->assertTransitionReference($container, '.workflow.article.transition.1', 'journalist_approval', [['place' => 'wait_for_journalist', 'weight' => 1]], [['place' => 'approved_by_journalist', 'weight' => 1]], $transitions[1]);
        $this->assertTransitionReference($container, '.workflow.article.transition.2', 'spellchecker_approval', [['place' => 'wait_for_spellchecker', 'weight' => 1]], [['place' => 'approved_by_spellchecker', 'weight' => 1]], $transitions[2]);
        $this->assertTransitionReference($container, '.workflow.article.transition.3', 'publish', [['place' => 'approved_by_journalist', 'weight' => 1], ['place' => 'approved_by_spellchecker', 'weight' => 1]], [['place' => 'published', 'weight' => 1]], $transitions[3]);
        $this->assertTransitionReference($container, '.workflow.article.transition.4', 'publish', [['place' => 'draft', 'weight' => 1]], [['place' => 'published', 'weight' => 2]], $transitions[4]);
    }

    public function testWorkflowEnumPlaces()
    {
        $container = $this->createContainerFromFile('workflow_enum_places');

        $workflowDefinition = $container->getDefinition('state_machine.enum.definition');
        $this->assertSame(['a', 'b', 'c'], $workflowDefinition->getArgument(0));
        $this->assertTransitionReference($container, '.state_machine.enum.transition.0', 'one', [['place' => 'a', 'weight' => 1]], [['place' => 'b', 'weight' => 1]], $workflowDefinition->getArgument(1)[0]);
        $this->assertTransitionReference($container, '.state_machine.enum.transition.1', 'two', [['place' => 'b', 'weight' => 1]], [['place' => 'c', 'weight' => 1]], $workflowDefinition->getArgument(1)[1]);
    }

    public function testWorkflowGlobPlaces()
    {
        $container = $this->createContainerFromFile('workflow_glob_places');

        $workflowDefinition = $container->getDefinition('state_machine.enum.definition');
        $this->assertSame(['a', 'b', 'c'], $workflowDefinition->getArgument(0));
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

        $this->assertTrue($container->hasDefinition('workflow.registry'));
        $this->assertTrue($container->hasDefinition('console.command.workflow_dump'));
    }

    public function testWorkflowServicesAreEnabledByDefault()
    {
        $container = $this->createContainer();
        $container->loadFromExtension('workflow', []);
        $container->compile();

        $this->assertTrue($container->hasDefinition('workflow.registry'));
        $this->assertTrue($container->hasDefinition('console.command.workflow_dump'));
    }

    public function testWorkflowServicesCanBeDisabled()
    {
        $container = $this->createContainer();
        $container->loadFromExtension('workflow', ['enabled' => false]);
        $container->compile();

        $this->assertFalse($container->hasDefinition('workflow.registry'));
        $this->assertFalse($container->hasDefinition('console.command.workflow_dump'));
    }

    public function testDebugServicesAreLoadedInDebugMode()
    {
        $container = $this->createContainerFromFile('workflows_enabled');
        $this->assertFalse($container->hasDefinition('data_collector.workflow'));

        $container = $this->createContainerFromFile('workflows_enabled', debug: true);
        $this->assertTrue($container->hasDefinition('data_collector.workflow'));
    }

    public function testBuildRegistersTheCompilerPasses()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        (new WorkflowBundle())->build($container);

        $passes = array_map(get_class(...), $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        $this->assertContains(AddEventAliasesPass::class, $passes);
        $this->assertContains(WorkflowGuardListenerPass::class, $passes);
        $this->assertContains(WorkflowValidatorPass::class, $passes);
        $this->assertNotContains(WorkflowDebugPass::class, $passes);

        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        (new WorkflowBundle())->build($container);

        $this->assertContains(WorkflowDebugPass::class, array_map(get_class(...), $container->getCompilerPassConfig()->getBeforeOptimizationPasses()));
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

    public function testWorkflowsWithDisabledEvents()
    {
        $container = $this->createContainerFromFile('workflow_with_disabled_events');

        $eventsToDispatch = $container->getDefinition('state_machine.my_workflow')->getArgument('index_4');

        $this->assertSame(['!'.WorkflowEvents::ANNOUNCE], $eventsToDispatch);
    }

    public function testWorkflowTransitionsPerformNoDeepMerging()
    {
        $container = $this->createContainer();
        $this->loadFromFile($container, 'workflow_base_config');
        $this->loadFromFile($container, 'workflow_override_config');
        $container->compile();

        $transitions = $container->getDefinition('workflow.test_workflow.definition')->getArgument(1);

        $this->assertCount(1, $transitions);
        $this->assertTransitionReference($container, '.workflow.test_workflow.transition.0', 'base_transition', [['place' => 'middle', 'weight' => 1]], [['place' => 'alternative', 'weight' => 1]], $transitions[0]);
    }

    private function createContainerFromFile(string $file, bool $compile = true, bool $debug = false): ContainerBuilder
    {
        $container = $this->createContainer($debug);
        $this->loadFromFile($container, $file);

        if ($compile) {
            $container->compile();
        }

        return $container;
    }

    private function createContainer(bool $debug = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', $debug);
        $container->registerExtension(new WorkflowBundle()->getContainerExtension());
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([]);
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);

        return $container;
    }

    private function loadFromFile(ContainerBuilder $container, string $file): void
    {
        (new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures')))->load($file.'.php');
    }

    private function assertTransitionReference(ContainerBuilder $container, string $expectedServiceId, string $expectedName, array $expectedFroms, array $expectedTos, Reference $transition): void
    {
        $this->assertSame($expectedServiceId, (string) $transition);

        $args = $container->getDefinition($transition)->getArguments();
        $this->assertCount(3, $args);
        $this->assertSame($expectedName, $args[0]);

        $this->assertCount(\count($expectedFroms), $args[1]);
        foreach ($expectedFroms as $i => ['place' => $place, 'weight' => $weight]) {
            $this->assertInstanceOf(Definition::class, $args[1][$i]);
            $this->assertSame(Arc::class, $args[1][$i]->getClass());
            $arcArgs = array_values($args[1][$i]->getArguments());
            $this->assertSame($place, $arcArgs[0]);
            $this->assertSame($weight, $arcArgs[1]);
        }

        $this->assertCount(\count($expectedTos), $args[2]);
        foreach ($expectedTos as $i => ['place' => $place, 'weight' => $weight]) {
            $this->assertInstanceOf(Definition::class, $args[2][$i]);
            $this->assertSame(Arc::class, $args[2][$i]->getClass());
            $arcArgs = array_values($args[2][$i]->getArguments());
            $this->assertSame($place, $arcArgs[0]);
            $this->assertSame($weight, $arcArgs[1]);
        }
    }
}

class WorkflowValidatorWithConstructor implements DefinitionValidatorInterface
{
    public function __construct(bool $enabled)
    {
    }

    public function validate(WorkflowDefinition $definition, string $name): void
    {
    }
}
