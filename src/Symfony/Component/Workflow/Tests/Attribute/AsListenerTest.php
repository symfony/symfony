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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Attribute;

class AsListenerTest extends TestCase
{
    #[DataProvider('provideOkTests')]
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

    public function testBeforeAndAfterReachTheParentAttribute()
    {
        $attribute = new Attribute\AsEnterListener('w', 'n', before: 'app.first', after: [\stdClass::class]);

        $this->assertSame('workflow.w.enter.n', $attribute->event);
        $this->assertNull($attribute->priority);
        $this->assertSame('app.first', $attribute->before);
        $this->assertSame([\stdClass::class], $attribute->after);
    }

    #[DataProvider('provideTransitionWithoutWorkflow')]
    public function testTransitionWithoutWorkflowUsesAPlaceholder(string $class, string $keyword)
    {
        $attribute = new $class(transition: 'some');

        $this->assertSame(\sprintf('workflow.%s.%s.some', Attribute\AsWorkflow::NAME_PLACEHOLDER, $keyword), $attribute->event);
    }

    public static function provideTransitionWithoutWorkflow(): iterable
    {
        yield [Attribute\AsAnnounceListener::class, 'announce'];
        yield [Attribute\AsCompletedListener::class, 'completed'];
        yield [Attribute\AsGuardListener::class, 'guard'];
        yield [Attribute\AsTransitionListener::class, 'transition'];
    }

    #[DataProvider('providePlaceWithoutWorkflow')]
    public function testPlaceWithoutWorkflowUsesAPlaceholder(string $class, string $keyword)
    {
        $attribute = new $class(place: 'some');

        $this->assertSame(\sprintf('workflow.%s.%s.some', Attribute\AsWorkflow::NAME_PLACEHOLDER, $keyword), $attribute->event);
    }

    public static function providePlaceWithoutWorkflow(): iterable
    {
        yield [Attribute\AsEnteredListener::class, 'entered'];
        yield [Attribute\AsEnterListener::class, 'enter'];
        yield [Attribute\AsLeaveListener::class, 'leave'];
    }
}
