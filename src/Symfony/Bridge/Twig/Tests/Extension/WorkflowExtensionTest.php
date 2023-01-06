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

        $this->assertTrue($this->extension->canTransition($subject, 't1'));
        $this->assertFalse($this->extension->canTransition($subject, 't2'));
    }

    public function testGetEnabledTransitions()
    {
        $subject = new Subject();

        $transitions = $this->extension->getEnabledTransitions($subject);

        $this->assertCount(1, $transitions);
        $this->assertInstanceOf(Transition::class, $transitions[0]);
        $this->assertSame('t1', $transitions[0]->getName());
    }

    public function testGetEnabledTransition()
    {
        $subject = new Subject();

        $transition = $this->extension->getEnabledTransition($subject, 't1');

        $this->assertInstanceOf(Transition::class, $transition);
        $this->assertSame('t1', $transition->getName());
    }

    public function testHasMarkedPlace()
    {
        $subject = new Subject(['ordered' => 1, 'waiting_for_payment' => 1]);

        $this->assertTrue($this->extension->hasMarkedPlace($subject, 'ordered'));
        $this->assertTrue($this->extension->hasMarkedPlace($subject, 'waiting_for_payment'));
        $this->assertFalse($this->extension->hasMarkedPlace($subject, 'processed'));
    }

    public function testGetMarkedPlaces()
    {
        $subject = new Subject(['ordered' => 1, 'waiting_for_payment' => 1]);

        $this->assertSame(['ordered', 'waiting_for_payment'], $this->extension->getMarkedPlaces($subject));
        $this->assertSame($subject->getMarking(), $this->extension->getMarkedPlaces($subject, false));
    }

    public function testGetMetadata()
    {
        $subject = new Subject();

        $this->assertSame('workflow title', $this->extension->getMetadata($subject, 'title'));
        $this->assertSame('ordered title', $this->extension->getMetadata($subject, 'title', 'orderer'));
        $this->assertSame('t1 title', $this->extension->getMetadata($subject, 'title', $this->t1));
        $this->assertNull($this->extension->getMetadata($subject, 'not found'));
        $this->assertNull($this->extension->getMetadata($subject, 'not found', $this->t1));
    }

    public function testbuildTransitionBlockerList()
    {
        $subject = new Subject();

        $list = $this->extension->buildTransitionBlockerList($subject, 't1');
        $this->assertInstanceOf(TransitionBlockerList::class, $list);
        $this->assertTrue($list->isEmpty());
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
