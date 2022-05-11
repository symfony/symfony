<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\TransitionBlocker;

class StateMachineTest extends TestCase
{
    use WorkflowBuilderTrait;

    public function testCan()
    {
        $definition = $this->createComplexStateMachineDefinition();

        $net = new StateMachine($definition);
        $subject = new Subject();

        // If you are in place "a" you should be able to apply "t1"
        $subject->setMarking('a');
        $this->assertTrue($net->can($subject, 't1'));
        $subject->setMarking('d');
        $this->assertTrue($net->can($subject, 't1'));

        $subject->setMarking('b');
        $this->assertFalse($net->can($subject, 't1'));
    }

    public function testCanWithMultipleTransition()
    {
        $definition = $this->createComplexStateMachineDefinition();

        $net = new StateMachine($definition);
        $subject = new Subject();

        // If you are in place "b" you should be able to apply "t1" and "t2"
        $subject->setMarking('b');
        $this->assertTrue($net->can($subject, 't2'));
        $this->assertTrue($net->can($subject, 't3'));
    }

    public function testBuildTransitionBlockerList()
    {
        $definition = $this->createComplexStateMachineDefinition();

        $net = new StateMachine($definition);
        $subject = new Subject();

        $subject->setMarking('a');
        $this->assertTrue($net->buildTransitionBlockerList($subject, 't1')->isEmpty());
        $subject->setMarking('d');
        $this->assertTrue($net->buildTransitionBlockerList($subject, 't1')->isEmpty());

        $subject->setMarking('b');
        $this->assertFalse($net->buildTransitionBlockerList($subject, 't1')->isEmpty());
    }

    public function testBuildTransitionBlockerListWithMultipleTransitions()
    {
        $definition = $this->createComplexStateMachineDefinition();

        $net = new StateMachine($definition);
        $subject = new Subject();

        $subject->setMarking('b');
        $this->assertTrue($net->buildTransitionBlockerList($subject, 't2')->isEmpty());
        $this->assertTrue($net->buildTransitionBlockerList($subject, 't3')->isEmpty());
    }

    public function testBuildTransitionBlockerListReturnsExpectedReasonOnBranchMerge()
    {
        $definition = $this->createComplexStateMachineDefinition();

        $dispatcher = new EventDispatcher();
        $net = new StateMachine($definition, null, $dispatcher);

        $dispatcher->addListener('workflow.guard', function (GuardEvent $event) {
            $event->addTransitionBlocker(new TransitionBlocker(sprintf('Transition blocker of place %s', $event->getTransition()->getFroms()[0]), 'blocker'));
        });

        $subject = new Subject();

        // There may be multiple transitions with the same name. Make sure that transitions
        // that are enabled by the marking are evaluated.
        // see https://github.com/symfony/symfony/issues/28432

        // Test if when you are in place "a" and trying to apply "t1" then it returns
        // blocker list contains guard blocker instead blockedByMarking
        $subject->setMarking('a');
        $transitionBlockerList = $net->buildTransitionBlockerList($subject, 't1');
        $this->assertCount(1, $transitionBlockerList);
        $blockers = iterator_to_array($transitionBlockerList);
        $this->assertSame('Transition blocker of place a', $blockers[0]->getMessage());
        $this->assertSame('blocker', $blockers[0]->getCode());

        // Test if when you are in place "d" and trying to apply  "t1" then
        // it returns blocker list contains guard blocker instead blockedByMarking
        $subject->setMarking('d');
        $transitionBlockerList = $net->buildTransitionBlockerList($subject, 't1');
        $this->assertCount(1, $transitionBlockerList);
        $blockers = iterator_to_array($transitionBlockerList);
        $this->assertSame('Transition blocker of place d', $blockers[0]->getMessage());
        $this->assertSame('blocker', $blockers[0]->getCode());
    }

    public function testApplyReturnsExpectedReasonOnBranchMerge()
    {
        $definition = $this->createComplexStateMachineDefinition();

        $dispatcher = new EventDispatcher();
        $net = new StateMachine($definition, null, $dispatcher);

        $dispatcher->addListener('workflow.guard', function (GuardEvent $event) {
            $event->addTransitionBlocker(new TransitionBlocker(sprintf('Transition blocker of place %s', $event->getTransition()->getFroms()[0]), 'blocker'));
        });

        $subject = new Subject();

        // There may be multiple transitions with the same name. Make sure that all transitions
        // that are enabled by the marking are evaluated.
        // see https://github.com/symfony/symfony/issues/34489

        try {
            $net->apply($subject, 't1');
            $this->fail();
        } catch (NotEnabledTransitionException $e) {
            $blockers = iterator_to_array($e->getTransitionBlockerList());
            $this->assertSame('Transition blocker of place a', $blockers[0]->getMessage());
            $this->assertSame('blocker', $blockers[0]->getCode());
        }
    }
}
