<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\SmtpEnvelope;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class TransportTest extends TestCase
{
    /**
     * @dataProvider fromStringProvider
     */
    public function testFromString(string $dsn, TransportInterface $transport): void
    {
        $transportFactory = new Transport([new DummyTransportFactory()]);

        $this->assertEquals($transport, $transportFactory->fromString($dsn));
    }

    public function fromStringProvider(): iterable
    {
        $transportA = new DummyTransport('a');
        $transportB = new DummyTransport('b');

        yield 'simple transport' => [
            'dummy://a',
            $transportA,
        ];

        yield 'failover transport' => [
            'dummy://a || dummy://b',
            new Transport\FailoverTransport([$transportA, $transportB]),
        ];

        yield 'round robin transport' => [
            'dummy://a && dummy://b',
            new Transport\RoundRobinTransport([$transportA, $transportB]),
        ];
    }
}

class DummyTransport implements Transport\TransportInterface
{
    private $host;

    public function __construct(string $host)
    {
        $this->host = $host;
    }

    public function send(RawMessage $message, SmtpEnvelope $envelope = null): ?SentMessage
    {
        throw new \BadMethodCallException('This method newer should be called.');
    }
}

class DummyTransportFactory implements Transport\TransportFactoryInterface
{
    public function create(Dsn $dsn): TransportInterface
    {
        return new DummyTransport($dsn->getHost());
    }

    public function supports(Dsn $dsn): bool
    {
        return 'dummy' === $dsn->getScheme();
    }
}
