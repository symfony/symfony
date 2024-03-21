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

use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final class EnteredEvent extends Event
{
    use EventNameTrait {
        getNameForPlace as public getName;
    }
    use HasContextTrait;

    public function __construct(object $subject, Marking $marking, ?Transition $transition = null, ?WorkflowInterface $workflow = null, array $context = [])
    {
        parent::__construct($subject, $marking, $transition, $workflow);

        $this->context = $context;
    }
}
