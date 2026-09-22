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
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\Attribute\AsWorkflowDefinition;
use Symfony\Component\Workflow\Attribute\Place;
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\Definition as WorkflowDefinition;
use Symfony\Component\Workflow\DependencyInjection\WorkflowDefinitionPass;
use Symfony\Component\Workflow\Exception\InvalidDefinitionException;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Tests\Fixtures\DefinitionValidator;
use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;
use Symfony\Component\Workflow\WeightedPlace;
use Symfony\Component\Workflow\WorkflowBundle;
use Symfony\Component\Workflow\WorkflowEvents;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\WorkflowType;

final class WorkflowDefinitionPassTest extends TestCase
{
    public function testRegistersAFullWorkflowFromAStringBackedEnum()
    {
        DefinitionValidator::$called = false;

        $container = $this->createContainer([ArticleWorkflowDefinition::class]);
        $container->compile();

        $this->assertTrue($container->getDefinition(ArticleWorkflowDefinition::class)->hasTag('container.excluded'));
        $this->assertTrue($container->hasDefinition('workflow.article'));
        $this->assertSame('workflow.abstract', $container->getDefinition('workflow.article')->getParent());
        $this->assertSame([], $container->getDefinition('workflow.article')->getArgument('index_4'));
        $this->assertFalse($container->getDefinition('workflow.article')->getArgument('index_1')->getArgument(0));
        $this->assertSame('status', $container->getDefinition('workflow.article')->getArgument('index_1')->getArgument(1));
        $this->assertSame([
            'workflow' => [[
                'name' => 'article',
                'metadata' => ['title' => 'Article publishing'],
                'definition_validators' => [DefinitionValidator::class, 'Symfony\\Component\\Workflow\\Validator\\WorkflowValidator'],
                'definition_id' => 'workflow.article.definition',
            ]],
            'workflow.workflow' => [['name' => 'article']],
        ], $container->getDefinition('workflow.article')->getTags());

        $definition = $container->getDefinition('workflow.article.definition');
        $this->assertSame(['draft', 'permit', 'ready', 'failed', 'timed_out'], $definition->getArgument(0));
        $this->assertSame(['draft', 'permit'], $definition->getArgument(2));
        $this->assertCount(3, $definition->getArgument(1));

        $this->assertTransition($container, '.workflow.article.transition.0', 'assemble', [['draft', 2], ['permit', 1]], [['ready', 3], ['failed', 1]], $definition->getArgument(1)[0]);
        $this->assertTransition($container, '.workflow.article.transition.1', 'retry', [['failed', 1]], [['ready', 1]], $definition->getArgument(1)[1]);
        $this->assertTransition($container, '.workflow.article.transition.2', 'retry', [['timed_out', 1]], [['ready', 1]], $definition->getArgument(1)[2]);

        $metadataStore = $container->getDefinition('workflow.article.metadata_store');
        $this->assertSame(InMemoryMetadataStore::class, $metadataStore->getClass());
        $this->assertSame(['title' => 'Article publishing'], $metadataStore->getArgument(0));
        $this->assertSame(['draft' => ['label' => 'Draft']], $metadataStore->getArgument(1));
        $metadataCalls = $metadataStore->getArgument(2)->getMethodCalls();
        $this->assertCount(3, $metadataCalls);
        $this->assertSame('.workflow.article.transition.0', (string) $metadataCalls[0][1][0]);
        $this->assertSame(['label' => 'Assemble'], $metadataCalls[0][1][1]);
        $this->assertSame('.workflow.article.transition.1', (string) $metadataCalls[1][1][0]);
        $this->assertSame(['reason' => 'failed'], $metadataCalls[1][1][1]);
        $this->assertSame('.workflow.article.transition.2', (string) $metadataCalls[2][1][0]);
        $this->assertSame(['reason' => 'timeout'], $metadataCalls[2][1][1]);

        $auditTrail = $container->getDefinition('.workflow.article.listener.audit_trail');
        $this->assertSame([
            'monolog.logger' => [['channel' => 'workflow']],
            'kernel.event_listener' => [
                ['event' => 'workflow.article.leave', 'method' => 'onLeave'],
                ['event' => 'workflow.article.transition', 'method' => 'onTransition'],
                ['event' => 'workflow.article.enter', 'method' => 'onEnter'],
            ],
        ], $auditTrail->getTags());
        $this->assertSame('logger', (string) $auditTrail->getArgument(0));
        $this->assertTrue($container->hasDefinition('.workflow.article.listener.guard'));
        $guards = $container->getDefinition('.workflow.article.listener.guard')->getArgument(0);
        $this->assertSame('.workflow.article.transition.0', (string) $guards['workflow.article.guard.assemble'][0]->getArgument(0));
        $this->assertSame('true', $guards['workflow.article.guard.assemble'][0]->getArgument(1));
        $this->assertSame('.workflow.article.transition.1', (string) $guards['workflow.article.guard.retry'][0]->getArgument(0));
        $this->assertSame('true', $guards['workflow.article.guard.retry'][0]->getArgument(1));
        $this->assertSame('.workflow.article.transition.2', (string) $guards['workflow.article.guard.retry'][1]->getArgument(0));
        $this->assertSame('false', $guards['workflow.article.guard.retry'][1]->getArgument(1));

        $this->assertSame('workflow.article', (string) $container->getAlias(WorkflowInterface::class.' $articleWorkflow'));
        $this->assertSame(WorkflowInterface::class.' $articleWorkflow', (string) $container->getAlias('.'.WorkflowInterface::class.' $article'));
        $registryCalls = $container->getDefinition('workflow.registry')->getMethodCalls();
        $this->assertCount(1, $registryCalls);
        $this->assertSame('workflow.article', (string) $registryCalls[0][1][0]);
        $this->assertTrue(DefinitionValidator::$called);
    }

