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
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\SupportStrategy\ClassInstanceSupportStrategy;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

class WorkflowExtensionTest extends TestCase
{
    private $extension;

    protected function setUp()
    {
        if (!class_exists(Workflow::class)) {
            $this->markTestSkipped('The Workflow component is needed to run tests for this extension.');
        }

        $places = array('ordered', 'waiting_for_payment', 'processed');
        $transitions = array(
            new Transition('t1', 'ordered', 'waiting_for_payment'),
            new Transition('t2', 'waiting_for_payment', 'processed'),
        );
        $definition = new Definition($places, $transitions);
        $workflow = new Workflow($definition);

        $registry = new Registry();
        $registry->add($workflow, new ClassInstanceSupportStrategy(\stdClass::class));

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
}
