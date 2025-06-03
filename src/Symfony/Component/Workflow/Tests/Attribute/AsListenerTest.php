<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Attribute;
use Symfony\Component\Workflow\Exception\LogicException;

class AsListenerTest extends TestCase
{
    /**
     * @dataProvider provideOkTests
     */
    public function testOk(string $class, string $expectedEvent, ?string $workflow = null, ?string $node = null)
    {
        $attribute = new $class($workflow, $node);

        $this->assertSame($expectedEvent, $attribute->event);
    }

    public static function provideOkTests(): iterable
    {
        yield [Attribute\AsAnnounceListener::class, 'workflow.announce'];
        yield [Attribute\AsAnnounceListener::class, 'workflow.w.announce', 'w'];
        yield [Attribute\AsAnnounceListener::class, 'workflow.w.announce.n', 'w', 'n'];

        yield [Attribute\AsCompletedListener::class, 'workflow.completed'];
        yield [Attribute\AsCompletedListener::class, 'workflow.w.completed', 'w'];
        yield [Attribute\AsCompletedListener::class, 'workflow.w.completed.n', 'w', 'n'];

        yield [Attribute\AsEnterListener::class, 'workflow.enter'];
        yield [Attribute\AsEnterListener::class, 'workflow.w.enter', 'w'];
        yield [Attribute\AsEnterListener::class, 'workflow.w.enter.n', 'w', 'n'];

        yield [Attribute\AsEnteredListener::class, 'workflow.entered'];
        yield [Attribute\AsEnteredListener::class, 'workflow.w.entered', 'w'];
        yield [Attribute\AsEnteredListener::class, 'workflow.w.entered.n', 'w', 'n'];

        yield [Attribute\AsGuardListener::class, 'workflow.guard'];
        yield [Attribute\AsGuardListener::class, 'workflow.w.guard', 'w'];
        yield [Attribute\AsGuardListener::class, 'workflow.w.guard.n', 'w', 'n'];

        yield [Attribute\AsLeaveListener::class, 'workflow.leave'];
        yield [Attribute\AsLeaveListener::class, 'workflow.w.leave', 'w'];
        yield [Attribute\AsLeaveListener::class, 'workflow.w.leave.n', 'w', 'n'];

        yield [Attribute\AsTransitionListener::class, 'workflow.transition'];
        yield [Attribute\AsTransitionListener::class, 'workflow.w.transition', 'w'];
        yield [Attribute\AsTransitionListener::class, 'workflow.w.transition.n', 'w', 'n'];
    }

    /**
     * @dataProvider provideTransitionThrowException
     */
    public function testTransitionThrowException(string $class)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('The "transition" argument of "%s" cannot be used without a "workflow" argument.', $class));

        new $class(transition: 'some');
    }

    public static function provideTransitionThrowException(): iterable
    {
        yield [Attribute\AsAnnounceListener::class, 'workflow.announce'];
        yield [Attribute\AsCompletedListener::class, 'workflow.completed'];
        yield [Attribute\AsGuardListener::class, 'workflow.guard'];
        yield [Attribute\AsTransitionListener::class, 'workflow.transition'];
    }

    /**
     * @dataProvider providePlaceThrowException
     */
    public function testPlaceThrowException(string $class)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('The "place" argument of "%s" cannot be used without a "workflow" argument.', $class));

        new $class(place: 'some');
    }

    public static function providePlaceThrowException(): iterable
    {
        yield [Attribute\AsEnteredListener::class, 'workflow.entered'];
        yield [Attribute\AsEnterListener::class, 'workflow.enter'];
        yield [Attribute\AsLeaveListener::class, 'workflow.leave'];
    }
}
