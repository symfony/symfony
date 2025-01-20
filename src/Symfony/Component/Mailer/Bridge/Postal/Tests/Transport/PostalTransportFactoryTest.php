<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postal\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Postal\Transport\PostalApiTransport;
use Symfony\Component\Mailer\Bridge\Postal\Transport\PostalTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class PostalTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new PostalTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('postal+api', 'postal.localhost'),
            true,
        ];

        yield [
            new Dsn('postal', 'postal.localhost'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('postal+api', 'postal.localhost', null, self::PASSWORD),
            new PostalApiTransport(self::PASSWORD, 'postal.localhost', new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('postal', 'postal.localhost', null, self::PASSWORD),
            new PostalApiTransport(self::PASSWORD, 'postal.localhost', new MockHttpClient(), null, $logger),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('postal+foo', 'postal.localhost', null, self::PASSWORD),
            'The "postal+foo" scheme is not supported; supported schemes for mailer "postal" are: "postal", "postal+api".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('postal+api', 'postal.localhost', null)];
    }
}
