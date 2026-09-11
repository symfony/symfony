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
}
