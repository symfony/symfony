<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Cloudflare\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Cloudflare\Transport\CloudflareApiTransport;
use Symfony\Component\Mailer\Bridge\Cloudflare\Transport\CloudflareSmtpTransport;
use Symfony\Component\Mailer\Bridge\Cloudflare\Transport\CloudflareTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

/**
 * @author vadage
 */
final class CloudflareTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new CloudflareTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('cloudflare', 'default'),
            true,
        ];

        yield [
            new Dsn('cloudflare+api', 'default'),
            true,
        ];

        yield [
            new Dsn('cloudflare+smtp', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        yield [
            new Dsn('cloudflare', 'default', self::USER, self::PASSWORD),
            new CloudflareApiTransport(self::USER, self::PASSWORD, new MockHttpClient(), null, new NullLogger()),
        ];
        yield [
            new Dsn('cloudflare', 'CLOUDFLARE_HOST', self::USER, self::PASSWORD),
            new CloudflareApiTransport(self::USER, self::PASSWORD, new MockHttpClient(), null, new NullLogger())->setHost('CLOUDFLARE_HOST'),
        ];
        yield [
            new Dsn('cloudflare+api', 'default', self::USER, self::PASSWORD),
            new CloudflareApiTransport(self::USER, self::PASSWORD, new MockHttpClient(), null, new NullLogger()),
        ];
        yield [
            new Dsn('cloudflare+api', 'CLOUDFLARE_HOST', self::USER, self::PASSWORD),
            new CloudflareApiTransport(self::USER, self::PASSWORD, new MockHttpClient(), null, new NullLogger())->setHost('CLOUDFLARE_HOST'),
        ];
        yield [
            new Dsn('cloudflare+smtp', 'default', password: self::PASSWORD),
            new CloudflareSmtpTransport(self::PASSWORD, null, new NullLogger()),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('cloudflare+foo', 'default', self::USER, self::PASSWORD),
            'The "cloudflare+foo" scheme is not supported; supported schemes for mailer "cloudflare" are: "cloudflare", "cloudflare+api", "cloudflare+smtp".',
        ];

        yield [
            new Dsn('cloudflare+foo', 'default'),
            'The "cloudflare+foo" scheme is not supported; supported schemes for mailer "cloudflare" are: "cloudflare", "cloudflare+api", "cloudflare+smtp".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('cloudflare', 'default')];
        yield [new Dsn('cloudflare', 'default', self::USER)];
        yield [new Dsn('cloudflare', 'default', null, self::PASSWORD)];
        yield [new Dsn('cloudflare+api', 'default')];
        yield [new Dsn('cloudflare+api', 'default', self::USER)];
        yield [new Dsn('cloudflare+api', 'default', null, self::PASSWORD)];
        yield [new Dsn('cloudflare+smtp', 'default')];
    }
}
