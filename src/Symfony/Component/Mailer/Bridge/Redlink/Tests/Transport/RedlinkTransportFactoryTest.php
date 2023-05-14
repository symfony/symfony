<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Redlink\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Redlink\Transport\RedlinkApiTransport;
use Symfony\Component\Mailer\Bridge\Redlink\Transport\RedlinkTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

/**
 * @author Mateusz Żyła <https://github.com/plotkabytes>
 */
class RedlinkTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new RedlinkTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('redlink', 'default'),
            true,
        ];

        yield [
            new Dsn('redlink+api', 'default'),
            true,
        ];

        yield [
            new Dsn('redlink+api', 'example.com'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        yield [
            new Dsn('redlink+api', 'default', self::USER, self::PASSWORD, null, ['fromSmtp' => '1.test.smtp', 'version' => 'v2.1']),
            new RedlinkApiTransport(self::USER, self::PASSWORD, '1.test.smtp', 'v2.1', new MockHttpClient(), null, new NullLogger()),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('redlink+foo', 'default', self::USER, self::PASSWORD),
            'The "redlink+foo" scheme is not supported; supported schemes for mailer "redlink" are: "redlink", "redlink+api".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('redlink+api', 'default', self::USER, null, null, ['fromSmtp' => 'v2.1'])];
        yield [new Dsn('redlink+api', 'default', null, self::PASSWORD, null, ['fromSmtp' => 'v2.1'])];
    }
}