    public function testRegistersAStateMachineFromAStringBackedEnumWithRepeatedTransitionAttributes()
    {
        $container = $this->createContainer([PostStateDefinition::class]);
        $container->compile();

        $this->assertTrue($container->hasDefinition('state_machine.post'));
        $this->assertSame('state_machine.abstract', $container->getDefinition('state_machine.post')->getParent());
        $this->assertSame([
            'workflow' => [[
                'name' => 'post',
                'metadata' => [],
                'definition_validators' => ['Symfony\\Component\\Workflow\\Validator\\StateMachineValidator'],
                'definition_id' => 'state_machine.post.definition',
            ]],
            'workflow.state_machine' => [['name' => 'post']],
        ], $container->getDefinition('state_machine.post')->getTags());
        $this->assertFalse($container->hasDefinition('.state_machine.post.listener.audit_trail'));

        $definition = $container->getDefinition('state_machine.post.definition');
        $this->assertSame(['Draft', 'Reviewed', 'Published'], $definition->getArgument(0));
        $this->assertSame(['Draft'], $definition->getArgument(2));
        $this->assertCount(3, $definition->getArgument(1));
        $this->assertTransition($container, '.state_machine.post.transition.0', 'publish', [['Draft', 1]], [['Published', 1]], $definition->getArgument(1)[0]);
        $this->assertTransition($container, '.state_machine.post.transition.1', 'publish', [['Reviewed', 1]], [['Published', 1]], $definition->getArgument(1)[1]);
        $this->assertTransition($container, '.state_machine.post.transition.2', 'review', [['Draft', 1]], [['Reviewed', 1]], $definition->getArgument(1)[2]);
    }

