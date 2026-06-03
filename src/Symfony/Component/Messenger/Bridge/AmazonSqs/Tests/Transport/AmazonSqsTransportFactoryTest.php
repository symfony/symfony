<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransportFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;

class AmazonSqsTransportFactoryTest extends TestCase
{
    public function testSupportsOnlySqsTransports()
    {
        $factory = new AmazonSqsTransportFactory();

        $this->assertTrue($factory->supports('sqs://localhost', []));
        $this->assertTrue($factory->supports('https://sqs.us-east-2.amazonaws.com/123456789012/ab1-MyQueue-A2BCDEF3GHI4', []));
        $this->assertFalse($factory->supports('redis://localhost', []));
        $this->assertFalse($factory->supports('invalid-dsn', []));
    }

    public function testCustomHttpClient()
    {
        $httpClient = new MockHttpClient();
        $factory = new AmazonSqsTransportFactory(null, $httpClient);

        $transport = $factory->createTransport('https://sqs.us-east-2.amazonaws.com/1111/messages?access_key=KEY&secret_key=SECRET', [], Serializer::create());

        self::assertSame(0, $httpClient->getRequestsCount());
        $transport->send(new Envelope(new \stdClass(), []));

        // 1 query to check queueExists, 1 query to sendMessage
        self::assertSame(2, $httpClient->getRequestsCount());
    }
}
