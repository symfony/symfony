<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\WorkflowExtension;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\SupportStrategy\ClassInstanceSupportStrategy;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\TransitionBlockerList;
use Symfony\Component\Workflow\Workflow;

class WorkflowExtensionTest extends TestCase
{
    private $extension;
    private $t1;

    protected function setUp(): void
    {
        if (!class_exists(Workflow::class)) {
            self::markTestSkipped('The Workflow component is needed to run tests for this extension.');
        }

        $places = ['ordered', 'waiting_for_payment', 'processed'];
        $transitions = [
            $this->t1 = new Transition('t1', 'ordered', 'waiting_for_payment'),
            new Transition('t2', 'waiting_for_payment', 'processed'),
        ];

        $metadataStore = null;
        if (class_exists(InMemoryMetadataStore::class)) {
            $transitionsMetadata = new \SplObjectStorage();
            $transitionsMetadata->attach($this->t1, ['title' => 't1 title']);
            $metadataStore = new InMemoryMetadataStore(
                ['title' => 'workflow title'],
                ['orderer' => ['title' => 'ordered title']],
                $transitionsMetadata
            );
        }
        $definition = new Definition($places, $transitions, null, $metadataStore);
        $workflow = new Workflow($definition, new MethodMarkingStore());

        $registry = new Registry();
        $addWorkflow = method_exists($registry, 'addWorkflow') ? 'addWorkflow' : 'add';
        $supportStrategy = class_exists(InstanceOfSupportStrategy::class)
            ? new InstanceOfSupportStrategy(Subject::class)
            : new ClassInstanceSupportStrategy(Subject::class);
        $registry->$addWorkflow($workflow, $supportStrategy);
        $this->extension = new WorkflowExtension($registry);
    }

    public function testCanTransition()
    {
        $subject = new Subject();

        self::assertTrue($this->extension->canTransition($subject, 't1'));
        self::assertFalse($this->extension->canTransition($subject, 't2'));
    }

    public function testGetEnabledTransitions()
    {
        $subject = new Subject();

        $transitions = $this->extension->getEnabledTransitions($subject);

        self::assertCount(1, $transitions);
        self::assertInstanceOf(Transition::class, $transitions[0]);
        self::assertSame('t1', $transitions[0]->getName());
    }

    public function testGetEnabledTransition()
    {
        $subject = new Subject();

        $transition = $this->extension->getEnabledTransition($subject, 't1');

        self::assertInstanceOf(Transition::class, $transition);
        self::assertSame('t1', $transition->getName());
    }

    public function testHasMarkedPlace()
    {
        $subject = new Subject(['ordered' => 1, 'waiting_for_payment' => 1]);

        self::assertTrue($this->extension->hasMarkedPlace($subject, 'ordered'));
        self::assertTrue($this->extension->hasMarkedPlace($subject, 'waiting_for_payment'));
        self::assertFalse($this->extension->hasMarkedPlace($subject, 'processed'));
    }

    public function testGetMarkedPlaces()
    {
        $subject = new Subject(['ordered' => 1, 'waiting_for_payment' => 1]);

        self::assertSame(['ordered', 'waiting_for_payment'], $this->extension->getMarkedPlaces($subject));
        self::assertSame($subject->getMarking(), $this->extension->getMarkedPlaces($subject, false));
    }

    public function testGetMetadata()
    {
        if (!class_exists(InMemoryMetadataStore::class)) {
            self::markTestSkipped('This test requires symfony/workflow:4.1.');
        }
        $subject = new Subject();

        self::assertSame('workflow title', $this->extension->getMetadata($subject, 'title'));
        self::assertSame('ordered title', $this->extension->getMetadata($subject, 'title', 'orderer'));
        self::assertSame('t1 title', $this->extension->getMetadata($subject, 'title', $this->t1));
        self::assertNull($this->extension->getMetadata($subject, 'not found'));
        self::assertNull($this->extension->getMetadata($subject, 'not found', $this->t1));
    }

    public function testbuildTransitionBlockerList()
    {
        if (!class_exists(TransitionBlockerList::class)) {
            self::markTestSkipped('This test requires symfony/workflow:4.1.');
        }
        $subject = new Subject();

        $list = $this->extension->buildTransitionBlockerList($subject, 't1');
        self::assertInstanceOf(TransitionBlockerList::class, $list);
        self::assertTrue($list->isEmpty());
    }
}

final class Subject
{
    private $marking;

    public function __construct($marking = null)
    {
        $this->marking = $marking;
    }

    public function getMarking()
    {
        return $this->marking;
    }

    public function setMarking($marking)
    {
        $this->marking = $marking;
    }
}