    public function testRejectsAUnitEnumDefinition()
    {
        $container = $this->createContainer([UnitStateDefinition::class]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Workflow definition enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\UnitStateDefinition" must be string-backed.');

        $container->compile();
    }

    public function testRegistersCustomSupportAndMarkingStoreServices()
    {
        $container = $this->createContainer([CustomServicesDefinition::class]);
        $container->compile();

        $markingStore = $container->getDefinition('state_machine.custom')->getArgument('index_1');
        $this->assertInstanceOf(Reference::class, $markingStore);
        $this->assertSame('custom.marking_store', (string) $markingStore);
        $this->assertNull($container->getDefinition('state_machine.custom')->getArgument('index_4'));

        $registryCall = $container->getDefinition('workflow.registry')->getMethodCalls()[0];
        $this->assertSame('state_machine.custom', (string) $registryCall[1][0]);
        $this->assertInstanceOf(Reference::class, $registryCall[1][1]);
        $this->assertSame('custom.support_strategy', (string) $registryCall[1][1]);
    }

    public function testRegistersMultipleAndInterfaceSupports()
    {
        $container = $this->createContainer([MultipleSupportsDefinition::class]);
        $container->compile();

        $registryCalls = $container->getDefinition('workflow.registry')->getMethodCalls();
        $this->assertCount(2, $registryCalls);
        $this->assertSame(SupportedSubject::class, $registryCalls[0][1][1]->getArgument(0));
        $this->assertSame(SupportedSubjectInterface::class, $registryCalls[1][1][1]->getArgument(0));
    }

    public function testRegistersMethodMarkingStoreForAStateMachine()
    {
        $container = $this->createContainer([StateMachineMarkingStoreDefinition::class]);
        $container->compile();

        $markingStore = $container->getDefinition('state_machine.state_machine_store')->getArgument('index_1');
        $this->assertTrue($markingStore->getArgument(0));
        $this->assertSame('state', $markingStore->getArgument(1));
    }

    /**
     * @param list<string> $events
     */
    #[DataProvider('provideEventLists')]
    public function testRegistersValidEventLists(string $definition, array $events)
    {
        $container = $this->createContainer([$definition]);
        $container->compile();

        $this->assertSame($events, $container->getDefinition('state_machine.events')->getArgument('index_4'));
    }

    public static function provideEventLists(): iterable
    {
        yield 'allow list' => [AllowedEventsDefinition::class, [WorkflowEvents::ENTER, WorkflowEvents::LEAVE]];
        yield 'block list' => [BlockedEventsDefinition::class, ['!'.WorkflowEvents::ANNOUNCE, '!'.WorkflowEvents::COMPLETED]];
    }

    public function testAllowsAnUnannotatedTransitionNameConstant()
    {
        $container = $this->createContainer([TransitionNameConstantDefinition::class]);
        $container->compile();

        $transition = $container->getDefinition('state_machine.transition_name.definition')->getArgument(1)[0];
        $this->assertSame(TransitionNameConstantDefinition::GO, $container->getDefinition((string) $transition)->getArgument(0));
    }

    public function testCreatesTheUsualRuntimeServiceAndRemovesTheEnumResource()
    {
        $container = $this->createContainer([PostStateDefinition::class], preserveDefinitions: false);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->getDefinition('state_machine.post')->setPublic(true);
            }
        });
        $container->compile();

        $this->assertFalse($container->has(PostStateDefinition::class));
        $this->assertInstanceOf(StateMachine::class, $container->get('state_machine.post'));
    }

    public function testDebugAndProfilerServicesSeeAttributeWorkflows()
    {
        $container = $this->createContainer([PostStateDefinition::class], debug: true);
        $container->register('debug.stopwatch', \stdClass::class);
        $container->compile();

        $this->assertTrue($container->hasDefinition('data_collector.workflow'));
        $this->assertSame([
            'template' => '@WebProfiler/Collector/workflow.html.twig',
            'id' => 'workflow',
        ], $container->getDefinition('data_collector.workflow')->getTag('data_collector')[0]);
        $this->assertSame('state_machine.post', $container->getDefinition('debug.state_machine.post')->getDecoratedService()[0]);
    }

    #[DataProvider('provideInvalidStateMachines')]
    public function testExistingStateMachineValidationAppliesToAttributeDefinitions(string $definition, string $message)
    {
        $container = $this->createContainer([$definition]);

        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage($message);

        $container->compile();
    }

    public static function provideInvalidStateMachines(): iterable
    {
        yield 'weighted arc' => [WeightedStateDefinition::class, 'weight equals to 2'];
        yield 'same name from same place' => [DuplicateOutgoingTransitionDefinition::class, 'Multiple transitions named "go" from place/state "A"'];
    }

    public function testRejectsAConfiguredWorkflowWithTheSameName()
    {
        $container = $this->createContainer([PostStateDefinition::class], [
            'post' => [
                'supports' => [\stdClass::class],
                'places' => ['a', 'b'],
                'transitions' => ['go' => ['from' => 'a', 'to' => 'b']],
            ],
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Workflow "post" is declared both by enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\PostStateDefinition" and configuration. Workflow names must be unique.');

        $container->compile();
    }

    public function testRejectsTwoAttributeDefinitionsWithTheSameName()
    {
        $container = $this->createContainer([PostStateDefinition::class, DuplicatePostStateDefinition::class]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Workflow "post" is declared by both enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\PostStateDefinition" and enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\DuplicatePostStateDefinition".');

        $container->compile();
    }

    public function testRejectsAnExistingWorkflowServiceDefinitionBeforeRegisteringAnyWorkflow()
    {
        $container = $this->createContainer([PostStateDefinition::class, CollisionStateDefinition::class]);
        $container->register('state_machine.collision', \stdClass::class);

        try {
            $container->compile();
            $this->fail('A service ID collision should prevent the workflows from being registered.');
        } catch (LogicException $e) {
            $this->assertSame('Cannot register workflow "collision" declared by enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\CollisionStateDefinition": service ID "state_machine.collision" already exists.', $e->getMessage());
        }

        $this->assertFalse($container->hasDefinition('state_machine.post'));
    }

    #[DataProvider('provideGeneratedServiceIds')]
    public function testRejectsEveryExistingGeneratedServiceIdBeforeRegisteringAnyWorkflow(string $serviceId)
    {
        $container = $this->createContainer([PostStateDefinition::class, CollisionStateDefinition::class]);
        $container->register($serviceId, \stdClass::class);

        try {
            $container->compile();
            $this->fail('A generated service ID collision should prevent the workflows from being registered.');
        } catch (LogicException $e) {
            $this->assertSame(\sprintf('Cannot register workflow "collision" declared by enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\CollisionStateDefinition": service ID "%s" already exists.', $serviceId), $e->getMessage());
        }

        $this->assertFalse($container->hasDefinition('state_machine.post'));
    }

    public static function provideGeneratedServiceIds(): iterable
    {
        yield 'workflow' => ['state_machine.collision'];
        yield 'metadata store' => ['state_machine.collision.metadata_store'];
        yield 'definition' => ['state_machine.collision.definition'];
        yield 'transition' => ['.state_machine.collision.transition.0'];
        yield 'audit trail listener' => ['.state_machine.collision.listener.audit_trail'];
        yield 'guard listener' => ['.state_machine.collision.listener.guard'];
        yield 'named autowiring alias' => [WorkflowInterface::class.' $collisionStateMachine'];
        yield 'target alias' => ['.'.WorkflowInterface::class.' $collision'];
    }

    #[DataProvider('provideExpandedTransitionServiceIds')]
    public function testRejectsEveryExpandedTransitionServiceId(string $definition, string $workflow, string $serviceId)
    {
        $container = $this->createContainer([$definition]);
        $container->register($serviceId, \stdClass::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('Cannot register workflow "%s" declared by enum "%s": service ID "%s" already exists.', $workflow, $definition, $serviceId));

        $container->compile();
    }

    public static function provideExpandedTransitionServiceIds(): iterable
    {
        yield 'state machine repeated attribute' => [PostStateDefinition::class, 'post', '.state_machine.post.transition.1'];
        yield 'workflow transition list' => [ArticleWorkflowDefinition::class, 'article', '.workflow.article.transition.2'];
    }

    public function testRejectsAnExistingWorkflowServiceAlias()
    {
        $container = $this->createContainer([PostStateDefinition::class]);
        $container->register('existing_service', \stdClass::class);
        $container->setAlias('state_machine.post', 'existing_service');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot register workflow "post" declared by enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\PostStateDefinition": service ID "state_machine.post" already exists.');

        $container->compile();
    }

    public function testRejectsConflictingGeneratedServiceIdsBeforeRegisteringAnyWorkflow()
    {
        $container = $this->createContainer([CollisionStateDefinition::class, DerivedCollisionStateDefinition::class]);

        try {
            $container->compile();
            $this->fail('A collision between generated service IDs should prevent the workflows from being registered.');
        } catch (LogicException $e) {
            $this->assertSame('Cannot register workflow "collision.definition" declared by enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\DerivedCollisionStateDefinition": generated service ID "state_machine.collision.definition" conflicts with workflow "collision" declared by enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\CollisionStateDefinition".', $e->getMessage());
        }

        $this->assertFalse($container->hasDefinition('state_machine.collision'));
        $this->assertFalse($container->hasDefinition('state_machine.collision.definition'));
    }

    public function testRejectsConflictingGeneratedAliasIdsBeforeRegisteringAnyWorkflow()
    {
        $container = $this->createContainer([UnderscoreAliasStateDefinition::class, DottedAliasStateDefinition::class]);

        try {
            $container->compile();
            $this->fail('A collision between generated alias IDs should prevent the workflows from being registered.');
        } catch (LogicException $e) {
            $this->assertSame('Cannot register workflow "alias.collision" declared by enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\DottedAliasStateDefinition": generated service ID "Symfony\\Component\\Workflow\\WorkflowInterface $aliasCollisionStateMachine" conflicts with workflow "alias_collision" declared by enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\UnderscoreAliasStateDefinition".', $e->getMessage());
        }

        $this->assertFalse($container->hasDefinition('state_machine.alias_collision'));
        $this->assertFalse($container->hasDefinition('state_machine.alias.collision'));
    }

    public function testRejectsAnInvalidAutowiringAliasNameBeforeRegisteringAnyWorkflow()
    {
        $container = $this->createContainer([PostStateDefinition::class, InvalidAliasNameStateDefinition::class]);

        try {
            $container->compile();
            $this->fail('An invalid autowiring alias name should prevent the workflows from being registered.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Invalid argument name "123.foo.state_machine" for service "state_machine.123.foo": the first character must be a letter.', $e->getMessage());
        }

        $this->assertFalse($container->hasDefinition('state_machine.post'));
        $this->assertFalse($container->hasDefinition('state_machine.123.foo'));
    }

    public function testRejectsAttributeDefinitionsWhenTheBundleIsDisabled()
    {
        $container = $this->createContainer([PostStateDefinition::class], ['enabled' => false]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The Workflow component is disabled, but enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\PostStateDefinition" declares workflow "post".');

        $container->compile();
    }

    public function testRejectsForeignEnumCaseWrappedInWeightedPlace()
    {
        $container = $this->createContainer([ForeignArcDefinition::class]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('which is not a case of');

        $container->compile();
    }

    public function testDefinitionTagIsAResourceTag()
    {
        $container = $this->createContainer([PostStateDefinition::class], excludeEnums: false);
        $container->compile();

        $this->assertTrue($container->getDefinition(PostStateDefinition::class)->hasTag('container.excluded'));
        $this->assertContains((string) new FileResource(__FILE__), array_map('strval', $container->getResources()));
    }

    public function testRejectsAnUnloadableDefinitionClass()
    {
        $container = $this->createContainer([]);
        $container->register('missing_workflow_definition', 'Missing\\WorkflowDefinition')
            ->addResourceTag(WorkflowDefinitionPass::TAG);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Class "Missing\\WorkflowDefinition" declared as a workflow definition could not be loaded.');

        $container->compile();
    }

    public function testAttributeAndConfiguredWorkflowsCanCoexist()
    {
        $container = $this->createContainer([PostStateDefinition::class], [
            'configured' => [
                'supports' => [\stdClass::class],
                'places' => ['a', 'b'],
                'transitions' => ['go' => ['from' => 'a', 'to' => 'b']],
            ],
        ]);
        $container->compile();

        $this->assertTrue($container->hasDefinition('state_machine.post'));
        $this->assertTrue($container->hasDefinition('state_machine.configured'));
    }

    #[DataProvider('provideInvalidDefinitions')]
    public function testRejectsInvalidDefinitions(string $definition, string $message)
    {
        $container = $this->createContainer([$definition]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $container->compile();
    }

    public static function provideInvalidDefinitions(): iterable
    {
        yield 'integer-backed enum' => [IntegerStateDefinition::class, 'Workflow definition enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\IntegerStateDefinition" must be string-backed.'];
        yield 'case from' => [CaseWithExplicitFromDefinition::class, 'must not declare "from"; the case is the inferred source place'];
        yield 'class without from' => [ClassWithoutFromDefinition::class, 'must declare at least one "from" place'];
        yield 'foreign case' => [ForeignCaseDefinition::class, 'which is not a case of'];
        yield 'supports and strategy' => [ConflictingSupportDefinition::class, '"supports" and "supportStrategy" cannot be used together'];
        yield 'marking store property and service' => [ConflictingMarkingStoreDefinition::class, '"markingStoreProperty" and "markingStoreService" cannot be used together'];
        yield 'not an enum' => [NotAnEnumWorkflowDefinition::class, '"Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\NotAnEnumWorkflowDefinition" is not an enum.'];
        yield 'missing support' => [MissingSupportDefinition::class, '"supports" or "supportStrategy" must be configured'];
        yield 'empty workflow name' => [EmptyNameDefinition::class, 'Workflow name cannot be empty'];
        yield 'empty output' => [EmptyOutputDefinition::class, 'must declare at least one "to" place'];
        yield 'foreign initial marking' => [ForeignInitialMarkingDefinition::class, 'Initial marking references'];
        yield 'attribute on ordinary enum constant' => [AttributeOnConstantDefinition::class, 'must be an enum case'];
        yield 'unknown event' => [UnknownEventDefinition::class, 'Unknown workflow event'];
        yield 'mixed events' => [MixedEventsDefinition::class, 'Cannot mix allow-list and block-list events'];
        yield 'disabled guard event' => [DisabledGuardEventDefinition::class, 'cannot be disabled'];
        yield 'invalid validator' => [InvalidValidatorDefinition::class, 'must implement'];
        yield 'empty place' => [EmptyPlaceDefinition::class, 'Place name cannot be empty'];
        yield 'empty transition name' => [EmptyTransitionNameDefinition::class, 'Transition name cannot be empty'];
        yield 'non-string transition name' => [NonStringTransitionNameDefinition::class, 'Transition name on workflow definition enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\NonStringTransitionNameDefinition" must be a string; "int" given.'];
        yield 'empty guard' => [EmptyGuardDefinition::class, 'Guard expression for transition "stay" cannot be empty'];
        yield 'nonexistent support' => [NonexistentSupportDefinition::class, 'The supported class or interface "Missing\\Subject"'];
        yield 'empty support strategy' => [EmptySupportStrategyDefinition::class, '"supportStrategy" cannot be empty'];
        yield 'empty marking store property' => [EmptyMarkingStorePropertyDefinition::class, 'Marking store options cannot be empty'];
        yield 'empty marking store service' => [EmptyMarkingStoreServiceDefinition::class, 'Marking store options cannot be empty'];
        yield 'empty enum' => [EmptyEnumDefinition::class, 'must declare at least one case'];
        yield 'no transitions' => [NoTransitionDefinition::class, 'must declare at least one transition'];
        yield 'duplicate normalized place' => [DuplicatePlaceDefinition::class, 'declares duplicate normalized place "same"'];
        yield 'invalid arc member' => [InvalidArcDefinition::class, 'has an invalid "to" arc of type "string"'];
        yield 'invalid initial marking member' => [InvalidInitialMarkingDefinition::class, 'Initial marking references "string"'];
        yield 'non-string event' => [NonStringEventDefinition::class, 'Events dispatched by workflow definition enum'];
        yield 'missing validator' => [MissingValidatorDefinition::class, 'The validation class "Missing\\DefinitionValidator"'];
        yield 'validator with required constructor argument' => [RequiredConstructorValidatorDefinition::class, 'must have a constructor without required arguments'];
        yield 'place attribute on ordinary enum constant' => [PlaceAttributeOnConstantDefinition::class, 'must be an enum case'];
        yield 'multiple state machine sources' => [MultipleSourceStateMachineDefinition::class, 'State machine transition "go" on enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\MultipleSourceStateMachineDefinition" cannot have more than one "from" place; use repeated #[Transition] attributes to define separate transitions.'];
        yield 'multiple state machine destinations' => [MultipleDestinationStateMachineDefinition::class, 'State machine transition "go" on enum "Symfony\\Component\\Workflow\\Tests\\DependencyInjection\\MultipleDestinationStateMachineDefinition" cannot have more than one "to" place; use repeated #[Transition] attributes to define separate transitions.'];
    }

    /**
     * @param list<class-string>   $definitions
     * @param array<string, mixed> $workflowConfig
     */
    private function createContainer(array $definitions, array $workflowConfig = [], bool $debug = false, bool $preserveDefinitions = true, bool $excludeEnums = true): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', $debug);

        $bundle = new WorkflowBundle();
        $container->registerExtension($bundle->getContainerExtension());
        $bundle->build($container);

        if ($excludeEnums) {
            $container->registerForAutoconfiguration(\UnitEnum::class)
                ->addTag('container.excluded', ['source' => 'because it\'s an enum']);
        }
        foreach (['security.token_storage', 'security.authorization_checker', 'security.authentication.trust_resolver', 'security.role_hierarchy'] as $service) {
            $container->register($service, \stdClass::class);
        }

        foreach ($definitions as $definition) {
            $container->register($definition, $definition)->setAutoconfigured(true);
        }

        $container->loadFromExtension('workflow', $workflowConfig);
        if ($preserveDefinitions) {
            $container->getCompilerPassConfig()->setOptimizationPasses([]);
            $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
            $container->getCompilerPassConfig()->setRemovingPasses([]);
            $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        }

        return $container;
    }

    /**
     * @param list<array{string, int}> $from
     * @param list<array{string, int}> $to
     */
    private function assertTransition(ContainerBuilder $container, string $id, string $name, array $from, array $to, Reference $reference): void
    {
        $this->assertSame($id, (string) $reference);
        $arguments = $container->getDefinition($id)->getArguments();
        $this->assertSame($name, $arguments[0]);
        $this->assertSame($from, array_map(static fn (Definition $arc): array => array_values($arc->getArguments()), $arguments[1]));
        $this->assertSame($to, array_map(static fn (Definition $arc): array => array_values($arc->getArguments()), $arguments[2]));
    }
}

#[AsWorkflowDefinition(
    name: 'article',
    type: WorkflowType::Workflow,
    supports: [\stdClass::class],
    initialMarking: [self::Draft, self::Permit],
    markingStoreProperty: 'status',
    auditTrail: true,
    eventsToDispatch: [],
    metadata: ['title' => 'Article publishing'],
    definitionValidators: [DefinitionValidator::class],
)]
#[Transition(name: 'assemble', from: [new WeightedPlace(self::Draft, 2), self::Permit], to: [new WeightedPlace(self::Ready, 3), self::Failed], guard: 'true', metadata: ['label' => 'Assemble'])]
enum ArticleWorkflowDefinition: string
{
    #[Place(metadata: ['label' => 'Draft'])]
    case Draft = 'draft';
    case Permit = 'permit';
    case Ready = 'ready';

    #[Transition(name: 'retry', to: self::Ready, guard: 'true', metadata: ['reason' => 'failed'])]
    case Failed = 'failed';

    #[Transition(name: 'retry', to: self::Ready, guard: 'false', metadata: ['reason' => 'timeout'])]
    case TimedOut = 'timed_out';
}

#[AsWorkflowDefinition(name: 'post', supports: [\stdClass::class], initialMarking: self::Draft)]
#[Transition(name: 'publish', from: self::Draft, to: self::Published)]
#[Transition(name: 'publish', from: self::Reviewed, to: self::Published)]
enum PostStateDefinition: string
{
    #[Transition(name: 'review', to: self::Reviewed)]
    case Draft = 'Draft';
    case Reviewed = 'Reviewed';
    case Published = 'Published';
}

#[AsWorkflowDefinition(name: 'post', supports: [\stdClass::class])]
enum DuplicatePostStateDefinition: string
{
    #[Transition(name: 'stay', to: self::Draft)]
    case Draft = 'Draft';
}

#[AsWorkflowDefinition(name: 'collision', supports: [\stdClass::class], auditTrail: true)]
enum CollisionStateDefinition: string
{
    #[Transition(name: 'stay', to: self::Draft, guard: 'true')]
    case Draft = 'Draft';
}

#[AsWorkflowDefinition(name: 'collision.definition', supports: [\stdClass::class])]
enum DerivedCollisionStateDefinition: string
{
    #[Transition(name: 'stay', to: self::Draft)]
    case Draft = 'Draft';
}

#[AsWorkflowDefinition(name: 'alias_collision', supports: [\stdClass::class])]
enum UnderscoreAliasStateDefinition: string
{
    #[Transition(name: 'stay', to: self::Draft)]
    case Draft = 'Draft';
}

#[AsWorkflowDefinition(name: 'alias.collision', supports: [\stdClass::class])]
enum DottedAliasStateDefinition: string
{
    #[Transition(name: 'stay', to: self::Draft)]
    case Draft = 'Draft';
}

#[AsWorkflowDefinition(name: '123.foo', supports: [\stdClass::class])]
enum InvalidAliasNameStateDefinition: string
{
    #[Transition(name: 'stay', to: self::Draft)]
    case Draft = 'Draft';
}

#[AsWorkflowDefinition(name: 'integer', supports: [\stdClass::class])]
enum IntegerStateDefinition: int
{
    case Draft = 1;
}

#[AsWorkflowDefinition(name: 'unit', supports: [\stdClass::class])]
enum UnitStateDefinition
{
    case Draft;
}

#[AsWorkflowDefinition(name: 'case_from', supports: [\stdClass::class])]
enum CaseWithExplicitFromDefinition: string
{
    #[Transition(name: 'go', from: self::A, to: self::B)]
    case A = 'A';
    case B = 'B';
}

#[AsWorkflowDefinition(name: 'class_without_from', supports: [\stdClass::class])]
#[Transition(name: 'go', to: self::B)]
enum ClassWithoutFromDefinition: string
{
    case A = 'A';
    case B = 'B';
}

enum OtherState: string
{
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'foreign', supports: [\stdClass::class])]
#[Transition(name: 'go', from: self::A, to: OtherState::A)]
enum ForeignCaseDefinition: string
{
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'foreign_arc', supports: [\stdClass::class])]
#[Transition(name: 'go', from: new WeightedPlace(OtherState::A, 2), to: self::B)]
enum ForeignArcDefinition: string
{
    case A = 'A';
    case B = 'B';
}

