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

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\TokenManager;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\Transport\MicrosoftGraphApiTransport;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\Transport\MicrosoftGraphTransportFactory;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class MicrosoftGraphTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    protected const TENANT = 't3nant';

    public function getFactory(): TransportFactoryInterface
    {
        return new MicrosoftGraphTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('microsoftgraph+api', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $mockClient = new MockHttpClient();

        yield [
            new Dsn('microsoftgraph+api', 'default', self::USER, self::PASSWORD, null, ['tenantId' => self::TENANT]),
            new MicrosoftGraphApiTransport('graph.microsoft.com', new TokenManager('graph.microsoft.com', 'login.microsoftonline.com', self::TENANT, self::USER, self::PASSWORD, $mockClient), false, $mockClient, null, new NullLogger()),
        ];
        yield [
            new Dsn('microsoftgraph+api', 'other.ms.com', self::USER, self::PASSWORD, null, ['tenantId' => self::TENANT, 'authEndpoint' => 'auth.ms.com']),
            new MicrosoftGraphApiTransport('other.ms.com', new TokenManager('other.ms.com', 'auth.ms.com', self::TENANT, self::USER, self::PASSWORD, $mockClient), false, $mockClient, null, new NullLogger()),
        ];
        yield [
            new Dsn('microsoftgraph+api', 'default', self::USER, self::PASSWORD, null, ['tenantId' => self::TENANT, 'noSave' => true]),
            new MicrosoftGraphApiTransport('graph.microsoft.com', new TokenManager('graph.microsoft.com', 'login.microsoftonline.com', self::TENANT, self::USER, self::PASSWORD, $mockClient), true, $mockClient, null, new NullLogger()),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('microsoft+foo', 'default', self::USER, self::PASSWORD),
            'The "microsoft+foo" scheme is not supported; supported schemes for mailer "microsoft graph api" are: "microsoftgraph+api".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('microsoftgraph+api', 'default')];
        yield [new Dsn('microsoftgraph+api', 'default', self::USER)];
        yield [new Dsn('microsoftgraph+api', 'default', null, self::PASSWORD)];
        yield [new Dsn('microsoftgraph+api', 'default', self::USER, self::PASSWORD)];
        yield [new Dsn('microsoftgraph+api', 'non-default', self::USER, self::PASSWORD, null, ['tenantId' => self::TENANT])];
    }

    #[DataProvider('invalidHttpDsnProvider')]
    public function testValidatesHttpNotProvided(string $graph, string $auth, string $failingType)
    {
        $factory = $this->getFactory();
        $dsn = new Dsn('microsoftgraph+api', $graph, self::USER, self::PASSWORD, null, ['tenantId' => self::TENANT, 'authEndpoint' => $auth]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($failingType.' endpoint needs to be provided without "http(s)://".');
        $factory->create($dsn);
    }

    public static function invalidHttpDsnProvider(): iterable
    {
        yield ['http://graph', 'auth', 'Graph'];
        yield ['https://graph', 'auth', 'Graph'];
        yield ['graph', 'http://auth', 'Auth'];
        yield ['graph', 'https://auth', 'Auth'];
    }
}
