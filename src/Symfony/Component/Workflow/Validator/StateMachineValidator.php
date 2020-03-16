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
    public function validate(Definition $definition, string $name)
    {
        $transitionFromNames = [];
        foreach ($definition->getTransitions() as $transition) {
            // Make sure that each transition has exactly one TO
            if (1 !== \count($transition->getTos())) {
                throw new InvalidDefinitionException(sprintf('A transition in StateMachine can only have one output. But the transition "%s" in StateMachine "%s" has %d outputs.', $transition->getName(), $name, \count($transition->getTos())));
            }

            // Make sure that each transition has exactly one FROM
            $froms = $transition->getFroms();
            if (1 !== \count($froms)) {
                throw new InvalidDefinitionException(sprintf('A transition in StateMachine can only have one input. But the transition "%s" in StateMachine "%s" has %d inputs.', $transition->getName(), $name, \count($froms)));
            }

            // Enforcing uniqueness of the names of transitions starting at each node
            $from = reset($froms);
            if (isset($transitionFromNames[$from][$transition->getName()])) {
                throw new InvalidDefinitionException(sprintf('A transition from a place/state must have an unique name. Multiple transitions named "%s" from place/state "%s" were found on StateMachine "%s".', $transition->getName(), $from, $name));
            }

            $transitionFromNames[$from][$transition->getName()] = true;
        }

        $initialPlaces = $definition->getInitialPlaces();
        if (2 <= \count($initialPlaces)) {
            throw new InvalidDefinitionException(sprintf('The state machine "%s" can not store many places. But the definition has %d initial places. Only one is supported.', $name, \count($initialPlaces)));
        }
    }
}
