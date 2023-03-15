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
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\FailoverTransport;
use Symfony\Component\Mailer\Transport\RoundRobinTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class TransportTest extends TestCase
{
    /**
     * @dataProvider fromStringProvider
     */
    public function testFromString(string $dsn, TransportInterface $transport)
    {
        $transportFactory = new Transport([new DummyTransportFactory()]);

        $this->assertEquals($transport, $transportFactory->fromString($dsn));
    }

    public static function fromStringProvider(): iterable
    {
        $transportA = new DummyTransport('a');
        $transportB = new DummyTransport('b');

        yield 'simple transport' => [
            'dummy://a',
            $transportA,
        ];

        yield 'failover transport' => [
            'failover(dummy://a dummy://b)',
            new FailoverTransport([$transportA, $transportB]),
        ];

        yield 'round robin transport' => [
            'roundrobin(dummy://a dummy://b)',
            new RoundRobinTransport([$transportA, $transportB]),
        ];

        yield 'mixed transport' => [
            'roundrobin(dummy://a failover(dummy://b dummy://a) dummy://b)',
            new RoundRobinTransport([$transportA, new FailoverTransport([$transportB, $transportA]), $transportB]),
        ];
    }

    /**
     * @dataProvider fromDsnProvider
     */
    public function testFromDsn(string $dsn, TransportInterface $transport)
    {
        $this->assertEquals($transport, Transport::fromDsn($dsn));
    }

    public static function fromDsnProvider(): iterable
    {
        yield 'multiple transports' => [
            'failover(smtp://a smtp://b)',
            new FailoverTransport([new Transport\Smtp\EsmtpTransport('a'), new Transport\Smtp\EsmtpTransport('b')]),
        ];
    }

    /**
     * @dataProvider fromWrongStringProvider
     */
    public function testFromWrongString(string $dsn, string $error)
    {
        $transportFactory = new Transport([new DummyTransportFactory()]);

        $this->expectExceptionMessage($error);
        $this->expectException(InvalidArgumentException::class);
        $transportFactory->fromString($dsn);
    }

    public static function fromWrongStringProvider(): iterable
    {
        yield 'garbage at the end' => ['dummy://a some garbage here', 'The mailer DSN has some garbage at the end.'];

        yield 'not a valid DSN' => ['something not a dsn', 'The mailer DSN must contain a scheme.'];

        yield 'failover not closed' => ['failover(dummy://a', 'The mailer DSN must contain a scheme.'];

        yield 'not a valid keyword' => ['foobar(dummy://a)', 'The "foobar" keyword is not valid (valid ones are "failover", "roundrobin")'];
    }
}

class DummyTransport implements Transport\TransportInterface
{
    private $host;

    public function __construct(string $host)
    {
        $this->host = $host;
    }

    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        throw new \BadMethodCallException('This method newer should be called.');
    }

    public function __toString(): string
    {
        return 'dummy://local';
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
