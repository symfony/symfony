<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\State;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\State\State;

class StateTest extends TestCase
{
    public function testState()
    {
        $now = new \DateTimeImmutable('2020-02-20 20:20:20Z');
        $later = $now->modify('1 hour');
        $state = new State();

        $this->assertTrue($state->acquire($now));
        $this->assertSame($now, $state->time());
        $this->assertSame(-1, $state->index());

        $state->save($later, 7);

        $this->assertSame($later, $state->time());
        $this->assertSame(7, $state->index());

        $state->release($later, null);
    }
}
