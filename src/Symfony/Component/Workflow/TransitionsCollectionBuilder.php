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
 * TransitionsCollectionBuilder.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class TransitionsCollectionBuilder
{
    /**
     * @var Transition[]
     */
    private $transitions = array();

    /**
     * @param Transition|string   $transition
     * @param string[]string|null $froms
     * @param string[]string|null $tos
     */
    public function addTransition($transition, $froms = null, $tos = null)
    {
        if ($transition instanceof Transition) {
            $this->add($transition);

            return;
        }

        $this->createTransition($transition, $froms, $tos);
    }

    /**
     * @param array[] $transitions An array of arrays of three arguments to pass to "addTransition"
     */
    public function addTransitions(array $transitions)
    {
        foreach ($transitions as $transition) {
            if ($transition instanceof Transition) {
                $this->add($transition);

                continue;
            }

            if (!is_array($transition) || 3 !== count($transition)) {
                throw new InvalidArgumentException(sprintf(
                    'Calling "%s" expected each entry to be an instance of "%s" or an array of three arguments to create a transition instance%s: "name", "froms", and "tos", but got %s.',
                    __METHOD__,
                    Transition::class,
                    isset($transition[0]) ? ' named "'.$transition[0].'"' : '',
                    is_array($transition) ? ' an array with '.count($transition).' entries' : gettype($transition)));
            }
            list($name, $froms, $tos) = $transition;
            $this->addTransition($name, $froms, $tos);
        }
    }

    public function getTransitions()
    {
        return $this->transitions;
    }

    public function reset()
    {
        return $this->transitions = array();
    }

    private function createTransition($name, $froms, $tos)
    {
        $this->add(new Transition($name, $froms, $tos));

    }

    private function add(Transition $newTransition) {
        foreach ($this->transitions as $transition) {
            if ($newTransition == $transition) {
                throw new InvalidArgumentException(sprintf('The transition named "%s" has already been added.', $transition->getName()));
            }
        }
        $this->transitions[] = $newTransition;
    }
}
