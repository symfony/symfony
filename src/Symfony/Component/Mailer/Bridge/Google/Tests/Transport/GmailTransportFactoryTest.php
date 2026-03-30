<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Google\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailApiTransport;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailSmtpTransport;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class GmailTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    private static string $testPrivateKey = '';
    private static string $testPrivateKeyBase64 = '';

    public static function setUpBeforeClass(): void
    {
        // Generate a test RSA private key
        self::$testPrivateKey = file_get_contents(__DIR__.'/../Fixtures/private_key.pem');
        self::$testPrivateKeyBase64 = base64_encode(self::$testPrivateKey);
    }

    public function getFactory(): TransportFactoryInterface
    {
        return new GmailTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('gmail', 'default'),
            true,
        ];

        yield [
            new Dsn('gmail+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('gmail+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('gmail+smtp', 'example.com'),
            true,
        ];

        yield [
            new Dsn('gmail+api', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        yield [
            new Dsn('gmail', 'default', self::USER, self::PASSWORD),
            new GmailSmtpTransport(self::USER, self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('gmail+smtp', 'default', self::USER, self::PASSWORD),
            new GmailSmtpTransport(self::USER, self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('gmail+smtps', 'default', self::USER, self::PASSWORD),
            new GmailSmtpTransport(self::USER, self::PASSWORD, null, new NullLogger()),
        ];
    }

    public function testCreateGmailApiTransport()
    {
        $factory = $this->getFactory();
        $dsn = new Dsn('gmail+api', 'default', 'service@example.iam.gserviceaccount.com', self::$testPrivateKeyBase64, null, ['user' => 'sender@example.com']);

        $transport = $factory->create($dsn);

        $this->assertInstanceOf(GmailApiTransport::class, $transport);
        $this->assertSame('gmail+api://sender@example.com', (string) $transport);
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('gmail+foo', 'default', self::USER, self::PASSWORD),
            'The "gmail+foo" scheme is not supported; supported schemes for mailer "gmail" are: "gmail", "gmail+smtp", "gmail+smtps", "gmail+api".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('gmail+smtp', 'default', self::USER)];

        yield [new Dsn('gmail+smtp', 'default', null, self::PASSWORD)];

        // gmail+api requires 'user' option
        yield [new Dsn('gmail+api', 'default', 'service@example.com', 'key')];
    }
}