#[AsWorkflowDefinition(name: 'support', supports: [\stdClass::class], supportStrategy: 'support_strategy')]
enum ConflictingSupportDefinition: string
{
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'store', supports: [\stdClass::class], markingStoreProperty: 'state', markingStoreService: 'marking_store')]
enum ConflictingMarkingStoreDefinition: string
{
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'custom', supportStrategy: 'custom.support_strategy', markingStoreService: 'custom.marking_store')]
enum CustomServicesDefinition: string
{
    #[Transition(name: 'go', to: self::B)]
    case A = 'A';
    case B = 'B';
}

#[AsWorkflowDefinition(name: 'weighted', supports: [\stdClass::class])]
#[Transition(name: 'go', from: new WeightedPlace(self::A, 2), to: self::B)]
enum WeightedStateDefinition: string
{
    case A = 'A';
    case B = 'B';
}

#[AsWorkflowDefinition(name: 'duplicate_outgoing', supports: [\stdClass::class])]
enum DuplicateOutgoingTransitionDefinition: string
{
    #[Transition(name: 'go', to: self::B)]
    #[Transition(name: 'go', to: self::C)]
    case A = 'A';
    case B = 'B';
    case C = 'C';
}

#[AsWorkflowDefinition(name: 'not_an_enum', supports: [\stdClass::class])]
class NotAnEnumWorkflowDefinition
{
}

