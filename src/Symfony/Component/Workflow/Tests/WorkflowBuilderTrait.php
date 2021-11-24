<?php

namespace Symfony\Component\Workflow\Tests;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Tests\fixtures\AlphabeticalEnum;
use Symfony\Component\Workflow\Transition;

trait WorkflowBuilderTrait
{
    private function createComplexWorkflowDefinition(bool $useEnumerations = false)
    {
        $places = $useEnumerations ? AlphabeticalEnum::cases() : range('a', 'g');

        $transitions = [];
        $transitions[] = new Transition('t1', $this->getTypedPlaceValue('a', $useEnumerations), [
            $this->getTypedPlaceValue('b', $useEnumerations),
            $this->getTypedPlaceValue('c', $useEnumerations),
        ]);
        $transitions[] = new Transition('t2', [
            $this->getTypedPlaceValue('b', $useEnumerations),
            $this->getTypedPlaceValue('c', $useEnumerations),
        ], $this->getTypedPlaceValue('d', $useEnumerations));
        $transitionWithMetadataDumpStyle = new Transition('t3', $this->getTypedPlaceValue('d', $useEnumerations), $this->getTypedPlaceValue('e', $useEnumerations));
        $transitions[] = $transitionWithMetadataDumpStyle;
        $transitions[] = new Transition('t4', $this->getTypedPlaceValue('d', $useEnumerations), $this->getTypedPlaceValue('f', $useEnumerations));
        $transitions[] = new Transition('t5', $this->getTypedPlaceValue('e', $useEnumerations), $this->getTypedPlaceValue('g', $useEnumerations));
        $transitions[] = new Transition('t6', $this->getTypedPlaceValue('f', $useEnumerations), $this->getTypedPlaceValue('g', $useEnumerations));

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

    private function createSimpleWorkflowDefinition(bool $useEnumerations = false)
    {
        $places = $useEnumerations ? AlphabeticalEnum::cases() : range('a', 'c');

        $transitions = [];
        $transitionWithMetadataDumpStyle = new Transition('t1', $this->getTypedPlaceValue('a', $useEnumerations), $this->getTypedPlaceValue('b', $useEnumerations));
        $transitions[] = $transitionWithMetadataDumpStyle;
        $transitionWithMetadataArrowColorPink = new Transition('t2', $this->getTypedPlaceValue('b', $useEnumerations), $this->getTypedPlaceValue('c', $useEnumerations));
        $transitions[] = $transitionWithMetadataArrowColorPink;

        $placesMetadata = [];
        $placesMetadata[$this->getPlaceKey($useEnumerations ? AlphabeticalEnum::C : 'c')] = [
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

    private function createWorkflowWithSameNameTransition(bool $useEnumerations = false)
    {
        $places = $useEnumerations ? AlphabeticalEnum::cases() : range('a', 'c');

        $transitions = [];
        $transitions[] = new Transition('a_to_bc', $this->getTypedPlaceValue('a', $useEnumerations), [
            $this->getTypedPlaceValue('b', $useEnumerations),
            $this->getTypedPlaceValue('c', $useEnumerations),
        ]);
        $transitions[] = new Transition('b_to_c', $this->getTypedPlaceValue('b', $useEnumerations), $this->getTypedPlaceValue('c', $useEnumerations));
        $transitions[] = new Transition('to_a', $this->getTypedPlaceValue('b', $useEnumerations), $this->getTypedPlaceValue('a', $useEnumerations));
        $transitions[] = new Transition('to_a', $this->getTypedPlaceValue('c', $useEnumerations), $this->getTypedPlaceValue('a', $useEnumerations));

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

    private function getTypedPlaceValue(string $value, bool $useEnumeration = false): string|AlphabeticalEnum
    {
        if ($useEnumeration) {
            $value = AlphabeticalEnum::tryFrom($value) ?? $value;
        }

        return $value;
    }

    private function getPlaceKey(string|\UnitEnum $value): string
    {
        return $value instanceof \UnitEnum ? \get_class($value).'::'.$value->name : $value;
    }

    private function getPlaceEventSuffix(string $value, bool $useEnumerations): string
    {
        return $useEnumerations ? $this->getPlaceKey(AlphabeticalEnum::tryFrom($value)) : $value;
    }
}
