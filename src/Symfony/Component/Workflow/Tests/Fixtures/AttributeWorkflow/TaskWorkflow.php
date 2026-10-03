<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow;

use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\WorkflowTrait;

#[AsWorkflow(supports: Task::class, markingProperty: 'step', metadata: ['title' => 'Task'])]
final class TaskWorkflow
{
    use WorkflowTrait;

    #[Transition(from: TaskStep::New, to: TaskStep::Processing)]
    public const START = 'start';

    #[Transition(from: TaskStep::Processing, to: TaskStep::Done)]
    #[Transition(from: TaskStep::Failed, to: TaskStep::Done)]
    public const FINISH = 'finish';

    #[Transition(from: TaskStep::Processing, to: TaskStep::Failed)]
    public const FAIL = 'fail';

    public function start(Task $task): Marking
    {
        return $this->apply($task, self::START, ['started_by' => 'start()']);
    }

    #[AsGuardListener(transition: self::START)]
    public function guardStart(GuardEvent $event): void
    {
        if ($event->getSubject()->blocked) {
            $event->setBlocked(true, 'The task is blocked.');
        }
    }

    #[AsTransitionListener(transition: self::START)]
    public function onStart(TransitionEvent $event): void
    {
        $event->getSubject()->context = $event->getContext();
    }
}
