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
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\SupportStrategy\ClassInstanceSupportStrategy;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

class WorkflowExtensionTest extends TestCase
{
    private $extension;
    private $t1;

    protected function setUp()
    {
        if (!class_exists(Workflow::class)) {
            $this->markTestSkipped('The Workflow component is needed to run tests for this extension.');
        }

        $places = array('ordered', 'waiting_for_payment', 'processed');
        $transitions = array(
            $this->t1 = new Transition('t1', 'ordered', 'waiting_for_payment'),
            new Transition('t2', 'waiting_for_payment', 'processed'),
        );

        $metadataStore = null;
        if (class_exists(InMemoryMetadataStore::class)) {
            $transitionsMetadata = new \SplObjectStorage();
            $transitionsMetadata->attach($this->t1, array('title' => 't1 title'));
            $metadataStore = new InMemoryMetadataStore(
                array('title' => 'workflow title'),
                array('orderer' => array('title' => 'ordered title')),
                $transitionsMetadata
            );
        }
        $definition = new Definition($places, $transitions, null, $metadataStore);
        $workflow = new Workflow($definition);

        $registry = new Registry();
        $addWorkflow = method_exists($registry, 'addWorkflow') ? 'addWorkflow' : 'add';
        $supportStrategy = class_exists(InstanceOfSupportStrategy::class)
            ? new InstanceOfSupportStrategy(\stdClass::class)
            : new ClassInstanceSupportStrategy(\stdClass::class);
        $registry->$addWorkflow($workflow, $supportStrategy);
        $this->extension = new WorkflowExtension($registry);
    }

    public function testCanTransition()
    {
        $subject = new \stdClass();
        $subject->marking = array();

        $this->assertTrue($this->extension->canTransition($subject, 't1'));
        $this->assertFalse($this->extension->canTransition($subject, 't2'));
    }

    public function testGetEnabledTransitions()
    {
        $subject = new \stdClass();
        $subject->marking = array();

        $transitions = $this->extension->getEnabledTransitions($subject);

        $this->assertCount(1, $transitions);
        $this->assertInstanceOf(Transition::class, $transitions[0]);
        $this->assertSame('t1', $transitions[0]->getName());
    }

    public function testHasMarkedPlace()
    {
        $subject = new \stdClass();
        $subject->marking = array();
        $subject->marking = array('ordered' => 1, 'waiting_for_payment' => 1);

        $this->assertTrue($this->extension->hasMarkedPlace($subject, 'ordered'));
        $this->assertTrue($this->extension->hasMarkedPlace($subject, 'waiting_for_payment'));
        $this->assertFalse($this->extension->hasMarkedPlace($subject, 'processed'));
    }

    public function testGetMarkedPlaces()
    {
        $subject = new \stdClass();
        $subject->marking = array();
        $subject->marking = array('ordered' => 1, 'waiting_for_payment' => 1);

        $this->assertSame(array('ordered', 'waiting_for_payment'), $this->extension->getMarkedPlaces($subject));
        $this->assertSame($subject->marking, $this->extension->getMarkedPlaces($subject, false));
    }

    public function testGetMetadata()
    {
        if (!class_exists(InMemoryMetadataStore::class)) {
            $this->markTestSkipped('This test requires symfony/workflow:4.1.');
        }
        $subject = new \stdClass();
        $subject->marking = array();

        $this->assertSame('workflow title', $this->extension->getMetadata($subject, 'title'));
        $this->assertSame('ordered title', $this->extension->getMetadata($subject, 'title', 'orderer'));
        $this->assertSame('t1 title', $this->extension->getMetadata($subject, 'title', $this->t1));
        $this->assertNull($this->extension->getMetadata($subject, 'not found'));
        $this->assertNull($this->extension->getMetadata($subject, 'not found', $this->t1));
    }
}
