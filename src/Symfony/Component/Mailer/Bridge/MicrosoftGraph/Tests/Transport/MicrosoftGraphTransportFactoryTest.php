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

use Microsoft\Graph\Core\NationalCloud;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\Transport\MicrosoftGraphTransport;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\Transport\MicrosoftGraphTransportFactory;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
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
            new MicrosoftGraphTransport(NationalCloud::GLOBAL, self::TENANT, self::USER, self::PASSWORD, new NullAdapter()),
        ];

        yield [
            new Dsn('microsoft+graph', 'germany', self::USER, self::PASSWORD, null, ['tenant' => self::TENANT]),
            new MicrosoftGraphTransport(NationalCloud::GERMANY, self::TENANT, self::USER, self::PASSWORD, new NullAdapter()),
        ];

        yield [
            new Dsn('microsoft+graph', 'china', self::USER, self::PASSWORD, null, ['tenant' => self::TENANT]),
            new MicrosoftGraphTransport(NationalCloud::CHINA, self::TENANT, self::USER, self::PASSWORD, new NullAdapter()),
        ];

        yield [
            new Dsn('microsoft+graph', 'us-gov', self::USER, self::PASSWORD, null, ['tenant' => self::TENANT]),
            new MicrosoftGraphTransport(NationalCloud::US_GOV, self::TENANT, self::USER, self::PASSWORD, new NullAdapter()),
        ];

        yield [
            new Dsn('microsoft+graph', 'us-dod', self::USER, self::PASSWORD, null, ['tenant' => self::TENANT]),
            new MicrosoftGraphTransport(NationalCloud::US_DOD, self::TENANT, self::USER, self::PASSWORD, new NullAdapter()),
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

        yield [new Dsn('microsoft+graph', 'default', self::USER, self::PASSWORD)];

        yield [new Dsn('microsoft+graph', 'default', null, self::PASSWORD)];

        yield [new Dsn('microsoft+graph', 'default', null, null)];
    }

    public function testInvalidDsnHost(): void
    {
        $factory = $this->getFactory();

        $this->expectException(InvalidArgumentException::class);
        $factory->create(new Dsn('microsoft+graph', 'some-wrong-national-cloud', self::USER, self::PASSWORD, null, ['tenant' => self::TENANT]));
    }

}
