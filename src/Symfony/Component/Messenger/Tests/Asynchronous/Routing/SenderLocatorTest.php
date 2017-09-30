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
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Asynchronous\Routing\SenderLocator;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\SenderInterface;

class SenderLocatorTest extends TestCase
{
    public function testItReturnsTheSenderBasedOnTheMessageClass()
    {
        $sender = $this->createMock(SenderInterface::class);
        $container = new Container();
        $container->set('my_amqp_sender', $sender);

        $locator = new SenderLocator($container, [
            DummyMessage::class => [
                'my_amqp_sender',
            ]
        ]);

        $this->assertEquals([$sender], $locator->getSendersForMessage(new DummyMessage('Hello')));
        $this->assertEquals([], $locator->getSendersForMessage(new SecondMessage()));
    }

    public function testItSupportsAWildcardInsteadOfTheMessageClass()
    {
        $container = new Container();

        $sender = $this->createMock(SenderInterface::class);
        $container->set('my_amqp_sender', $sender);

        $apiSender = $this->createMock(SenderInterface::class);
        $container->set('my_api_sender', $apiSender);

        $locator = new SenderLocator($container, [
            DummyMessage::class => [
                'my_amqp_sender',
            ],
            '*' => [
                'my_api_sender'
            ]
        ]);

        $this->assertEquals([$sender], $locator->getSendersForMessage(new DummyMessage('Hello')));
        $this->assertEquals([$apiSender], $locator->getSendersForMessage(new SecondMessage()));
    }
}
