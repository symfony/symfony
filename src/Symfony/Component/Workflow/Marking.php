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
 * A base Marking which contains the state of the
 * state of a Workflow or a StateMachine a representation
 * by places with tokens.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Jules Pietri <jules@heahprod.com>
 */
abstract class Marking
{
    const STRATEGY_SINGLE_STATE = 'single_state';
    const STRATEGY_MULTIPLE_STATE = 'multiple_state';

    protected $places = array();

    /**
     * @param string $place
     *
     * @return bool
     */
    final public function has($place)
    {
        return isset($this->places[$place]);
    }

    /**
     * @return int[] An array of places as keys and token counts as values.
     */
    final public function getState()
    {
        return $this->places;
    }

    /**
     * @param string $place
     */
    abstract public function mark($place);

    /**
     * @param string $place
     */
    abstract public function unmark($place);
}
