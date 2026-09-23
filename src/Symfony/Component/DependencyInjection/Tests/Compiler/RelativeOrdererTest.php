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
use Symfony\Component\DependencyInjection\Compiler\RelativeOrderer;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class RelativeOrdererTest extends TestCase
{
    public function testEmptyAndUnconstrainedOrders()
    {
        $this->assertSame([], RelativeOrderer::sort([]));
        $this->assertSame([0, 1], RelativeOrderer::sort([
            ['id' => 'first', 'before' => [], 'after' => []],
            ['id' => 'second', 'before' => [], 'after' => []],
        ]));
    }

    public function testBeforeAndAfterOverrideFallbackOrder()
    {
        $this->assertSame([1, 2, 0, 3], RelativeOrderer::sort([
            ['id' => 'first', 'before' => [], 'after' => []],
            ['id' => 'unrelated', 'before' => [], 'after' => []],
            ['id' => 'second', 'before' => 'first', 'after' => []],
            ['id' => 'third', 'before' => [], 'after' => ['second', 'first', 'second']],
        ]));
    }

    public function testMissingTargetPreservesFallbackOrder()
    {
        $this->assertSame([0, 1], RelativeOrderer::sort([
            ['id' => 'first', 'before' => 'missing', 'after' => []],
            ['id' => 'second', 'before' => [], 'after' => ['missing']],
        ]));
    }

    public function testNumericStringServiceIdIsOrdered()
    {
        $this->assertSame([1, 0], RelativeOrderer::sort([
            ['id' => '0', 'before' => [], 'after' => []],
            ['id' => 'second', 'before' => '0', 'after' => []],
        ]));
    }

    public function testAllEntriesForTargetServiceAreOrdered()
    {
        $this->assertSame([2, 0, 1], RelativeOrderer::sort([
            ['id' => 'first', 'before' => [], 'after' => []],
            ['id' => 'first', 'before' => [], 'after' => []],
            ['id' => 'second', 'before' => 'first', 'after' => []],
        ]));
    }

    public function testCycleIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Circular "before" or "after" ordering detected.');

        RelativeOrderer::sort([
            ['id' => 'first', 'before' => 'second', 'after' => []],
            ['id' => 'second', 'before' => 'first', 'after' => []],
        ]);
    }

    public function testInvalidTargetIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "before" constraint of item "first" must contain ids.');

        RelativeOrderer::sort([['id' => 'first', 'before' => [42], 'after' => []]]);
    }
}
