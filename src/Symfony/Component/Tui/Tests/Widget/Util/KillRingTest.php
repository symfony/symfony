<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Widget\Util\KillRing;

class KillRingTest extends TestCase
{
    public function testAddAndPeek()
    {
        $ring = new KillRing();
        $ring->add('hello', false);

        $this->assertSame('hello', $ring->peek());
    }

    public function testAddEmptyStringIsIgnored()
    {
        $ring = new KillRing();
        $ring->add('', false);

        $this->assertNull($ring->peek());
    }

    public function testConsecutiveKillsAppend()
    {
        $ring = new KillRing();
        $ring->add('hello', false);
        $ring->add(' world', false);

        $this->assertSame('hello world', $ring->peek());
    }

    public function testConsecutiveKillsPrepend()
    {
        $ring = new KillRing();
        $ring->add('world', true);
        $ring->add('hello ', true);

        $this->assertSame('hello world', $ring->peek());
    }

    public function testNonConsecutiveKillsCreateNewEntries()
    {
        $ring = new KillRing();
        $ring->add('first', false);
        $ring->resetAction();
        $ring->add('second', false);

        $this->assertSame('second', $ring->peek());
    }

    public function testMaxEntries()
    {
        $ring = new KillRing(3);
        $ring->add('a', false);
        $ring->resetAction();
        $ring->add('b', false);
        $ring->resetAction();
        $ring->add('c', false);
        $ring->resetAction();
        $ring->add('d', false);

        // 'a' should have been evicted
        $this->assertSame('d', $ring->peek());

        // Rotate through: should only have b, c, d
        $ring->resetAction();
        $ring->recordYank(['start_line' => 0, 'start_col' => 0, 'end_line' => 0, 'end_col' => 1]);
        $this->assertTrue($ring->canYankPop());
        $text = $ring->rotate();
        $this->assertSame('c', $text);
    }

    public function testCanYankPopRequiresYankAction()
    {
        $ring = new KillRing();
        $ring->add('a', false);
        $ring->resetAction();
        $ring->add('b', false);

        $this->assertFalse($ring->canYankPop());
    }

    public function testCanYankPopRequiresMultipleEntries()
    {
        $ring = new KillRing();
        $ring->add('only', false);
        $ring->recordYank(['start_line' => 0, 'start_col' => 0, 'end_line' => 0, 'end_col' => 4]);

        $this->assertFalse($ring->canYankPop());
    }

    public function testCanYankPopRequiresYankRange()
    {
        $ring = new KillRing();
        $ring->add('a', false);
        $ring->resetAction();
        $ring->add('b', false);
        // Manually set last action to yank without recording range
        // This shouldn't happen in practice, but canYankPop checks for it

        $this->assertFalse($ring->canYankPop());
    }

    public function testYankPopCycle()
    {
        $ring = new KillRing();
        $ring->add('first', false);
        $ring->resetAction();
        $ring->add('second', false);
        $ring->resetAction();
        $ring->add('third', false);

        // Yank gets 'third'
        $this->assertSame('third', $ring->peek());
        $ring->recordYank(['start_line' => 0, 'start_col' => 0, 'end_line' => 0, 'end_col' => 5]);

        // Yank-pop rotates: third goes to front, 'second' is now on top
        $this->assertTrue($ring->canYankPop());
        $text = $ring->rotate();
        $this->assertSame('second', $text);
    }

    public function testRotateWithSingleEntry()
    {
        $ring = new KillRing();
        $ring->add('only', false);

        $this->assertNull($ring->rotate());
    }

    public function testResetAction()
    {
        $ring = new KillRing();
        $ring->add('a', false);
        $ring->resetAction();
        $ring->add('b', false);

        // After reset, 'b' is a new entry, not appended to 'a'
        $this->assertSame('b', $ring->peek());
    }

    public function testResetAll()
    {
        $ring = new KillRing();
        $ring->add('a', false);
        $ring->resetAction();
        $ring->add('b', false);
        $ring->recordYank(['start_line' => 0, 'start_col' => 0, 'end_line' => 0, 'end_col' => 1]);

        $ring->resetAll();

        $this->assertFalse($ring->canYankPop());
        $this->assertNull($ring->getLastYankRange());
    }

    public function testGetLastYankRange()
    {
        $ring = new KillRing();
        $this->assertNull($ring->getLastYankRange());

        $range = ['start_line' => 1, 'start_col' => 5, 'end_line' => 2, 'end_col' => 3];
        $ring->recordYank($range);

        $this->assertSame($range, $ring->getLastYankRange());
    }
}
