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
    private $transitions = array();
    private $initialPlace;

    /**
     * @param string[]     $places
     * @param Transition[] $transitions
     */
    public function __construct(array $places = array(), array $transitions = array())
    {
        $this->addPlaces($places);
        $this->addTransitions($transitions);
    }

    public function build(): Definition
    {
        return new Definition($this->places, $this->transitions, $this->initialPlace);
    }

    /**
     * Clear all data in the builder.
     *
     * @return $this
     */
    public function reset()
    {
        $this->places = array();
        $this->transitions = array();
        $this->initialPlace = null;

        return $this;
    }

    /**
     * @return $this
     */
    public function setInitialPlace(string $place)
    {
        $this->initialPlace = $place;

        return $this;
    }

    /**
     * @return $this
     */
    public function addPlace(string $place)
    {
        if (!preg_match('{^[\w\d_-]+$}', $place)) {
            throw new InvalidArgumentException(sprintf('The place "%s" contains invalid characters.', $place));
        }

        if (!$this->places) {
            $this->initialPlace = $place;
        }

        $this->places[$place] = $place;

        return $this;
    }

    /**
     * @param string[] $places
     *
     * @return $this
     */
    public function addPlaces(array $places)
    {
        foreach ($places as $place) {
            $this->addPlace($place);
        }

        return $this;
    }

    /**
     * @param Transition[] $transitions
     *
     * @return $this
     */
    public function addTransitions(array $transitions)
    {
        foreach ($transitions as $transition) {
            $this->addTransition($transition);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function addTransition(Transition $transition)
    {
        $this->transitions[] = $transition;

        return $this;
    }
}
