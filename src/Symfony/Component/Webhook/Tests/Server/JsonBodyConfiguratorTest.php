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
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Webhook\Server\JsonBodyConfigurator;
use Symfony\Component\Webhook\Server\PayloadSerializerInterface;

class JsonBodyConfiguratorTest extends TestCase
{
    public function testPayloadWithPayloadSerializer()
    {
        $payload = ['foo' => 'bar'];

        $payloadSerializer = $this->createMock(PayloadSerializerInterface::class);
        $payloadSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($payload)
        ;

        $httpOptions = new HttpOptions();
        $httpOptions->setHeaders([
            'Webhook-Event' => 'event-name',
            'Webhook-Id' => 'event-id',
        ]);

        $configurator = new JsonBodyConfigurator($payloadSerializer);
        $configurator->configure(new RemoteEvent('event-name', 'event-id', $payload), 's3cr3t', $httpOptions);
    }

    public function testPayloadWithSerializer()
    {
        $payload = ['foo' => 'bar'];

        $payloadEncoder = $this->createMock(SerializerInterface::class);
        $payloadEncoder
            ->expects($this->once())
            ->method('serialize')
            ->with($payload, 'json')
            ->willReturn('{"foo": "bar"}')
        ;

        $httpOptions = new HttpOptions();
        $httpOptions->setHeaders([
            'Webhook-Event' => 'event-name',
            'Webhook-Id' => 'event-id',
        ]);

        $configurator = new JsonBodyConfigurator($payloadEncoder);
        $configurator->configure(new RemoteEvent('event-name', 'event-id', $payload), 's3cr3t', $httpOptions);

        $this->assertJsonStringEqualsJsonString('{"foo": "bar"}', $httpOptions->toArray()['body']);
    }
}
