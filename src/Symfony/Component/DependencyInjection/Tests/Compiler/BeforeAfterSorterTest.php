<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\BeforeAfterSorter;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class BeforeAfterSorterTest extends TestCase
{
    public function testSeedOrderIsKeptWhenThereIsNoConstraint()
    {
        $this->assertSame(['a', 'b', 'c'], BeforeAfterSorter::sort(['a', 'b', 'c'], []));
    }

    public function testBeforeMovesTheConstrainedItemNotItsTarget()
    {
        $this->assertSame(['c', 'a', 'b'], BeforeAfterSorter::sort(['a', 'b', 'c'], [
            'c' => ['before' => ['a']],
        ]));
    }

    public function testAfterMovesTheConstrainedItemNotItsTarget()
    {
        $this->assertSame(['b', 'a', 'c'], BeforeAfterSorter::sort(['a', 'b', 'c'], [
            'a' => ['after' => ['b']],
        ]));
    }

    public function testAfterIsTheMirrorOfBefore()
    {
        $withBefore = BeforeAfterSorter::sort(['a', 'b', 'c'], ['c' => ['before' => ['a']]]);
        $withAfter = BeforeAfterSorter::sort(['a', 'b', 'c'], ['a' => ['after' => ['c']]]);

        $this->assertSame($withBefore, $withAfter);
        $this->assertSame(['c', 'a', 'b'], $withAfter);
    }

    public function testAnItemIsInsertedBetweenTwoOthers()
    {
        $this->assertSame(['b', 'e', 'c'], BeforeAfterSorter::sort(['b', 'c', 'e'], [
            'e' => ['after' => ['b'], 'before' => ['c']],
        ]));
    }

    public function testOnlyTheConstrainedItemMovesInALongerList()
    {
        $this->assertSame(['a', 'j', 'b', 'c', 'd', 'e'], BeforeAfterSorter::sort(['a', 'b', 'c', 'd', 'e', 'j'], [
            'j' => ['before' => ['b']],
        ]));
    }

    public function testConstraintsAreTransitive()
    {
        $this->assertSame(['c', 'b', 'a'], BeforeAfterSorter::sort(['a', 'b', 'c'], [
            'b' => ['before' => ['a']],
            'c' => ['before' => ['b']],
        ]));
    }

    public function testReferencesToUnknownItemsAreIgnored()
    {
        $this->assertSame(['a', 'b'], BeforeAfterSorter::sort(['a', 'b'], [
            'a' => ['before' => ['not_in_the_collection']],
            'b' => ['after' => ['gone_too']],
        ]));
    }

    public function testConstraintsOnUnknownItemsAreIgnored()
    {
        $this->assertSame(['a', 'b'], BeforeAfterSorter::sort(['a', 'b'], [
            'not_in_the_collection' => ['before' => ['a']],
        ]));
    }

    public function testMultipleTargetsAreSupported()
    {
        $this->assertSame(['c', 'a', 'b'], BeforeAfterSorter::sort(['a', 'b', 'c'], [
            'c' => ['before' => ['a', 'b']],
        ]));
    }

    public function testPrioritiesSeedTheOrderAndAMissingOneCountsAsZero()
    {
        $this->assertSame(['b' => 5, 'a' => 0, 'c' => -5], BeforeAfterSorter::sortWithPriorities(['a' => null, 'b' => 5, 'c' => -5], []));
    }

    public function testAnItemWithoutPriorityAdoptsTheOneItsPlacementNeeds()
    {
        $this->assertSame(['b' => 10, 'a' => 10, 'd' => 5], BeforeAfterSorter::sortWithPriorities(['a' => 10, 'b' => null, 'd' => 5], [
            'b' => ['before' => ['a']],
        ]));
        $this->assertSame(['a' => 10, 'd' => -5, 'b' => -5], BeforeAfterSorter::sortWithPriorities(['a' => 10, 'b' => null, 'd' => -5], [
            'b' => ['after' => ['d']],
        ]));
    }

    public function testAnItemWithoutPriorityStaysAtZeroWhenThatFits()
    {
        $this->assertSame(['a' => 10, 'b' => 0, 'd' => -5], BeforeAfterSorter::sortWithPriorities(['a' => 10, 'b' => null, 'd' => -5], [
            'b' => ['after' => ['a'], 'before' => ['d']],
        ]));
    }

    public function testAnItemWithoutPriorityBetweenTwoOthersTakesTheClosestToZero()
    {
        $this->assertSame(['a' => 100, 'b' => 50, 'd' => 50], BeforeAfterSorter::sortWithPriorities(['a' => 100, 'b' => null, 'd' => 50], [
            'b' => ['after' => ['a'], 'before' => ['d']],
        ]));
    }

    public function testAnItemWithoutPriorityCanBeMovedByTheConstraintOfAnother()
    {
        $this->assertSame(['b' => 10, 'a' => 10], BeforeAfterSorter::sortWithPriorities(['a' => 10, 'b' => null], [
            'a' => ['after' => ['b']],
        ]));
    }

    public function testExplicitPrioritiesAreNeverChanged()
    {
        $this->assertSame(['c' => 0, 'a' => 0, 'b' => 0], BeforeAfterSorter::sortWithPriorities(['a' => 0, 'b' => 0, 'c' => 0], [
            'c' => ['before' => ['a']],
        ]));
    }

    public function testAConstraintAlreadySatisfiedByPrioritiesChangesNothing()
    {
        $this->assertSame(['a' => 10, 'b' => 0], BeforeAfterSorter::sortWithPriorities(['a' => 10, 'b' => 0], [
            'a' => ['before' => ['b']],
        ]));
    }

    public function testABeforeConstraintContradictedByAnExplicitPriorityIsReported()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The priority of "b" (0) contradicts its "before" constraint on "a" (10): raise it to 10 or more, remove it, or drop the constraint.');

        BeforeAfterSorter::sortWithPriorities(['a' => 10, 'b' => 0], ['b' => ['before' => ['a']]]);
    }

    public function testAnAfterConstraintContradictedByAnExplicitPriorityIsReported()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The priority of "a" (10) contradicts its "after" constraint on "b" (0): lower it to 0 or less, remove it, or drop the constraint.');

        BeforeAfterSorter::sortWithPriorities(['a' => 10, 'b' => 0], ['a' => ['after' => ['b']]]);
    }

    public function testAContradictionThroughAnItemWithoutPriorityIsReported()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "before"/"after" constraints on "f" cannot be satisfied: it would need a priority of at least 100 to run before "a" and at most 50 to run after "b".');

        BeforeAfterSorter::sortWithPriorities(['a' => 100, 'b' => 50, 'f' => null], ['f' => ['after' => ['b'], 'before' => ['a']]]);
    }

    public function testAliasesAreResolvedForTheContradictionCheck()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The priority of "b" (0) contradicts its "before" constraint on "a" (10)');

        BeforeAfterSorter::sortWithPriorities(['a' => 10, 'b' => 0], ['b' => ['before' => ['Some\\Class']]], ['Some\\Class' => ['a']]);
    }

    public function testAnExplicitPriorityConstrainingAFreeItemMovesTheFreeOne()
    {
        $this->assertSame(['x' => 100, 'a' => -200, 'b' => -200], BeforeAfterSorter::sortWithPriorities(['x' => 100, 'b' => null, 'a' => -200], [
            'a' => ['before' => ['b']],
        ]));
    }

    public function testBoundsPropagateThroughFreeItems()
    {
        $this->assertSame(['f1' => 100, 'f2' => 100, 'a' => 100, 'z' => 0], BeforeAfterSorter::sortWithPriorities(['z' => null, 'f1' => null, 'f2' => null, 'a' => 100], [
            'f1' => ['before' => ['f2']],
            'f2' => ['before' => ['a']],
        ]));
    }

    public function testADirectCycleIsReported()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cycle detected in the "before"/"after" constraints: "a" -> "b" -> "a".');

        BeforeAfterSorter::sort(['a', 'b'], [
            'a' => ['before' => ['b']],
            'b' => ['before' => ['a']],
        ]);
    }

    public function testAnIndirectCycleIsReported()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cycle detected in the "before"/"after" constraints: "a" -> "c" -> "b" -> "a".');

        BeforeAfterSorter::sort(['a', 'b', 'c'], [
            'a' => ['before' => ['b']],
            'b' => ['before' => ['c']],
            'c' => ['before' => ['a']],
        ]);
    }

    public function testAnItemReferencingItselfIsIgnored()
    {
        $this->assertSame(['a', 'b'], BeforeAfterSorter::sort(['a', 'b'], ['a' => ['before' => ['a']]]));
    }

    public function testAnAliasDesignatesTheItemsItStandsFor()
    {
        $this->assertSame(['c', 'a', 'b'], BeforeAfterSorter::sort(['a', 'b', 'c'], [
            'c' => ['before' => ['Some\\Class']],
        ], ['Some\\Class' => ['a']]));
    }

    public function testAnAliasCanDesignateSeveralItems()
    {
        $this->assertSame(['c', 'a', 'b'], BeforeAfterSorter::sort(['a', 'b', 'c'], [
            'c' => ['before' => ['Some\\Class']],
        ], ['Some\\Class' => ['a', 'b']]));
    }

    public function testAnAliasResolvingToTheItemItselfIsIgnored()
    {
        $this->assertSame(['a', 'b'], BeforeAfterSorter::sort(['a', 'b'], [
            'a' => ['before' => ['Some\\Class']],
        ], ['Some\\Class' => ['a']]));
    }

    public function testConstraintsCanBeNamedByTheCaller()
    {
        $this->assertSame(['c', 'a', 'b'], BeforeAfterSorter::sort(['a', 'b', 'c'], ['c' => ['within' => ['a']]], [], 'within', 'around'));
        $this->assertSame(['b', 'a', 'c'], BeforeAfterSorter::sort(['a', 'b', 'c'], ['a' => ['around' => ['b']]], [], 'within', 'around'));
        $this->assertSame(['a', 'b', 'c'], BeforeAfterSorter::sort(['a', 'b', 'c'], ['c' => ['before' => ['a']]], [], 'within', 'around'));
    }

    #[DataProvider('provideErrorsWithNamedConstraints')]
    public function testErrorsUseTheNamesOfTheConstraints(string $expectedMessage, array $priorities, array $constraints)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        BeforeAfterSorter::sortWithPriorities($priorities, $constraints, [], 'within', 'around');
    }

    public static function provideErrorsWithNamedConstraints(): iterable
    {
        yield 'cycle' => ['Cycle detected in the "within"/"around" constraints: "a" -> "b" -> "a".', ['a' => null, 'b' => null], ['a' => ['within' => ['b']], 'b' => ['within' => ['a']]]];
        yield 'raise' => ['The priority of "b" (0) contradicts its "within" constraint on "a" (10): raise it to 10 or more, remove it, or drop the constraint.', ['a' => 10, 'b' => 0], ['b' => ['within' => ['a']]]];
        yield 'lower' => ['The priority of "a" (10) contradicts its "around" constraint on "b" (0): lower it to 0 or less, remove it, or drop the constraint.', ['a' => 10, 'b' => 0], ['a' => ['around' => ['b']]]];
        yield 'bounds' => ['The "within"/"around" constraints on "f" cannot be satisfied: it would need a priority of at least 100 to run within "a" and at most 50 to run around "b".', ['a' => 100, 'b' => 50, 'f' => null], ['f' => ['around' => ['b'], 'within' => ['a']]]];
    }
}
