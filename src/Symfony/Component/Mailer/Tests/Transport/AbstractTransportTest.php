<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * @group time-sensitive
 */
class AbstractTransportTest extends TestCase
{
    public function testThrottling()
    {
        $transport = new NullTransport();
        $transport->setMaxPerSecond(2 / 10);
        $message = new RawMessage('');
        $envelope = new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]);

        $start = time();
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(0, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(5, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(10, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(15, time() - $start, 1);

        $start = time();
        $transport->setMaxPerSecond(-3);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(0, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(0, time() - $start, 1);
    }

    public function testSendingRawMessages()
    {
        $this->expectException(LogicException::class);

        $transport = new NullTransport();
        $transport->send(new RawMessage('Some raw email message'));
    }
}
