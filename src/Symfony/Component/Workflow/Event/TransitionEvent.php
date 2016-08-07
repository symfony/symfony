<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Event;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TransitionEvent extends Event
{
    private $nextState;

    public function setNextState($state)
    {
        $this->nextState = $state;
    }

    public function getNextState()
    {
        return $this->nextState;
    }
}
