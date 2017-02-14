<?php

namespace Symfony\Component\Workflow\Tests\SupportStrategy;

use Symfony\Component\Workflow\SupportStrategy\ClassInstanceSupportStrategy;
use Symfony\Component\Workflow\Workflow;

class ClassInstanceSupportStrategyTest extends \PHPUnit_Framework_TestCase
{
    public function testSupportsIfClassInstance()
    {
        $strategy = new ClassInstanceSupportStrategy('Symfony\Component\Workflow\Tests\SupportStrategy\Subject1');

        $this->assertTrue($strategy->supports($this->getWorkflow(), new Subject1()));
    }

    public function testSupportsIfNotClassInstance()
    {
        $strategy = new ClassInstanceSupportStrategy('Symfony\Component\Workflow\Tests\SupportStrategy\Subject2');

        $this->assertFalse($strategy->supports($this->getWorkflow(), new Subject1()));
    }

    private function getWorkflow()
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
