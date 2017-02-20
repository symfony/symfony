<?php

namespace Symfony\Component\Workflow\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\EventListener\AuditTrailListener;
use Symfony\Component\Workflow\MarkingStore\MultipleStateMarkingStore;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;
use Symfony\Component\Workflow\Tests\createSimpleWorkflowDefinition;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

class AuditTrailListenerTest extends TestCase
{
    use WorkflowBuilderTrait;

    public function testItWorks()
    {
        $definition = $this->createSimpleWorkflowDefinition();

        $object = new \stdClass();
        $object->marking = null;

        $logger = new Logger();

        $ed = new EventDispatcher();
        $ed->addSubscriber(new AuditTrailListener($logger));

        $workflow = new Workflow($definition, new MultipleStateMarkingStore(), $ed);

        $workflow->apply($object, 't1');

        $expected = array(
            'Leaving "a" for subject of class "stdClass".',
            'Transition "t1" for subject of class "stdClass".',
            'Entering "b" for subject of class "stdClass".',
        );

        $this->assertSame($expected, $logger->logs);
    }
}

class Logger extends AbstractLogger
{
    public $logs = array();

    public function log($level, $message, array $context = array())
    {
        $this->logs[] = $message;
    }
}
