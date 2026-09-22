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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow\Task;
use Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow\TaskWorkflow;
use Symfony\Component\Workflow\WorkflowBundle;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\WorkflowType;

class WorkflowAttributePassTest extends TestCase
{
    public function testTheWorkflowOfAClassIsRegistered()
    {
        $container = $this->createContainer();
        $container->register(TaskWorkflow::class, TaskWorkflow::class)->setAutoconfigured(true);
        $container->compile();

        $definition = $container->getDefinition(TaskWorkflow::class);
        $this->assertFalse($definition->hasTag('.workflow.attribute'));
        $this->assertEquals([['setWorkflow', [new Reference('state_machine.task')]]], $definition->getMethodCalls());
        $this->assertSame([
            ['event' => 'workflow.task.guard.start', 'method' => 'guardStart', 'priority' => null, 'dispatcher' => null, 'before' => null, 'after' => null],
            ['event' => 'workflow.task.transition.start', 'method' => 'onStart', 'priority' => null, 'dispatcher' => null, 'before' => null, 'after' => null],
        ], $definition->getTag('kernel.event_listener'));

        $workflow = $container->getDefinition('state_machine.task');
        $this->assertSame('state_machine.abstract', $workflow->getParent());
        $this->assertSame('task', $workflow->getArgument('index_3'));
        $this->assertNull($workflow->getArgument('index_4'));
        $this->assertSame([['name' => 'task']], $workflow->getTag('workflow.state_machine'));
        $this->assertSame('task', $workflow->getTag('workflow')[0]['name']);
        $this->assertSame(['title' => 'Task'], $workflow->getTag('workflow')[0]['metadata']);
        $markingStore = $workflow->getArgument('index_1');
        $this->assertInstanceOf(ChildDefinition::class, $markingStore);
        $this->assertSame('workflow.marking_store.method', $markingStore->getParent());
        $this->assertSame([true, 'step'], $markingStore->getArguments());

        $workflowDefinition = $container->getDefinition('state_machine.task.definition');
        $this->assertSame(['new', 'processing', 'done', 'failed'], $workflowDefinition->getArgument(0));
        $this->assertSame([], $workflowDefinition->getArgument(2));
        $transitions = $workflowDefinition->getArgument(1);
        $this->assertCount(4, $transitions);
        $this->assertTransition($container, '.state_machine.task.transition.0', 'start', [['new', 1]], [['processing', 1]], $transitions[0]);
        $this->assertTransition($container, '.state_machine.task.transition.1', 'finish', [['processing', 1]], [['done', 1]], $transitions[1]);
        $this->assertTransition($container, '.state_machine.task.transition.2', 'finish', [['failed', 1]], [['done', 1]], $transitions[2]);
        $this->assertTransition($container, '.state_machine.task.transition.3', 'fail', [['processing', 1]], [['failed', 1]], $transitions[3]);

        $metadataStore = $container->getDefinition('state_machine.task.metadata_store');
        $this->assertSame(['title' => 'Task'], $metadataStore->getArgument(0));
        $this->assertSame(['new' => ['label' => 'New'], 'processing' => ['label' => 'Processing', 'bg_color' => '#eee']], $metadataStore->getArgument(1));

        $this->assertSame('state_machine.task', (string) $container->getAlias(WorkflowInterface::class.' $taskStateMachine'));
        $this->assertSame(WorkflowInterface::class.' $taskStateMachine', (string) $container->getAlias('.'.WorkflowInterface::class.' $task'));

        $registryCalls = $container->getDefinition('workflow.registry')->getMethodCalls();
        $this->assertCount(1, $registryCalls);
        $this->assertSame('state_machine.task', (string) $registryCalls[0][1][0]);
        $this->assertSame(Task::class, $registryCalls[0][1][1]->getArgument(0));
    }

    public function testTheWorkflowIsNotInjectedWhenTheClassDoesNotUseTheTrait()
    {
        $container = $this->createContainer();
        $container->register(PassPlainWorkflow::class, PassPlainWorkflow::class)->setAutoconfigured(true);
        $container->compile();

        $this->assertSame([], $container->getDefinition(PassPlainWorkflow::class)->getMethodCalls());
        $this->assertTrue($container->hasDefinition('workflow.pass_plain'));
    }

