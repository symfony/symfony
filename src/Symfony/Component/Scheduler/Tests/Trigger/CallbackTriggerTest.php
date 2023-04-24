<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Trigger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Trigger\CallbackTrigger;

class CallbackTriggerTest extends TestCase
{
    public function testToString()
    {
        $trigger = new CallbackTrigger(fn () => null);
        $this->assertMatchesRegularExpression('/^\d{32}$/', (string) $trigger);

        $trigger = new CallbackTrigger(fn () => null, '');
        $this->assertSame('', (string) $trigger);

        $trigger = new CallbackTrigger(fn () => null, 'foo');
        $this->assertSame('foo', (string) $trigger);
    }
}
