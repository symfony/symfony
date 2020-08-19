<?php

namespace Symfony\Component\Workflow\Tests\SupportStrategy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\Workflow;

class InstanceOfSupportStrategyTest extends TestCase
{
    public function testSupportsIfClassInstance()
    {
        $strategy = new InstanceOfSupportStrategy(Subject1::class);

        $this->assertTrue($strategy->supports($this->createWorkflow(), new Subject1()));

        $stdClass = new \StdClass();
        $stdClass->class = Subject1::class;
        $this->assertTrue($strategy->supports($this->createWorkflow(), $stdClass));
    }

    public function testSupportsIfNotClassInstance()
    {
        $strategy = new InstanceOfSupportStrategy(Subject2::class);

        $this->assertFalse($strategy->supports($this->createWorkflow(), new Subject1()));

        $stdClass = new \StdClass();
        $stdClass->class = Subject1::class;
        $this->assertFalse($strategy->supports($this->createWorkflow(), $stdClass));
    }

    private function createWorkflow()
    {
        return $this->getMockBuilder(Workflow::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}

class Subject1
{
}
class Subject2
{
}