    public function testTheTransitionsOfAStateMachineAreSplit()
    {
        $container = $this->createContainer();
        $container->register(PassSplitStateMachine::class, PassSplitStateMachine::class)->setAutoconfigured(true);
        $container->register(PassJoinWorkflow::class, PassJoinWorkflow::class)->setAutoconfigured(true);
        $container->compile();

        $transitions = $container->getDefinition('state_machine.pass_split.definition')->getArgument(1);
        $this->assertCount(2, $transitions);
        $this->assertTransition($container, '.state_machine.pass_split.transition.0', 'go', [['a', 1]], [['c', 1]], $transitions[0]);
        $this->assertTransition($container, '.state_machine.pass_split.transition.1', 'go', [['b', 1]], [['c', 1]], $transitions[1]);

        $transitions = $container->getDefinition('workflow.pass_join.definition')->getArgument(1);
        $this->assertCount(1, $transitions);
        $this->assertTransition($container, '.workflow.pass_join.transition.0', 'go', [['a', 1], ['b', 1]], [['c', 1]], $transitions[0]);
    }

    public function testAttributeAndConfiguredWorkflowsCoexist()
    {
        $container = $this->createContainer([
            'article' => [
                'supports' => [\stdClass::class],
                'places' => ['a', 'b'],
                'transitions' => ['go' => ['from' => 'a', 'to' => 'b']],
            ],
        ]);
        $container->register(TaskWorkflow::class, TaskWorkflow::class)->setAutoconfigured(true);
        $container->compile();

        $this->assertTrue($container->hasDefinition('state_machine.article'));
        $this->assertTrue($container->hasDefinition('state_machine.task'));
        $this->assertCount(2, $container->getDefinition('workflow.registry')->getMethodCalls());
    }

    public function testTheNameOfTheWorkflowMustBeUnique()
    {
        $container = $this->createContainer([
            'task' => [
                'supports' => [\stdClass::class],
                'places' => ['a', 'b'],
                'transitions' => ['go' => ['from' => 'a', 'to' => 'b']],
            ],
        ]);
        $container->register(TaskWorkflow::class, TaskWorkflow::class)->setAutoconfigured(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The workflow "task" defined by "Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow\TaskWorkflow" is already defined by the "state_machine.task" service.');

        $container->compile();
    }

    public function testTwoClassesCannotDefineTheSameWorkflow()
    {
        $container = $this->createContainer();
        $container->register(TaskWorkflow::class, TaskWorkflow::class)->setAutoconfigured(true);
        $container->register(PassOtherTaskWorkflow::class, PassOtherTaskWorkflow::class)->setAutoconfigured(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The workflow "task" defined by "Symfony\Component\Workflow\Tests\DependencyInjection\PassOtherTaskWorkflow" is already defined by the "Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow\TaskWorkflow" service.');

        $container->compile();
    }

    public function testWorkflowsMustBeEnabled()
    {
        $container = $this->createContainer(['enabled' => false]);
        $container->register(TaskWorkflow::class, TaskWorkflow::class)->setAutoconfigured(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow\TaskWorkflow" service uses the "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" attribute but workflows are disabled; enable them in the "workflow" configuration.');

        $container->compile();
    }

    public function testListenersMustDefineTheWorkflowOutsideOfAWorkflowClass()
    {
        $container = $this->createContainer();
        $container->register(PassOrphanListener::class, PassOrphanListener::class)->setAutoconfigured(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "guard()" listener of the "Symfony\Component\Workflow\Tests\DependencyInjection\PassOrphanListener" service listens to a transition or a place without defining the "workflow" argument of its attribute; set it, or move the listener to a class using the "#[Symfony\Component\Workflow\Attribute\AsWorkflow]" attribute.');

        $container->compile();
    }

    private function createContainer(array $config = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $bundle = new WorkflowBundle();
        $container->registerExtension($bundle->getContainerExtension());
        $bundle->build($container);
        $container->loadFromExtension('workflow', $config);

        // Mimics the autoconfiguration of the listeners done by the ServicesBundle
        $container->registerAttributeForAutoconfiguration(AsEventListener::class, static function (ChildDefinition $definition, AsEventListener $attribute, \ReflectionClass|\ReflectionMethod $reflector) {
            $tagAttributes = get_object_vars($attribute);
            if ($reflector instanceof \ReflectionMethod) {
                $tagAttributes['method'] = $reflector->getName();
            }
            $definition->addTag('kernel.event_listener', $tagAttributes);
        });

        // Keep the definitions as they are to inspect them
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);

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

#[AsWorkflow(type: WorkflowType::Workflow, supports: \stdClass::class)]
class PassPlainWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(supports: \stdClass::class)]
class PassSplitStateMachine
{
    #[Transition(from: ['a', 'b'], to: 'c')]
    public const GO = 'go';
}

#[AsWorkflow(type: WorkflowType::Workflow, supports: \stdClass::class)]
class PassJoinWorkflow
{
    #[Transition(from: ['a', 'b'], to: 'c')]
    public const GO = 'go';
}

#[AsWorkflow('task', supports: \stdClass::class)]
class PassOtherTaskWorkflow
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

class PassOrphanListener
{
    #[AsGuardListener(transition: 'go')]
    public function guard(GuardEvent $event): void
    {
    }
}
