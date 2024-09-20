<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Event\AnnounceEvent;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\LeaveEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;

class EventNameTraitTest extends TestCase
{
    /**
     * @dataProvider getEvents
     *
     * @param class-string $class
     */
    public function testEventNames(string $class, ?string $workflowName, ?string $transitionOrPlaceName, string $expected)
    {
        $name = $class::getName($workflowName, $transitionOrPlaceName);
        $this->assertEquals($expected, $name);
    }

    public static function getEvents(): iterable
    {
        yield [AnnounceEvent::class, null, null, 'workflow.announce'];
        yield [AnnounceEvent::class, 'post', null, 'workflow.post.announce'];
        yield [AnnounceEvent::class, 'post', 'publish', 'workflow.post.announce.publish'];

        yield [CompletedEvent::class, null, null, 'workflow.completed'];
        yield [CompletedEvent::class, 'post', null, 'workflow.post.completed'];
        yield [CompletedEvent::class, 'post', 'publish', 'workflow.post.completed.publish'];

        yield [EnteredEvent::class, null, null, 'workflow.entered'];
        yield [EnteredEvent::class, 'post', null, 'workflow.post.entered'];
        yield [EnteredEvent::class, 'post', 'published', 'workflow.post.entered.published'];

        yield [EnterEvent::class, null, null, 'workflow.enter'];
        yield [EnterEvent::class, 'post', null, 'workflow.post.enter'];
        yield [EnterEvent::class, 'post', 'published', 'workflow.post.enter.published'];

        yield [GuardEvent::class, null, null, 'workflow.guard'];
        yield [GuardEvent::class, 'post', null, 'workflow.post.guard'];
        yield [GuardEvent::class, 'post', 'publish', 'workflow.post.guard.publish'];

        yield [LeaveEvent::class, null, null, 'workflow.leave'];
        yield [LeaveEvent::class, 'post', null, 'workflow.post.leave'];
        yield [LeaveEvent::class, 'post', 'published', 'workflow.post.leave.published'];

        yield [TransitionEvent::class, null, null, 'workflow.transition'];
        yield [TransitionEvent::class, 'post', null, 'workflow.post.transition'];
        yield [TransitionEvent::class, 'post', 'publish', 'workflow.post.transition.publish'];
    }

    public function testInvalidArgumentExceptionIsThrownIfWorkflowNameIsMissing()
    {
        $this->expectException(\InvalidArgumentException::class);

        EnterEvent::getName(null, 'place');
    }
}
