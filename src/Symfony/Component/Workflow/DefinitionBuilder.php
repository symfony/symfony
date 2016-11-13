<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

use Symfony\Component\Workflow\Exception\InvalidArgumentException;

/**
 * Builds a definition.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class DefinitionBuilder
{
    private $places = array();
    private $transitionsCollection;
    private $initialPlace;

    /**
     * @param string[]             $places
     * @param (Transition|array)[] $transitions Nested values can be either instances of Transition or
     *                                          arrays with three values: the transition name, and two
     *                                          to pass string or arrays of string for froms and todos
     */
    public function __construct(array $places = array(), array $transitions = array())
    {
        $this->addPlaces($places);
        $this->transitionsCollection = new TransitionsCollectionBuilder();
        $this->transitionsCollection->addTransitions($transitions);
    }

    /**
     * @return Definition
     */
    public function build()
    {
        return new Definition($this->places, $this->transitionsCollection->getTransitions(), $this->initialPlace);
    }

    /**
     * Clear all data in the builder.
     */
    public function reset()
    {
        $this->places = array();
        $this->transitionsCollection->reset();
        $this->initialPlace = null;
    }

    public function setInitialPlace($place)
    {
        $this->initialPlace = $place;
    }

    public function addPlace($place)
    {
        if (!preg_match('{^[\w\d_-]+$}', $place)) {
            throw new InvalidArgumentException(sprintf('The place "%s" contains invalid characters.', $place));
        }

        if (!$this->places) {
            $this->initialPlace = $place;
        }

        $this->places[$place] = $place;
    }

    public function addPlaces(array $places)
    {
        foreach ($places as $place) {
            $this->addPlace($place);
        }
    }

    /**
     * @param (Transition|array)[] $transitions Nested values can be either instances of Transition or
     *                                          arrays with three values: the transition name, and two
     *                                          to pass string or arrays of string for froms and todos
     */
    public function addTransitions(array $transitions)
    {
        $this->transitionsCollection->addTransitions($transitions);
    }

    /**
     * @param Transition|string    $transition
     * @param string[]|string|null $froms
     * @param string[]|string|null $tos
     */
    public function addTransition($transition, $froms = null, $tos = null)
    {
        $this->transitionsCollection->addTransition($transition, $froms, $tos);
    }
}
