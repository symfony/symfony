<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Tests\Server;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Event\WebhookSentEvent;
use Symfony\Component\Webhook\Server\RequestConfiguratorInterface;
use Symfony\Component\Webhook\Server\Transport;
use Symfony\Component\Webhook\Subscriber;
use Symfony\Contracts\EventDispatcher\Event;

class TransportTest extends TestCase
{
    public function testSendWithDispatcher(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(WebhookSentEvent::class, $listener = new DebugEventListener());

        $transport = new Transport(
            new MockHttpClient(function (string $method, string $url) {
                self::assertSame('POST', $method);
                self::assertSame('https://www.acme.org/webhook', $url);

                return new MockResponse('OK');
            }),
            new NullRequestConfigurator(),
            new NullRequestConfigurator(),
            new NullRequestConfigurator(),
            $eventDispatcher,
        );

        $transport->send(
            $subscriber = new Subscriber('https://www.acme.org/webhook', '$ecret'),
            $event = new RemoteEvent('user.created', 'f4afdfd6-32cb-4b72-8a56-4498bada91e0', ['name' => 'John']),
        );

        $eventReceivedInListener = $listener->event;
        self::assertInstanceOf(WebhookSentEvent::class, $eventReceivedInListener);
        self::assertSame($subscriber, $eventReceivedInListener->getSubscriber());
        self::assertSame($event, $eventReceivedInListener->getEvent());
        self::assertSame('OK', $eventReceivedInListener->getResponse()->getContent());
    }

    public function testSendWithoutDispatcher(): void
    {
        $transport = new Transport(
            new MockHttpClient(function (string $method, string $url) {
                self::assertSame('POST', $method);
                self::assertSame('https://www.acme.org/webhook', $url);

                return new MockResponse('OK');
            }),
            new NullRequestConfigurator(),
            new NullRequestConfigurator(),
            new NullRequestConfigurator(),
        );

        $transport->send(
            new Subscriber('https://www.acme.org/webhook', '$ecret'),
            new RemoteEvent('user.created', 'f4afdfd6-32cb-4b72-8a56-4498bada91e0', ['name' => 'John']),
        );
    }
}

class NullRequestConfigurator implements RequestConfiguratorInterface
{
    public function configure(RemoteEvent $event, #[\SensitiveParameter] string $secret, HttpOptions $options): void
    {
    }
}

class DebugEventListener
{
    public ?Event $event = null;

    public function __invoke(Event $event): void
    {
        $this->event = $event;
    }
}
