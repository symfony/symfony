<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Sender;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

class SendersLocatorTest extends TestCase
{
    public function testItReturnsTheSenderBasedOnTheMessageClass()
    {
        $sender = self::createMock(SenderInterface::class);
        $sendersLocator = $this->createContainer([
            'my_sender' => $sender,
        ]);
        $locator = new SendersLocator([
            DummyMessage::class => ['my_sender'],
        ], $sendersLocator);

        self::assertSame(['my_sender' => $sender], iterator_to_array($locator->getSenders(new Envelope(new DummyMessage('a')))));
        self::assertSame([], iterator_to_array($locator->getSenders(new Envelope(new SecondMessage()))));
    }

    private function createContainer(array $senders): ContainerInterface
    {
        $container = self::createMock(ContainerInterface::class);
        $container->expects(self::any())
            ->method('has')
            ->willReturnCallback(function ($id) use ($senders) {
                return isset($senders[$id]);
            });
        $container->expects(self::any())
            ->method('get')
            ->willReturnCallback(function ($id) use ($senders) {
                return $senders[$id];
            });

        return $container;
    }
}
