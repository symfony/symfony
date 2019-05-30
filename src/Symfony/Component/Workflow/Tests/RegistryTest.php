<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\SupportStrategy\SupportStrategyInterface;
use Symfony\Component\Workflow\SupportStrategy\WorkflowSupportStrategyInterface;
use Symfony\Component\Workflow\Workflow;

class RegistryTest extends TestCase
{
    private $registry;

    protected function setUp()
    {
        $this->registry = new Registry();

        $this->registry->addWorkflow(new Workflow(new Definition([], []), $this->getMockBuilder(MarkingStoreInterface::class)->getMock(), $this->getMockBuilder(EventDispatcherInterface::class)->getMock(), 'workflow1'), $this->createWorkflowSupportStrategy(Subject1::class));
        $this->registry->addWorkflow(new Workflow(new Definition([], []), $this->getMockBuilder(MarkingStoreInterface::class)->getMock(), $this->getMockBuilder(EventDispatcherInterface::class)->getMock(), 'workflow2'), $this->createWorkflowSupportStrategy(Subject2::class));
        $this->registry->addWorkflow(new Workflow(new Definition([], []), $this->getMockBuilder(MarkingStoreInterface::class)->getMock(), $this->getMockBuilder(EventDispatcherInterface::class)->getMock(), 'workflow3'), $this->createWorkflowSupportStrategy(Subject2::class));
    }

    protected function tearDown()
    {
        $this->registry = null;
    }

    /**
     * @group legacy
     * @expectedDeprecation The "Symfony\Component\Workflow\Registry::add()" method is deprecated since Symfony 4.1. Use addWorkflow() instead.
     */
    public function testAddIsDeprecated()
    {
        $registry = new Registry();

        $registry->add($w = new Workflow(new Definition([], []), $this->getMockBuilder(MarkingStoreInterface::class)->getMock(), $this->getMockBuilder(EventDispatcherInterface::class)->getMock(), 'workflow1'), $this->createSupportStrategy(Subject1::class));

        $workflow = $registry->get(new Subject1());
        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertSame('workflow1', $workflow->getName());
    }

    public function testGetWithSuccess()
    {
        $workflow = $this->registry->get(new Subject1());
        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertSame('workflow1', $workflow->getName());

        $workflow = $this->registry->get(new Subject1(), 'workflow1');
        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertSame('workflow1', $workflow->getName());

        $workflow = $this->registry->get(new Subject2(), 'workflow2');
        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertSame('workflow2', $workflow->getName());
    }

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\InvalidArgumentException
     * @expectedExceptionMessage At least two workflows match this subject. Set a different name on each and use the second (name) argument of this method.
     */
    public function testGetWithMultipleMatch()
    {
        $w1 = $this->registry->get(new Subject2());
        $this->assertInstanceOf(Workflow::class, $w1);
        $this->assertSame('workflow1', $w1->getName());
    }

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\InvalidArgumentException
     * @expectedExceptionMessage Unable to find a workflow for class "stdClass".
     */
    public function testGetWithNoMatch()
    {
        $w1 = $this->registry->get(new \stdClass());
        $this->assertInstanceOf(Workflow::class, $w1);
        $this->assertSame('workflow1', $w1->getName());
    }

    public function testAllWithOneMatchWithSuccess()
    {
        $workflows = $this->registry->all(new Subject1());
        $this->assertInternalType('array', $workflows);
        $this->assertCount(1, $workflows);
        $this->assertInstanceOf(Workflow::class, $workflows[0]);
        $this->assertSame('workflow1', $workflows[0]->getName());
    }

    public function testAllWithMultipleMatchWithSuccess()
    {
        $workflows = $this->registry->all(new Subject2());
        $this->assertInternalType('array', $workflows);
        $this->assertCount(2, $workflows);
        $this->assertInstanceOf(Workflow::class, $workflows[0]);
        $this->assertInstanceOf(Workflow::class, $workflows[1]);
        $this->assertSame('workflow2', $workflows[0]->getName());
        $this->assertSame('workflow3', $workflows[1]->getName());
    }

    public function testAllWithNoMatch()
    {
        $workflows = $this->registry->all(new \stdClass());
        $this->assertInternalType('array', $workflows);
        $this->assertCount(0, $workflows);
    }

    /**
     * @group legacy
     */
    private function createSupportStrategy($supportedClassName)
    {
        $strategy = $this->getMockBuilder(SupportStrategyInterface::class)->getMock();
        $strategy->expects($this->any())->method('supports')
            ->willReturnCallback(function ($workflow, $subject) use ($supportedClassName) {
                return $subject instanceof $supportedClassName;
            });

        return $strategy;
    }

    /**
     * @group legacy
     */
    private function createWorkflowSupportStrategy($supportedClassName)
    {
        $strategy = $this->getMockBuilder(WorkflowSupportStrategyInterface::class)->getMock();
        $strategy->expects($this->any())->method('supports')
            ->willReturnCallback(function ($workflow, $subject) use ($supportedClassName) {
                return $subject instanceof $supportedClassName;
            });

        return $strategy;
    }
}

class Subject1
{
}
class Subject2
{
}