#[AsWorkflowDefinition(name: 'missing_support')]
enum MissingSupportDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: '', supports: [\stdClass::class])]
enum EmptyNameDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'empty_output', supports: [\stdClass::class])]
enum EmptyOutputDefinition: string
{
    #[Transition(name: 'go', to: [])]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'foreign_initial', supports: [\stdClass::class], initialMarking: OtherState::A)]
enum ForeignInitialMarkingDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'constant_attribute', supports: [\stdClass::class])]
enum AttributeOnConstantDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';

    #[Transition(name: 'invalid', to: self::A)]
    public const INVALID = 'invalid';
}

#[AsWorkflowDefinition(name: 'unknown_event', supports: [\stdClass::class], eventsToDispatch: ['workflow.unknown'])]
enum UnknownEventDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'invalid_validator', supports: [\stdClass::class], definitionValidators: [\DateTime::class])]
enum InvalidValidatorDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'mixed_events', supports: [\stdClass::class], eventsToDispatch: [WorkflowEvents::ENTER, '!'.WorkflowEvents::ANNOUNCE])]
enum MixedEventsDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'disabled_guard', supports: [\stdClass::class], eventsToDispatch: ['!'.WorkflowEvents::GUARD])]
enum DisabledGuardEventDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'empty_place', supports: [\stdClass::class])]
enum EmptyPlaceDefinition: string
{
    #[Transition(name: 'go', to: self::B)]
    case A = '';
    case B = 'b';
}

