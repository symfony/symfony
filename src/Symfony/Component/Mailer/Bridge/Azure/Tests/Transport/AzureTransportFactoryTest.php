<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Azure\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Azure\Transport\AzureApiTransport;
use Symfony\Component\Mailer\Bridge\Azure\Transport\AzureTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class AzureTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new AzureTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('azure', 'default'),
            true,
        ];

        yield [
            new Dsn('azure+api', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        yield [
            new Dsn('azure', 'default', self::USER, self::PASSWORD),
            new AzureApiTransport(self::PASSWORD, self::USER, false, '2023-03-31', new MockHttpClient(), null, new NullLogger()),
        ];
        yield [
            new Dsn('azure', 'ACS_RESOURCE_NAME', self::USER, self::PASSWORD),
            (new AzureApiTransport(self::PASSWORD, self::USER, false, '2023-03-31', new MockHttpClient(), null, new NullLogger()))->setHost('ACS_RESOURCE_NAME'),
        ];
        yield [
            new Dsn('azure+api', 'default', self::USER, self::PASSWORD),
            new AzureApiTransport(self::PASSWORD, self::USER, false, '2023-03-31', new MockHttpClient(), null, new NullLogger()),
        ];
        yield [
            new Dsn('azure+api', 'ACS_RESOURCE_NAME', self::USER, self::PASSWORD),
            (new AzureApiTransport(self::PASSWORD, self::USER, false, '2023-03-31', new MockHttpClient(), null, new NullLogger()))->setHost('ACS_RESOURCE_NAME'),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('azure+foo', 'default', self::USER, self::PASSWORD),
            'The "azure+foo" scheme is not supported; supported schemes for mailer "azure" are: "azure", "azure+api".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('azure', 'default')];
        yield [new Dsn('azure', 'default', self::USER)];
        yield [new Dsn('azure', 'default', null, self::PASSWORD)];
        yield [new Dsn('azure+api', 'default')];
        yield [new Dsn('azure+api', 'default', self::USER)];
        yield [new Dsn('azure+api', 'default', null, self::PASSWORD)];
    }
}
