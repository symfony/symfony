<?php

namespace Symfony\Component\Workflow\Tests\SupportStrategy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\SupportStrategy\ClassInstanceSupportStrategy;
use Symfony\Component\Workflow\Workflow;

class ClassInstanceSupportStrategyTest extends TestCase
{
    public function testSupportsIfClassInstance()
    {
        $strategy = new ClassInstanceSupportStrategy('Symfony\Component\Workflow\Tests\SupportStrategy\Subject1');

        $this->assertTrue($strategy->supports($this->createWorkflow(), new Subject1()));
    }

    public function testSupportsIfNotClassInstance()
    {
        $strategy = new ClassInstanceSupportStrategy('Symfony\Component\Workflow\Tests\SupportStrategy\Subject2');

        $this->assertFalse($strategy->supports($this->createWorkflow(), new Subject1()));
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