#[AsWorkflowDefinition(name: 'empty_transition_name', supports: [\stdClass::class])]
enum EmptyTransitionNameDefinition: string
{
    #[Transition(name: '', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'non_string_transition_name', supports: [\stdClass::class])]
enum NonStringTransitionNameDefinition: string
{
    public const NAME = 1;

    #[Transition(name: self::NAME, to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'empty_guard', supports: [\stdClass::class])]
enum EmptyGuardDefinition: string
{
    #[Transition(name: 'stay', to: self::A, guard: '')]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'nonexistent_support', supports: ['Missing\\Subject'])]
enum NonexistentSupportDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'empty_support_strategy', supportStrategy: '')]
enum EmptySupportStrategyDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'empty_store_property', supports: [\stdClass::class], markingStoreProperty: '')]
enum EmptyMarkingStorePropertyDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'empty_store_service', supports: [\stdClass::class], markingStoreService: '')]
enum EmptyMarkingStoreServiceDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'empty_enum', supports: [\stdClass::class])]
enum EmptyEnumDefinition: string
{
}

#[AsWorkflowDefinition(name: 'no_transition', supports: [\stdClass::class])]
enum NoTransitionDefinition: string
{
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'duplicate_place', supports: [\stdClass::class])]
enum DuplicatePlaceDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'same';
    case B = 'same';
}

