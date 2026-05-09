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

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Transition;

trait WorkflowBuilderTrait
{
    private static function createComplexWorkflowDefinition(): Definition
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

    private static function createSimpleWorkflowDefinition(): Definition
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

    private static function createWorkflowWithMetadataEdgeCases(): Definition
    {
        $places = range('a', 'd');

        $transitions = [];
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t2', 'b', 'c');
        $transitionWithDescription = new Transition('t3', 'c', 'd');
        $transitions[] = $transitionWithDescription;

        $placesMetadata = [];
        // bg_color only, no description
        $placesMetadata['b'] = [
            'bg_color' => 'Orange',
        ];
        // description only, no bg_color
        $placesMetadata['c'] = [
            'description' => 'A <bold> description with "special" chars & entities',
        ];
        // multiline description with bg_color
        $placesMetadata['d'] = [
            'bg_color' => 'Lime',
            'description' => "First line\nSecond line",
        ];

        $transitionsMetadata = new \SplObjectStorage();
        $transitionsMetadata[$transitionWithDescription] = [
            'bg_color' => 'LightCoral',
            'description' => 'Transition description',
        ];
        $inMemoryMetadataStore = new InMemoryMetadataStore([], $placesMetadata, $transitionsMetadata);

        return new Definition($places, $transitions, null, $inMemoryMetadataStore);
    }

    private static function createWorkflowWithSameNameTransition(): Definition
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

    private static function createComplexStateMachineDefinition(): Definition
    {
        $places = ['a', 'b', 'c', 'd'];

        $transitions[] = new Transition('t1', 'a', 'b');
        $transitionWithMetadataDumpStyle = new Transition('t1', 'd', 'b');
        $transitions[] = $transitionWithMetadataDumpStyle;
        $transitionWithMetadataArrowColorBlue = new Transition('t2', 'b', 'c');
        $transitions[] = $transitionWithMetadataArrowColorBlue;
        $transitions[] = new Transition('t3', 'b', 'd');

        $transitionsMetadata = new \SplObjectStorage();
        // PHP 7.2 doesn't allow this heredoc syntax in an array, use a dedicated variable instead
        $label = <<<'EOTXT'
            My custom transition
            label 3
            EOTXT;
        $transitionsMetadata[$transitionWithMetadataDumpStyle] = [
            'label' => $label,
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

    private static function createWorkflowWithSameNameBackTransition(): Definition
    {
        $places = range('a', 'c');

        $transitions = [];
        $transitions[] = new Transition('a_to_bc', 'a', ['b', 'c']);
        $transitions[] = new Transition('back1', 'b', 'a');
        $transitions[] = new Transition('back1', 'c', 'b');
        $transitions[] = new Transition('back2', 'c', 'b');
        $transitions[] = new Transition('back2', 'b', 'a');
        $transitions[] = new Transition('c_to_cb', 'c', ['b', 'c']);

        return new Definition($places, $transitions);

        // The graph looks like:
        //   +-----------------------------------------------------------------+
        //   |                                                                 |
        //   |                                                                 |
        //   |         +---------------------------------------------+         |
        //   v         |                                             v         |
        // +---+     +---------+     +-------+     +---------+     +---+     +-------+
        // | a | --> | a_to_bc | --> |       | --> |  back2  | --> |   | --> | back2 |
        // +---+     +---------+     |       |     +---------+     |   |     +-------+
        //   ^                       |       |                     |   |
        //   |                       |   c   | <-----+             | b |
        //   |                       |       |       |             |   |
        //   |                       |       |     +---------+     |   |     +-------+
        //   |                       |       | --> | c_to_cb | --> |   | --> | back1 |
        //   |                       +-------+     +---------+     +---+     +-------+
        //   |                         |                             ^         |
        //   |                         |                             |         |
        //   |                         v                             |         |
        //   |                       +-------+                       |         |
        //   |                       | back1 | ----------------------+         |
        //   |                       +-------+                                 |
        //   |                                                                 |
        //   +-----------------------------------------------------------------+
    }
}
