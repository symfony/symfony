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

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Definition
{
    private $class;
    private $states = array();
    private $transitions = array();
    private $initialState;

    public function __construct($class)
    {
        $this->class = $class;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function getStates()
    {
        return $this->states;
    }

    public function getTransitions()
    {
        return $this->transitions;
    }

    public function getInitialState()
    {
        return $this->initialState;
    }

    public function setInitialState($name)
    {
        if (!isset($this->states[$name])) {
            throw new \LogicException(sprintf('State "%s" cannot be the initial state as it does not exist.', $name));
        }

        $this->initialState = $name;
    }

    public function addState($name)
    {
        if (!count($this->states)) {
            $this->initialState = $name;
        }

        $this->states[$name] = $name;
    }

    public function addTransition(Transition $transition)
    {
        if (isset($this->transitions[$transition->getName()])) {
            throw new \LogicException(sprintf('Transition "%s" is already defined.', $transition->getName()));
        }

        foreach ($transition->getFroms() as $from) {
            if (!isset($this->states[$from])) {
                throw new \LogicException(sprintf('State "%s" referenced in transition "%s" does not exist.', $from, $name));
            }
        }

        foreach ($transition->getTos() as $to) {
            if (!isset($this->states[$to])) {
                throw new \LogicException(sprintf('State "%s" referenced in transition "%s" does not exist.', $to, $name));
            }
        }

        $this->transitions[$transition->getName()] = $transition;
    }
}