#[AsWorkflowDefinition(name: 'invalid_arc', supports: [\stdClass::class])]
enum InvalidArcDefinition: string
{
    #[Transition(name: 'stay', to: [self::A, 'invalid'])]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'invalid_initial', supports: [\stdClass::class], initialMarking: [self::A, 'invalid'])]
enum InvalidInitialMarkingDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'non_string_event', supports: [\stdClass::class], eventsToDispatch: [1])]
enum NonStringEventDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'missing_validator', supports: [\stdClass::class], definitionValidators: ['Missing\\DefinitionValidator'])]
enum MissingValidatorDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

class RequiredConstructorValidator implements DefinitionValidatorInterface
{
    public function __construct(string $required)
    {
    }

    public function validate(WorkflowDefinition $definition, string $name): void
    {
    }
}

#[AsWorkflowDefinition(name: 'required_validator_argument', supports: [\stdClass::class], definitionValidators: [RequiredConstructorValidator::class])]
enum RequiredConstructorValidatorDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'place_constant_attribute', supports: [\stdClass::class])]
enum PlaceAttributeOnConstantDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';

    #[Place]
    public const INVALID = 'invalid';
}

#[AsWorkflowDefinition(name: 'multiple_source', supports: [\stdClass::class])]
#[Transition(name: 'go', from: [self::A, self::B], to: self::C)]
enum MultipleSourceStateMachineDefinition: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
}

