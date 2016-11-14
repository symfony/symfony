<?php

namespace Symfony\Component\Workflow\Tests\EventListener;

use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\EventListener\AuditTrailListener;
use Symfony\Component\Workflow\MarkingStore\PropertyAccessMarkingStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

class AuditTrailListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testItWorks()
    {
        $transitions = array(
            new Transition('t1', 'a', 'b'),
            new Transition('t2', 'a', 'b'),
        );

        $definition = new Definition(array('a', 'b'), $transitions);

        $object = new \stdClass();
        $object->marking = null;

        $logger = new Logger();

        $ed = new EventDispatcher();
        $ed->addSubscriber(new AuditTrailListener($logger));

        $workflow = new Workflow($definition, new PropertyAccessMarkingStore(), $ed);

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
