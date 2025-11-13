<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport\Smtp;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class EsmtpTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new EsmtpTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('smtp', 'example.com'),
            true,
        ];

        yield [
            new Dsn('smtps', 'example.com'),
            true,
        ];

        yield [
            new Dsn('api', 'example.com'),
            false,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        $transport = new EsmtpTransport('localhost', 25, false, null, $logger);

        yield [
            new Dsn('smtp', 'localhost'),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 99, true, null, $logger);
        $transport->setUsername(self::USER);
        $transport->setPassword(self::PASSWORD);

        yield [
            new Dsn('smtps', 'example.com', self::USER, self::PASSWORD, 99),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 465, true, null, $logger);

        yield [
            new Dsn('smtps', 'example.com'),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 465, true, null, $logger);

        yield [
            new Dsn('smtps', 'example.com', '', '', 465),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 465, true, null, $logger);
        /** @var SocketStream $stream */
        $stream = $transport->getStream();
        $streamOptions = $stream->getStreamOptions();
        $streamOptions['ssl']['verify_peer'] = false;
        $streamOptions['ssl']['verify_peer_name'] = false;
        $stream->setStreamOptions($streamOptions);

        yield [
            new Dsn('smtps', 'example.com', '', '', 465, ['verify_peer' => false]),
            $transport,
        ];

        yield [
            new Dsn('smtps', 'example.com', '', '', 465, ['verify_peer' => 'false']),
            $transport,
        ];

        yield [
            Dsn::fromString('smtps://:@example.com?verify_peer=0'),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 465, true, null, $logger);

        yield [
            Dsn::fromString('smtps://:@example.com?verify_peer='),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 465, true, null, $logger);
        /** @var SocketStream $stream */
        $stream = $transport->getStream();
        $streamOptions = $stream->getStreamOptions();
        $streamOptions['ssl']['peer_fingerprint'] = '6A1CF3B08D175A284C30BC10DE19162307C7286E';
        $stream->setStreamOptions($streamOptions);

        yield [
            new Dsn('smtps', 'example.com', '', '', 465, ['peer_fingerprint' => '6A1CF3B08D175A284C30BC10DE19162307C7286E']),
            $transport,
        ];

        yield [
            Dsn::fromString('smtps://:@example.com?peer_fingerprint=6A1CF3B08D175A284C30BC10DE19162307C7286E'),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 465, true, null, $logger);
        $transport->setLocalDomain('example.com');

        yield [
            new Dsn('smtps', 'example.com', '', '', 465, ['local_domain' => 'example.com']),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 465, true, null, $logger);
        $transport->setMaxPerSecond(2.0);

        yield [
            new Dsn('smtps', 'example.com', '', '', 465, ['max_per_second' => '2']),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 465, true, null, $logger);
        $transport->setRestartThreshold(10, 1);

        yield [
            new Dsn('smtps', 'example.com', '', '', 465, ['restart_threshold' => '10', 'restart_threshold_sleep' => '1']),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 465, true, null, $logger);
        $transport->setPingThreshold(10);

        yield [
            new Dsn('smtps', 'example.com', '', '', 465, ['ping_threshold' => '10']),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 25, false, null, $logger);
        $transport->setAutoTls(false);

        yield [
            new Dsn('smtp', 'example.com', '', '', 25, ['auto_tls' => false]),
            $transport,
        ];
        yield [
            new Dsn('smtp', 'example.com', '', '', 0, ['auto_tls' => false]),
            $transport,
        ];
        yield [
            Dsn::fromString('smtp://:@example.com?auto_tls=false'),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 465, false, null, $logger);
        $transport->setAutoTls(false);
        yield [
            Dsn::fromString('smtp://:@example.com:465?auto_tls=false'),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 465, true, null, $logger);
        $transport->getStream()->setSourceIp('0.0.0.0');
        yield [
            Dsn::fromString('smtps://:@example.com:465?source_ip=0.0.0.0'),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 465, true, null, $logger);
        $transport->getStream()->setSourceIp('[2606:4700:20::681a:5fb]');
        yield [
            Dsn::fromString('smtps://:@example.com:465?source_ip=[2606:4700:20::681a:5fb]'),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 465, true, null, $logger);
        $transport->setRequireTls(true);

        yield [
            new Dsn('smtps', 'example.com', '', '', 465, ['require_tls' => true]),
            $transport,
        ];
        yield [
            Dsn::fromString('smtps://:@example.com?require_tls=true'),
            $transport,
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [new Dsn('null', '')];
    }
}
