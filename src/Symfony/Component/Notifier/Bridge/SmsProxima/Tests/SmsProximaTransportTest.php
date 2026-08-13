<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsProxima\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\SmsProxima\SmsProximaOptions;
use Symfony\Component\Notifier\Bridge\SmsProxima\SmsProximaTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;

class SmsProximaTransportTest extends TestCase
{
    public function testToString()
    {
        $transport = new SmsProximaTransport('my-token', 'BOUTIQUE');

        $this->assertSame('sms-proxima://sms-proxima.com?from=BOUTIQUE', (string) $transport);
    }

    public function testSupportsOnlySmsMessage()
    {
        $transport = new SmsProximaTransport('my-token', 'BOUTIQUE');

        $this->assertTrue($transport->supports(new SmsMessage('+33612345678', 'Hello')));
        $this->assertFalse($transport->supports(new ChatMessage('Hello')));
    }

    public function testOptionsToArray()
    {
        $options = (new SmsProximaOptions())
            ->sandbox(true)
            ->stop(false)
            ->idempotencyKey('uuid-1234')
            ->timeToSend('2026-12-25 10:00');

        $this->assertSame([
            'sandbox' => 1,
            'stop' => 0,
            'idempotencyKey' => 'uuid-1234',
            'timeToSend' => '2026-12-25 10:00',
        ], $options->toArray());
    }

    public function testOptionsGetRecipientIdReturnsNull()
    {
        $this->assertNull((new SmsProximaOptions())->getRecipientId());
    }
}
