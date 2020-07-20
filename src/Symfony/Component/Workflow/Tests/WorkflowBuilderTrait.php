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

        $transitions = [];
        $transitions[] = new Transition('t1', 'a', ['b', 'c']);
        $transitions[] = new Transition('t2', ['b', 'c'], 'd');
        $transitionWithMetadataDumpStyle = new Transition('t3', 'd', 'e');
        $transitions[] = $transitionWithMetadataDumpStyle;
        $transitions[] = new Transition('t4', 'd', 'f');
        $transitions[] = new Transition('t5', 'e', 'g');
        $transitions[] = new Transition('t6', 'f', 'g');

        $transitionsMetadata = new \SplObjectStorage();
        $transitionsMetadata[$transitionWithMetadataDumpStyle] = [
            'label' => 'My custom transition label 1',
            'color' => 'Red',
            'arrow_color' => 'Green',
        ];
        $inMemoryMetadataStore = new InMemoryMetadataStore([], [], $transitionsMetadata);

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

        $transitions = [];
        $transitionWithMetadataDumpStyle = new Transition('t1', 'a', 'b');
        $transitions[] = $transitionWithMetadataDumpStyle;
        $transitionWithMetadataArrowColorPink = new Transition('t2', 'b', 'c');
        $transitions[] = $transitionWithMetadataArrowColorPink;

        $placesMetadata = [];
        $placesMetadata['c'] = [
            'bg_color' => 'DeepSkyBlue',
            'description' => 'My custom place description',
        ];

        $transitionsMetadata = new \SplObjectStorage();
        $transitionsMetadata[$transitionWithMetadataDumpStyle] = [
            'label' => 'My custom transition label 2',
            'color' => 'Grey',
            'arrow_color' => 'Purple',
        ];
        $transitionsMetadata[$transitionWithMetadataArrowColorPink] = [
            'arrow_color' => 'Pink',
        ];
        $inMemoryMetadataStore = new InMemoryMetadataStore([], $placesMetadata, $transitionsMetadata);

        return new Definition($places, $transitions, null, $inMemoryMetadataStore);

        // The graph looks like:
        // +---+     +----+     +---+     +----+     +---+
        // | a | --> | t1 | --> | b | --> | t2 | --> | c |
        // +---+     +----+     +---+     +----+     +---+
    }

    private function createWorkflowWithSameNameTransition()
    {
        $places = range('a', 'c');

        $transitions = [];
        $transitions[] = new Transition('a_to_bc', 'a', ['b', 'c']);
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
        $places = ['a', 'b', 'c', 'd'];

        $transitions[] = new Transition('t1', 'a', 'b');
        $transitionWithMetadataDumpStyle = new Transition('t1', 'd', 'b');
        $transitions[] = $transitionWithMetadataDumpStyle;
        $transitionWithMetadataArrowColorBlue = new Transition('t2', 'b', 'c');
        $transitions[] = $transitionWithMetadataArrowColorBlue;
        $transitions[] = new Transition('t3', 'b', 'd');

        $transitionsMetadata = new \SplObjectStorage();
        $transitionsMetadata[$transitionWithMetadataDumpStyle] = [
            'label' => 'My custom transition label 3',
            'color' => 'Grey',
            'arrow_color' => 'Red',
        ];
        $transitionsMetadata[$transitionWithMetadataArrowColorBlue] = [
            'arrow_color' => 'Blue',
        ];
        $inMemoryMetadataStore = new InMemoryMetadataStore([], [], $transitionsMetadata);

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
