<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Exception\InvalidArgumentException;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\SupportStrategy\WorkflowSupportStrategyInterface;
use Symfony\Component\Workflow\Workflow;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RegistryTest extends TestCase
{
    private $registry;

    protected function setUp(): void
    {
        $this->registry = new Registry();

        $this->registry->addWorkflow(new Workflow(new Definition([], []), self::createMock(MarkingStoreInterface::class), self::createMock(EventDispatcherInterface::class), 'workflow1'), $this->createWorkflowSupportStrategy(Subject1::class));
        $this->registry->addWorkflow(new Workflow(new Definition([], []), self::createMock(MarkingStoreInterface::class), self::createMock(EventDispatcherInterface::class), 'workflow2'), $this->createWorkflowSupportStrategy(Subject2::class));
        $this->registry->addWorkflow(new Workflow(new Definition([], []), self::createMock(MarkingStoreInterface::class), self::createMock(EventDispatcherInterface::class), 'workflow3'), $this->createWorkflowSupportStrategy(Subject2::class));
    }

    protected function tearDown(): void
    {
        $this->registry = null;
    }

    public function testHasWithMatch()
    {
        self::assertTrue($this->registry->has(new Subject1()));
    }

    public function testHasWithoutMatch()
    {
        self::assertFalse($this->registry->has(new Subject1(), 'nope'));
    }

    public function testGetWithSuccess()
    {
        $workflow = $this->registry->get(new Subject1());
        self::assertInstanceOf(Workflow::class, $workflow);
        self::assertSame('workflow1', $workflow->getName());

        $workflow = $this->registry->get(new Subject1(), 'workflow1');
        self::assertInstanceOf(Workflow::class, $workflow);
        self::assertSame('workflow1', $workflow->getName());

        $workflow = $this->registry->get(new Subject2(), 'workflow2');
        self::assertInstanceOf(Workflow::class, $workflow);
        self::assertSame('workflow2', $workflow->getName());
    }

    public function testGetWithMultipleMatch()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Too many workflows (workflow2, workflow3) match this subject (Symfony\Component\Workflow\Tests\Subject2); set a different name on each and use the second (name) argument of this method.');
        $w1 = $this->registry->get(new Subject2());
        self::assertInstanceOf(Workflow::class, $w1);
        self::assertSame('workflow1', $w1->getName());
    }

    public function testGetWithNoMatch()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Unable to find a workflow for class "stdClass".');
        $w1 = $this->registry->get(new \stdClass());
        self::assertInstanceOf(Workflow::class, $w1);
        self::assertSame('workflow1', $w1->getName());
    }

    public function testAllWithOneMatchWithSuccess()
    {
        $workflows = $this->registry->all(new Subject1());
        self::assertIsArray($workflows);
        self::assertCount(1, $workflows);
        self::assertInstanceOf(Workflow::class, $workflows[0]);
        self::assertSame('workflow1', $workflows[0]->getName());
    }

    public function testAllWithMultipleMatchWithSuccess()
    {
        $workflows = $this->registry->all(new Subject2());
        self::assertIsArray($workflows);
        self::assertCount(2, $workflows);
        self::assertInstanceOf(Workflow::class, $workflows[0]);
        self::assertInstanceOf(Workflow::class, $workflows[1]);
        self::assertSame('workflow2', $workflows[0]->getName());
        self::assertSame('workflow3', $workflows[1]->getName());
    }

    public function testAllWithNoMatch()
    {
        $workflows = $this->registry->all(new \stdClass());
        self::assertIsArray($workflows);
        self::assertCount(0, $workflows);
    }

    private function createWorkflowSupportStrategy($supportedClassName)
    {
        $strategy = self::createMock(WorkflowSupportStrategyInterface::class);
        $strategy->expects(self::any())->method('supports')
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
