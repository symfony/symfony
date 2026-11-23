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
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\SmsProxima\SmsProximaTransport;
use Symfony\Component\Notifier\Bridge\SmsProxima\SmsProximaTransportFactory;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\Dsn;

class SmsProximaTransportFactoryTest extends TestCase
{
    public function testCreateWithValidDsn()
    {
        $factory = new SmsProximaTransportFactory();
        $transport = $factory->create(new Dsn('sms-proxima://my-token@default?from=BOUTIQUE'));

        $this->assertInstanceOf(SmsProximaTransport::class, $transport);
        $this->assertSame('sms-proxima://sms-proxima.com?from=BOUTIQUE', (string) $transport);
    }

    public function testCreateWithCustomHost()
    {
        $factory = new SmsProximaTransportFactory();
        $transport = $factory->create(new Dsn('sms-proxima://my-token@example.com:8080?from=BOUTIQUE'));

        $this->assertSame('sms-proxima://example.com:8080?from=BOUTIQUE', (string) $transport);
    }

    public function testSendWithDefaultHostTargetsSmsProximaApi()
    {
        $client = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://sms-proxima.com/api/sms/send', $url);

            return new MockResponse(json_encode(['ticket' => 'abc123']), ['http_code' => 200]);
        });

        $factory = new SmsProximaTransportFactory(null, $client);
        $transport = $factory->create(new Dsn('sms-proxima://my-token@default?from=BOUTIQUE'));

        $sentMessage = $transport->send(new SmsMessage('+33612345678', 'Hello'));

        $this->assertSame('abc123', $sentMessage->getMessageId());
    }

    public function testCreateThrowsOnUnsupportedScheme()
    {
        $this->expectException(UnsupportedSchemeException::class);

        $factory = new SmsProximaTransportFactory();
        $factory->create(new Dsn('other-scheme://token@default'));
    }
}
