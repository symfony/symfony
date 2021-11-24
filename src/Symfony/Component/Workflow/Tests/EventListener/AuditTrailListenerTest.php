<?php

namespace Symfony\Component\Workflow\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\EventListener\AuditTrailListener;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Tests\Subject;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;
use Symfony\Component\Workflow\Workflow;

class AuditTrailListenerTest extends TestCase
{
    use WorkflowBuilderTrait;

    public function testItWorksWithStrings()
    {
        $definition = $this->createSimpleWorkflowDefinition();

        $object = new Subject();

        $logger = new Logger();

        $ed = new EventDispatcher();
        $ed->addSubscriber(new AuditTrailListener($logger));

        $workflow = new Workflow($definition, new MethodMarkingStore(), $ed);

        $workflow->apply($object, 't1');

        $expected = [
            'Leaving "a" for subject of class "Symfony\Component\Workflow\Tests\Subject" in workflow "unnamed".',
            'Transition "t1" for subject of class "Symfony\Component\Workflow\Tests\Subject" in workflow "unnamed".',
            'Entering "b" for subject of class "Symfony\Component\Workflow\Tests\Subject" in workflow "unnamed".',
        ];

        $this->assertSame($expected, $logger->logs);
    }

    /**
     * @requires PHP 8.1
     */
    public function testItWorksWithEnumerations()
    {
        $definition = $this->createSimpleWorkflowDefinition(true);

        $object = new Subject();

        $logger = new Logger();

        $ed = new EventDispatcher();
        $ed->addSubscriber(new AuditTrailListener($logger));

        $workflow = new Workflow($definition, new MethodMarkingStore(), $ed);

        $workflow->apply($object, 't1');

        $expected = [
            'Leaving "Symfony\Component\Workflow\Tests\fixtures\AlphabeticalEnum::A" for subject of class "Symfony\Component\Workflow\Tests\Subject" in workflow "unnamed".',
            'Transition "t1" for subject of class "Symfony\Component\Workflow\Tests\Subject" in workflow "unnamed".',
            'Entering "Symfony\Component\Workflow\Tests\fixtures\AlphabeticalEnum::B" for subject of class "Symfony\Component\Workflow\Tests\Subject" in workflow "unnamed".',
        ];

        $this->assertSame($expected, $logger->logs);
    }
}

class Logger extends AbstractLogger
{
    public $logs = [];

    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = $message;
    }
}
