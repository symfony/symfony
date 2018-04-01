<?php

namespace Symphony\Component\Workflow\Tests\SupportStrategy;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Workflow\SupportStrategy\ClassInstanceSupportStrategy;
use Symphony\Component\Workflow\Workflow;

/**
 * @group legacy
 */
class ClassInstanceSupportStrategyTest extends TestCase
{
    /**
     * @expectedDeprecation "Symphony\Component\Workflow\SupportStrategy\ClassInstanceSupportStrategy" is deprecated since Symphony 4.1. Use "Symphony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy" instead.
     */
    public function testSupportsIfClassInstance()
    {
        $strategy = new ClassInstanceSupportStrategy('Symphony\Component\Workflow\Tests\SupportStrategy\Subject1');

        $this->assertTrue($strategy->supports($this->createWorkflow(), new Subject1()));
    }

    public function testSupportsIfNotClassInstance()
    {
        $strategy = new ClassInstanceSupportStrategy('Symphony\Component\Workflow\Tests\SupportStrategy\Subject2');

        $this->assertFalse($strategy->supports($this->createWorkflow(), new Subject1()));
    }

    private function createWorkflow()
    {
        return $this->getMockBuilder(Workflow::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
