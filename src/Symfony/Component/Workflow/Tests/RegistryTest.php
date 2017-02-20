<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\SupportStrategy\SupportStrategyInterface;
use Symfony\Component\Workflow\Workflow;

class RegistryTest extends TestCase
{
    private $registry;

    protected function setUp()
    {
        $this->registry = new Registry();

        $this->registry->add(new Workflow(new Definition(array(), array()), $this->getMockBuilder(MarkingStoreInterface::class)->getMock(), $this->getMockBuilder(EventDispatcherInterface::class)->getMock(), 'workflow1'), $this->createSupportStrategy(Subject1::class));
        $this->registry->add(new Workflow(new Definition(array(), array()), $this->getMockBuilder(MarkingStoreInterface::class)->getMock(), $this->getMockBuilder(EventDispatcherInterface::class)->getMock(), 'workflow2'), $this->createSupportStrategy(Subject2::class));
        $this->registry->add(new Workflow(new Definition(array(), array()), $this->getMockBuilder(MarkingStoreInterface::class)->getMock(), $this->getMockBuilder(EventDispatcherInterface::class)->getMock(), 'workflow3'), $this->createSupportStrategy(Subject2::class));
    }

    protected function tearDown()
    {
        $this->registry = null;
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

    /**
     * @group legacy
     */
    public function testGetWithSuccessLegacyStrategy()
    {
        $registry = new Registry();

        $registry->add(new Workflow(new Definition(array(), array()), $this->getMockBuilder(MarkingStoreInterface::class)->getMock(), $this->getMockBuilder(EventDispatcherInterface::class)->getMock(), 'workflow1'), Subject1::class);
        $registry->add(new Workflow(new Definition(array(), array()), $this->getMockBuilder(MarkingStoreInterface::class)->getMock(), $this->getMockBuilder(EventDispatcherInterface::class)->getMock(), 'workflow2'), Subject2::class);

        $workflow = $registry->get(new Subject1());
        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertSame('workflow1', $workflow->getName());

        $workflow = $registry->get(new Subject1(), 'workflow1');
        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertSame('workflow1', $workflow->getName());

        $workflow = $registry->get(new Subject2(), 'workflow2');
        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertSame('workflow2', $workflow->getName());
    }

    private function createSupportStrategy($supportedClassName)
    {
        $strategy = $this->getMockBuilder(SupportStrategyInterface::class)->getMock();
        $strategy->expects($this->any())->method('supports')
            ->will($this->returnCallback(function ($workflow, $subject) use ($supportedClassName) {
                return $subject instanceof $supportedClassName;
            }));

        return $strategy;
    }
}

class Subject1
{
}
class Subject2
{
}
