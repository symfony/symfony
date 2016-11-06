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
use Symfony\Component\Workflow\Exception\LogicException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Definition
{
    private $places = array();
    private $transitions = array();
    private $initialPlace;

    /**
     * Definition constructor.
     *
     * @param string[]     $places
     * @param Transition[] $transitions
     */
    public function __construct(array $places = array(), array $transitions = array())
    {
        $this->addPlaces($places);
        $this->addTransitions($transitions);
    }

    public function getInitialPlace()
    {
        return $this->initialPlace;
    }

    public function getPlaces()
    {
        return $this->places;
    }

    /**
     * @return Transition[]
     */
    public function getTransitions()
    {
        return $this->transitions;
    }

    public function setInitialPlace($place)
    {
        if (!isset($this->places[$place])) {
            throw new LogicException(sprintf('Place "%s" cannot be the initial place as it does not exist.', $place));
        }

        $this->initialPlace = $place;
    }

    public function addPlace($place)
    {
        if (!preg_match('{^[\w\d_-]+$}', $place)) {
            throw new InvalidArgumentException(sprintf('The place "%s" contains invalid characters.', $place));
        }

        if (!count($this->places)) {
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

    public function addTransitions(array $transitions)
    {
        foreach ($transitions as $transition) {
            $this->addTransition($transition);
        }
    }

    public function addTransition(Transition $transition)
    {
        $name = $transition->getName();

        foreach ($transition->getFroms() as $from) {
            if (!isset($this->places[$from])) {
                throw new LogicException(sprintf('Place "%s" referenced in transition "%s" does not exist.', $from, $name));
            }
        }

        foreach ($transition->getTos() as $to) {
            if (!isset($this->places[$to])) {
                throw new LogicException(sprintf('Place "%s" referenced in transition "%s" does not exist.', $to, $name));
            }
        }

        $this->transitions[$name] = $transition;
    }
}
