<?php

namespace Symfony\Component\Workflow\Tests;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Transition;

trait WorkflowBuilderTrait
{
    private function createComplexWorkflowDefinition()
    {
        $places = range('a', 'g');

        $transitions = array();
        $transitions[] = new Transition('t1', 'a', array('b', 'c'));
        $transitions[] = new Transition('t2', array('b', 'c'), 'd');
        $transitionWithMetadataColorGreen = new Transition('t3', 'd', 'e');
        $transitions[] = $transitionWithMetadataColorGreen;
        $transitions[] = new Transition('t4', 'd', 'f');
        $transitions[] = new Transition('t5', 'e', 'g');
        $transitions[] = new Transition('t6', 'f', 'g');

        $transitionsMetadata = new \SplObjectStorage();
        $transitionsMetadata[$transitionWithMetadataColorGreen] = array('color' => 'Green');
        $inMemoryMetadataStore = new InMemoryMetadataStore(array(), array(), $transitionsMetadata);

        return new Definition($places, $transitions, null, $inMemoryMetadataStore);

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

    private function createSimpleWorkflowDefinition()
    {
        $places = range('a', 'c');

        $transitions = array();
        $transitionWithMetadataColorPurple = new Transition('t1', 'a', 'b');
        $transitions[] = $transitionWithMetadataColorPurple;
        $transitionWithMetadataColorPink = new Transition('t2', 'b', 'c');
        $transitions[] = $transitionWithMetadataColorPink;

        $transitionsMetadata = new \SplObjectStorage();
        $transitionsMetadata[$transitionWithMetadataColorPurple] = array('color' => 'Purple');
        $transitionsMetadata[$transitionWithMetadataColorPink] = array('color' => 'Pink');
        $inMemoryMetadataStore = new InMemoryMetadataStore(array(), array(), $transitionsMetadata);

        return new Definition($places, $transitions, null, $inMemoryMetadataStore);

        // The graph looks like:
        // +---+     +----+     +---+     +----+     +---+
        // | a | --> | t1 | --> | b | --> | t2 | --> | c |
        // +---+     +----+     +---+     +----+     +---+
    }

    private function createWorkflowWithSameNameTransition()
    {
        $places = range('a', 'c');

        $transitions = array();
        $transitions[] = new Transition('a_to_bc', 'a', array('b', 'c'));
        $transitions[] = new Transition('b_to_c', 'b', 'c');
        $transitions[] = new Transition('to_a', 'b', 'a');
        $transitions[] = new Transition('to_a', 'c', 'a');

        return new Definition($places, $transitions);

        // The graph looks like:
        //   +------------------------------------------------------------+
        //   |                                                            |
        //   |                                                            |
        //   |         +----------------------------------------+         |
        //   v         |                                        v         |
        // +---+     +---------+     +---+     +--------+     +---+     +------+
        // | a | --> | a_to_bc | --> | b | --> | b_to_c | --> | c | --> | to_a | -+
        // +---+     +---------+     +---+     +--------+     +---+     +------+  |
        //   ^                         |                                  ^       |
        //   |                         +----------------------------------+       |
        //   |                                                                    |
        //   |                                                                    |
        //   +--------------------------------------------------------------------+
    }

    private function createComplexStateMachineDefinition()
    {
        $places = array('a', 'b', 'c', 'd');

        $transitions[] = new Transition('t1', 'a', 'b');
        $transitionWithMetadataColorRed = new Transition('t1', 'd', 'b');
        $transitions[] = $transitionWithMetadataColorRed;
        $transitionWithMetadataColorBlue = new Transition('t2', 'b', 'c');
        $transitions[] = $transitionWithMetadataColorBlue;
        $transitions[] = new Transition('t3', 'b', 'd');

        $transitionsMetadata = new \SplObjectStorage();
        $transitionsMetadata[$transitionWithMetadataColorRed] = array('color' => 'Red');
        $transitionsMetadata[$transitionWithMetadataColorBlue] = array('color' => 'Blue');
        $inMemoryMetadataStore = new InMemoryMetadataStore(array(), array(), $transitionsMetadata);

        return new Definition($places, $transitions, null, $inMemoryMetadataStore);

        // The graph looks like:
        //                     t1
        //               +------------------+
        //               v                  |
        // +---+  t1   +-----+  t2   +---+  |
        // | a | ----> |  b  | ----> | c |  |
        // +---+       +-----+       +---+  |
        //               |                  |
        //               | t3               |
        //               v                  |
        //             +-----+              |
        //             |  d  | -------------+
        //             +-----+
    }
}
