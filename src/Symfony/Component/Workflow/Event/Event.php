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

use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Event extends GenericEvent
{
    private $marking;
    private $transition;

    /**
     * @param object     $subject
     * @param Marking    $marking
     * @param Transition $transition
     * @param array      $arguments
     */
    public function __construct($subject, Marking $marking, Transition $transition, array $arguments = array())
    {
        parent::__construct($subject, $arguments);

        $this->marking = $marking;
        $this->transition = $transition;
    }

    public function getMarking()
    {
        return $this->marking;
    }

    public function getTransition()
    {
        return $this->transition;
    }
}
