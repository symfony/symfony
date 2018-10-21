<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Asynchronous\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Asynchronous\Routing\SenderLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\SenderInterface;

class SenderLocatorTest extends TestCase
{
    public function testItReturnsTheSenderBasedOnTheMessageClass()
    {
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $locator = new SenderLocator(array(
            DummyMessage::class => $sender,
        ));

        $this->assertSame($sender, $locator->getSender(new Envelope(new DummyMessage('Hello'))));
        $this->assertNull($locator->getSender(new Envelope(new SecondMessage())));
    }

    public function testItThrowsExceptionIfConfigurationIsWrong()
    {
        $locator = new SenderLocator(array(
            DummyMessage::class => 'amqp',
        ));

        $this->expectException(RuntimeException::class);
        $locator->getSender(new Envelope(new DummyMessage('Hello')));
    }
}
