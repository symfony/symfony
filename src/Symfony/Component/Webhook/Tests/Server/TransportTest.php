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
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Server\RequestConfiguratorInterface;
use Symfony\Component\Webhook\Server\Transport;
use Symfony\Component\Webhook\Subscriber;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TransportTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    public function testCanBeInstantiated()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://example.com/webhook',
                $this->isType('array')
            );

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->expects($this->once())->method('getSecret')->willReturn('some-secret');
        $subscriber->expects($this->once())->method('getUrl')->willReturn('https://example.com/webhook');

        $event = $this->createMock(RemoteEvent::class);

        $configuratorOne = $this->createMock(RequestConfiguratorInterface::class);
        $configuratorOne->expects($this->once())
            ->method('configure')
            ->with($event, 'some-secret', $this->isInstanceOf(HttpOptions::class));

        $configuratorTwo = $this->createMock(RequestConfiguratorInterface::class);
        $configuratorTwo->expects($this->once())
            ->method('configure')
            ->with($event, 'some-secret', $this->isInstanceOf(HttpOptions::class));

        $transport = new Transport($client, new \ArrayIterator([
            $configuratorOne,
            $configuratorTwo,
        ]));

        $transport->send($subscriber, $event);
    }

    /**
     * @group legacy
     */
    public function testCanBeInstantiatedWithDeprecatedOptions()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://example.com/webhook',
                $this->isType('array')
            );

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->expects($this->once())->method('getSecret')->willReturn('some-secret');
        $subscriber->expects($this->once())->method('getUrl')->willReturn('https://example.com/webhook');

        $event = $this->createMock(RemoteEvent::class);

        $headerConfigurator = $this->createMock(RequestConfiguratorInterface::class);
        $headerConfigurator->expects($this->once())
            ->method('configure')
            ->with($event, 'some-secret', $this->isInstanceOf(HttpOptions::class));

        $bodyConfigurator = $this->createMock(RequestConfiguratorInterface::class);
        $bodyConfigurator->expects($this->once())
            ->method('configure')
            ->with($event, 'some-secret', $this->isInstanceOf(HttpOptions::class));

        $signerConfigurator = $this->createMock(RequestConfiguratorInterface::class);
        $signerConfigurator->expects($this->once())
            ->method('configure')
            ->with($event, 'some-secret', $this->isInstanceOf(HttpOptions::class));

        $event = $this->createMock(RemoteEvent::class);

        $this->expectUserDeprecationMessage('Since symfony/webhook 7.3: Individual configurators for webhook transport is deprecated, use an iterable instead.');

        $transport = new Transport($client, $headerConfigurator, $bodyConfigurator, $signerConfigurator);
        $transport->send($subscriber, $event);
    }
}
