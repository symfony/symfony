<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

class RedeliveryStampTest extends TestCase
{
    public function testShouldRedeliverToSenderWithAlias()
    {
        $stamp = new RedeliveryStamp(5, 'foo_alias');

        $this->assertFalse($stamp->shouldRedeliverToSender('Foo\Bar\Sender', 'bar_alias'));
        $this->assertTrue($stamp->shouldRedeliverToSender('Foo\Bar\Sender', 'foo_alias'));
    }

    public function testShouldRedeliverToSenderWithoutAlias()
    {
        $stampToRedeliverToSender1 = new RedeliveryStamp(5, 'App\Sender1');

        $this->assertTrue($stampToRedeliverToSender1->shouldRedeliverToSender('App\Sender1', null));
        $this->assertFalse($stampToRedeliverToSender1->shouldRedeliverToSender('App\Sender2', null));
    }
}
