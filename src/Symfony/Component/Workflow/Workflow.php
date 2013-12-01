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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Workflow
{
    private $name;
    private $dispatcher;
    private $propertyAccessor;
    private $property = 'state';
    private $stateTransitions = array();
    private $states;
    private $initialState;
    private $class;

    public function __construct($name, Definition $definition, EventDispatcherInterface $dispatcher = null)
    {
        $this->name = $name;
        $this->dispatcher = $dispatcher;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();

        $this->states = $definition->getStates();
        $this->class = $definition->getClass();
        $this->initialState = $definition->getInitialState();
        foreach ($definition->getTransitions() as $name => $transition) {
            $this->transitions[$name] = $transition;
            foreach ($transition->getFroms() as $from) {
                $this->stateTransitions[$from][$name] = $name;
            }
        }
    }

    public function supports($class)
    {
        return $class instanceof $this->class;
    }

    public function can($object, $transition)
    {
        if (!isset($this->transitions[$transition])) {
            throw new \LogicException(sprintf('Transition "%s" does not exist for workflow "%s".', $transition, $this->name));
        }

        if (null !== $this->dispatcher) {
            $event = new GuardEvent($object, $this->getState($object));

            $this->dispatcher->dispatch(sprintf('workflow.%s.guard.%s', $this->name, $transition), $event);

            if (null !== $ret = $event->isAllowed()) {
                return $ret;
            }
        }

        return isset($this->stateTransitions[$this->getState($object)][$transition]);
    }

    public function getState($object)
    {
        $state = $this->propertyAccessor->getValue($object, $this->property);

        // check if the object is already in the workflow
        if (null === $state) {
            $this->enter($object, $this->initialState, array());

            $state = $this->propertyAccessor->getValue($object, $this->property);
        }

        // check that the object has a known state
        if (!isset($this->states[$state])) {
            throw new \LogicException(sprintf('State "%s" is not valid for workflow "%s".', $transition, $this->name));
        }

        return $state;
    }

    public function apply($object, $transition, array $attributes = array())
    {
        $current = $this->getState($object);

        if (!$this->can($object, $transition)) {
            throw new \LogicException(sprintf('Unable to apply transition "%s" from state "%s" for workflow "%s".', $transition, $current, $this->name));
        }

        $transition = $this->determineTransition($current, $transition);

        $this->leave($object, $current, $attributes);

        $state = $this->transition($object, $current, $transition, $attributes);

        $this->enter($object, $state, $attributes);
    }

    public function getAvailableTransitions($object)
    {
        return array_keys($this->stateTransitions[$this->getState($object)]);
    }

    public function getNextStates($object)
    {
        if (!$stateTransitions = $this->stateTransitions[$this->getState($object)]) {
            return array();
        }

        $states = array();
        foreach ($stateTransitions as $transition) {
            foreach ($this->transitions[$transition]->getTos() as $to) {
                $states[] = $to;
            }
        }

        return $states;
    }

    public function setStateProperty($property)
    {
        $this->property = $property;
    }

    public function setPropertyAccessor(PropertyAccessor $propertyAccessor)
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    public function __call($method, $arguments)
    {
        if (!count($arguments)) {
            throw new BadMethodCallException();
        }

        return $this->apply($arguments[0], $method, array_slice($arguments, 1));
    }

    private function leave($object, $state, $attributes)
    {
        if (null === $this->dispatcher) {
            return;
        }

        $this->dispatcher->dispatch(sprintf('workflow.leave', $this->name), new Event($object, $state, $attributes));
        $this->dispatcher->dispatch(sprintf('workflow.%s.leave', $this->name), new Event($object, $state, $attributes));
        $this->dispatcher->dispatch(sprintf('workflow.%s.leave.%s', $this->name, $state), new Event($object, $state, $attributes));
    }

    private function transition($object, $current, Transition $transition, $attributes)
    {
        $state = null;
        $tos = $transition->getTos();

        if (null !== $this->dispatcher) {
            // the generic event cannot change the next state
            $this->dispatcher->dispatch(sprintf('workflow.transition', $this->name), new Event($object, $current, $attributes));
            $this->dispatcher->dispatch(sprintf('workflow.%s.transition', $this->name), new Event($object, $current, $attributes));

            $event = new TransitionEvent($object, $current, $attributes);
            $this->dispatcher->dispatch(sprintf('workflow.%s.transition.%s', $this->name, $transition->getName()), $event);
            $state = $event->getNextState();

            if (null !== $state && !in_array($state, $tos)) {
                throw new \LogicException(sprintf('Transition "%s" cannot go to state "%s" for workflow "%s"', $transition->getName(), $state, $this->name));
            }
        }

        if (null === $state) {
            if (count($tos) > 1) {
                throw new \LogicException(sprintf('Unable to apply transition "%s" as the new state is not unique for workflow "%s".', $transition->getName(), $this->name));
            }

            $state = $tos[0];
        }

        return $state;
    }

    private function enter($object, $state, $attributes)
    {
        $this->propertyAccessor->setValue($object, $this->property, $state);

        if (null !== $this->dispatcher) {
            $this->dispatcher->dispatch(sprintf('workflow.enter', $this->name), new Event($object, $state, $attributes));
            $this->dispatcher->dispatch(sprintf('workflow.%s.enter', $this->name), new Event($object, $state, $attributes));
            $this->dispatcher->dispatch(sprintf('workflow.%s.enter.%s', $this->name, $state), new Event($object, $state, $attributes));
        }
    }

    private function determineTransition($current, $transition)
    {
        return $this->transitions[$transition];
    }
}
