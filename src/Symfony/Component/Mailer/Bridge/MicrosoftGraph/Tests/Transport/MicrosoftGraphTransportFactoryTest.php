<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MicrosoftGraph\Tests\Transport;

use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\Transport\MicrosoftGraphTransport;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\Transport\MicrosoftGraphTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class MicrosoftGraphTransportFactoryTest extends TransportFactoryTestCase
{
    protected const TENANT = 'tenantId';

    public function getFactory(): TransportFactoryInterface
    {
        return new MicrosoftGraphTransportFactory(new NullAdapter());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('microsoft+graph', 'default'),
            true,
        ];

        yield [
            new Dsn('microsoft+graph', 'example.com'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        yield [
            new Dsn('microsoft+graph', 'default', self::USER, self::PASSWORD, null, ['tenant' => self::TENANT]),
            new MicrosoftGraphTransport(self::USER, self::PASSWORD, 'https://login.microsoftonline.com/tenantId/oauth2/v2.0/token', 'https://graph.microsoft.com', new NullAdapter()),
        ];
        yield [
            new Dsn('microsoft+graph', 'https://example.com', self::USER, self::PASSWORD, null, ['tenant' => self::TENANT, 'graphEndpoint' => 'https://another-example.com']),
            new MicrosoftGraphTransport(self::USER, self::PASSWORD, 'https://example.com/tenantId/oauth2/v2.0/token', 'https://another-example.com', new NullAdapter()),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('microsoft+smtp', 'default', self::USER, self::PASSWORD),
            'The "microsoft+smtp" scheme is not supported; supported schemes for mailer "microsoft graph" are: "microsoft+graph".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('microsoft+graph', 'default', self::USER)];

        yield [new Dsn('microsoft+graph', 'default', null, self::PASSWORD)];
    }
}
