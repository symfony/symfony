<?php

namespace Symfony\Component\Workflow\Tests;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Transition;

trait WorkflowBuilderTrait
{
    private function createComplexWorkflow()
    {
        $places = range('a', 'g');

        $transitions = array();
        $transitions[] = new Transition('t1', 'a', array('b', 'c'));
        $transitions[] = new Transition('t2', array('b', 'c'), 'd');
        $transitions[] = new Transition('t3', 'd', 'e');
        $transitions[] = new Transition('t4', 'd', 'f');
        $transitions[] = new Transition('t5', 'e', 'g');
        $transitions[] = new Transition('t6', 'f', 'g');

        return new Definition($places, $transitions);

        // The graph looks like:
        // +---+     +----+     +---+     +----+     +----+     +----+     +----+     +----+     +---+
        // | a | --> | t1 | --> | c | --> | t2 | --> | d  | --> | t4 | --> | f  | --> | t6 | --> | g |
        // +---+     +----+     +---+     +----+     +----+     +----+     +----+     +----+     +---+
        //             |                    ^          |                                           ^
        //             |                    |          |                                           |
        //             v                    |          v                                           |
        //           +----+                 |        +----+     +----+     +----+                  |
        //           | b  | ----------------+        | t3 | --> | e  | --> | t5 | -----------------+
        //           +----+                          +----+     +----+     +----+
    }

    public function createSimpleWorkflowDefinition()
    {
        $places = range('a', 'c');

        $transitions = array();
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t2', 'b', 'c');

        return new Definition($places, $transitions);
    }
}