#[AsWorkflowDefinition(name: 'multiple_destination', supports: [\stdClass::class])]
enum MultipleDestinationStateMachineDefinition: string
{
    #[Transition(name: 'go', to: [self::B, self::C])]
    case A = 'A';
    case B = 'B';
    case C = 'C';
}

interface SupportedSubjectInterface
{
}

class SupportedSubject implements SupportedSubjectInterface
{
}

#[AsWorkflowDefinition(name: 'multiple_supports', supports: [SupportedSubject::class, SupportedSubjectInterface::class])]
enum MultipleSupportsDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'state_machine_store', supports: [\stdClass::class], markingStoreProperty: 'state')]
enum StateMachineMarkingStoreDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'events', supports: [\stdClass::class], eventsToDispatch: [WorkflowEvents::ENTER, WorkflowEvents::LEAVE])]
enum AllowedEventsDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'events', supports: [\stdClass::class], eventsToDispatch: ['!'.WorkflowEvents::ANNOUNCE, '!'.WorkflowEvents::COMPLETED])]
enum BlockedEventsDefinition: string
{
    #[Transition(name: 'stay', to: self::A)]
    case A = 'A';
}

#[AsWorkflowDefinition(name: 'transition_name', supports: [\stdClass::class])]
enum TransitionNameConstantDefinition: string
{
    public const GO = 'go';

    #[Transition(self::GO, self::B)]
    case A = 'A';
    case B = 'B';
}
