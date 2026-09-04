<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\PufferPost\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\PufferPost\Transport\PufferPostApiTransport;
use Symfony\Component\Mailer\Bridge\PufferPost\Transport\PufferPostTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class PufferPostTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new PufferPostTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('pufferpost', 'default'),
            true,
        ];

        yield [
            new Dsn('pufferpost+api', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('pufferpost', 'default', self::USER),
            new PufferPostApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('pufferpost+api', 'default', self::USER),
            new PufferPostApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('pufferpost+api', 'example.com', self::USER),
            (new PufferPostApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com'),
        ];

        yield [
            new Dsn('pufferpost+api', 'example.com', self::USER, null, 8080),
            (new PufferPostApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com')->setPort(8080),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('pufferpost+foo', 'default'),
            'The "pufferpost+foo" scheme is not supported; supported schemes for mailer "pufferpost" are: "pufferpost", "pufferpost+api".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('pufferpost+api', 'default')];
    }
}
