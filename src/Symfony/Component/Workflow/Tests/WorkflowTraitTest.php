<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\WorkflowTrait;

class WorkflowTraitTest extends TestCase
{
    public function testTheTraitExposesEveryMethodOfTheWorkflowClass()
    {
        $trait = new \ReflectionClass(WorkflowTrait::class);

        foreach ((new \ReflectionClass(Workflow::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor()) {
                continue;
            }

            $this->assertTrue($trait->hasMethod($method->name), \sprintf('"%s" must expose the "%s()" method of "%s".', WorkflowTrait::class, $method->name, Workflow::class));
            $this->assertSame(self::describe($method), self::describe($trait->getMethod($method->name)), \sprintf('The signature of "%s::%s()" must match the one of "%s".', WorkflowTrait::class, $method->name, Workflow::class));
        }
    }

    public function testTheTraitDelegatesToTheWorkflow()
    {
        $metadataStore = new InMemoryMetadataStore(['title' => 'My workflow']);
        $definition = new Definition(['a', 'b'], [new Transition('go', 'a', 'b')], null, $metadataStore);
        $markingStore = new MethodMarkingStore(true);
        $workflow = new Workflow($definition, $markingStore, null, 'my_workflow');

        $facade = new class {
            use WorkflowTrait;
        };
        $facade->setWorkflow($workflow);
        $subject = new Subject();

        $this->assertSame('my_workflow', $facade->getName());
        $this->assertSame($definition, $facade->getDefinition());
        $this->assertSame($markingStore, $facade->getMarkingStore());
        $this->assertSame($metadataStore, $facade->getMetadataStore());
        $this->assertSame(['a' => 1], $facade->getMarking($subject)->getPlaces());
        $this->assertTrue($facade->can($subject, 'go'));
        $this->assertTrue($facade->buildTransitionBlockerList($subject, 'go')->isEmpty());
        $this->assertSame('go', $facade->getEnabledTransition($subject, 'go')->getName());
        $this->assertCount(1, $facade->getEnabledTransitions($subject));
        $this->assertSame(['b' => 1], $facade->apply($subject, 'go', ['foo' => 'bar'])->getPlaces());
        $this->assertSame(['foo' => 'bar'], $subject->getContext());
        $this->assertFalse($facade->can($subject, 'go'));
    }

    public function testTheTraitForwardsTheContextOfGetMarking()
    {
        $dispatcher = new EventDispatcher();
        $context = null;
        $dispatcher->addListener('workflow.entered', static function (EnteredEvent $event) use (&$context) {
            $context = $event->getContext();
        });
        $facade = new class {
            use WorkflowTrait;
        };
        $facade->setWorkflow(new Workflow(new Definition(['a', 'b'], [new Transition('go', 'a', 'b')]), new MethodMarkingStore(true), $dispatcher));

        $facade->getMarking(new Subject(), ['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar'], $context);
    }

    public function testTheClassCanImplementTheWorkflowInterface()
    {
        $facade = new class implements WorkflowInterface {
            use WorkflowTrait;
        };
        $facade->setWorkflow(new Workflow(new Definition(['a'], []), null, null, 'my_workflow'));

        $this->assertSame('my_workflow', $facade->getName());
    }

    public function testTheTraitThrowsWhenTheWorkflowIsNotSet()
    {
        $facade = new class {
            use WorkflowTrait;
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The workflow of "class@anonymous');

        $facade->getName();
    }

    private static function describe(\ReflectionMethod $method): string
    {
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $parameters[] = \sprintf('%s $%s%s', $parameter->getType(), $parameter->name, $parameter->isDefaultValueAvailable() ? ' = '.var_export($parameter->getDefaultValue(), true) : '');
        }

        return \sprintf('%s(%s): %s', $method->name, implode(', ', $parameters), $method->getReturnType());
    }
}
