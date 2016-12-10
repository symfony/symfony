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

use Symfony\Component\EventDispatcher\Event as BaseEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Event extends BaseEvent
{
    private $subject;
    private $marking;
    private $transition;

    /**
     * @param object     $subject
     * @param Marking    $marking
     * @param Transition $transition
     */
    public function __construct($subject, Marking $marking, Transition $transition)
    {
        $this->subject = $subject;
        $this->marking = $marking;
        $this->transition = $transition;
    }

    public function getMarking()
    {
        return $this->marking;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getTransition()
    {
        return $this->transition;
    }
}
