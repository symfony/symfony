<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Validator;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Exception\InvalidDefinitionException;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class StateMachineValidator implements DefinitionValidatorInterface
{
    public function validate(Definition $definition, string $name): void
    {
        $transitionFromNames = [];
        foreach ($definition->getTransitions() as $transition) {
            // Make sure that each transition has exactly one TO
            if (1 !== \count($transition->getTos(true))) {
                throw new InvalidDefinitionException(\sprintf('A transition in StateMachine can only have one output. But the transition "%s" in StateMachine "%s" has %d outputs.', $transition->getName(), $name, \count($transition->getTos(true))));
            }
            foreach ($transition->getFroms(true) as $arc) {
                if (1 < $arc->weight) {
                    throw new InvalidDefinitionException(\sprintf('A transition in StateMachine can only have arc with weight equals to one. But the transition "%s" in StateMachine "%s" has an arc from "%s" to the transition with a weight equals to %d.', $transition->getName(), $name, $arc->place, $arc->weight));
                }
            }

            // Make sure that each transition has exactly one FROM
            $fromArcs = $transition->getFroms(true);
            if (1 !== \count($fromArcs)) {
                throw new InvalidDefinitionException(\sprintf('A transition in StateMachine can only have one input. But the transition "%s" in StateMachine "%s" has %d inputs.', $transition->getName(), $name, \count($fromArcs)));
            }
            foreach ($transition->getTos(true) as $arc) {
                if (1 !== $arc->weight) {
                    throw new InvalidDefinitionException(\sprintf('A transition in StateMachine can only have arc with weight equals to one. But the transition "%s" in StateMachine "%s" has an arc from the transition to "%s" with a weight equals to %d.', $transition->getName(), $name, $arc->place, $arc->weight));
                }
            }

            // Enforcing uniqueness of the names of transitions starting at each node
            $fromArc = reset($fromArcs);
            $from = $fromArc->place;
            if (isset($transitionFromNames[$from][$transition->getName()])) {
                throw new InvalidDefinitionException(\sprintf('A transition from a place/state must have an unique name. Multiple transitions named "%s" from place/state "%s" were found on StateMachine "%s".', $transition->getName(), $from, $name));
            }

            $transitionFromNames[$from][$transition->getName()] = true;
        }

        $initialPlaces = $definition->getInitialPlaces();
        if (2 <= \count($initialPlaces)) {
            throw new InvalidDefinitionException(\sprintf('The state machine "%s" cannot store many places. But the definition has %d initial places. Only one is supported.', $name, \count($initialPlaces)));
        }
    }
}
