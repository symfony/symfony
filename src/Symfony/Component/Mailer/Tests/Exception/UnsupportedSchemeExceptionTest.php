<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClassExistsMock;
use Symfony\Component\Mailer\Bridge\AhaSend\Transport\AhaSendTransportFactory;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use Symfony\Component\Mailer\Bridge\Azure\Transport\AzureTransportFactory;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Bridge\Infobip\Transport\InfobipTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillTransportFactory;
use Symfony\Component\Mailer\Bridge\MailerSend\Transport\MailerSendTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailjet\Transport\MailjetTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailomat\Transport\MailomatTransportFactory;
use Symfony\Component\Mailer\Bridge\MailPace\Transport\MailPaceTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailtrap\Transport\MailtrapTransportFactory;
use Symfony\Component\Mailer\Bridge\Postal\Transport\PostalTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;
use Symfony\Component\Mailer\Bridge\Resend\Transport\ResendTransportFactory;
use Symfony\Component\Mailer\Bridge\Scaleway\Transport\ScalewayTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Bridge\Sweego\Transport\SweegoTransportFactory;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\Dsn;

/**
 * @runTestsInSeparateProcesses
 */
final class UnsupportedSchemeExceptionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ClassExistsMock::register(__CLASS__);
        ClassExistsMock::withMockedClasses([
            AhaSendTransportFactory::class => false,
            AzureTransportFactory::class => false,
            BrevoTransportFactory::class => false,
            GmailTransportFactory::class => false,
            InfobipTransportFactory::class => false,
            MailPaceTransportFactory::class => false,
            MailerSendTransportFactory::class => false,
            MailgunTransportFactory::class => false,
            MailjetTransportFactory::class => false,
            MailomatTransportFactory::class => false,
            MandrillTransportFactory::class => false,
            PostalTransportFactory::class => false,
            PostmarkTransportFactory::class => false,
            MailtrapTransportFactory::class => false,
            ResendTransportFactory::class => false,
            ScalewayTransportFactory::class => false,
            SendgridTransportFactory::class => false,
            SesTransportFactory::class => false,
            SweegoTransportFactory::class => false,
        ]);
    }

    /**
     * @dataProvider messageWhereSchemeIsPartOfSchemeToPackageMapProvider
     */
    public function testMessageWhereSchemeIsPartOfSchemeToPackageMap(string $scheme, string $package)
    {
        $dsn = new Dsn($scheme, 'localhost');

        $this->assertSame(
            \sprintf('Unable to send emails via "%s" as the bridge is not installed. Try running "composer require %s".', $scheme, $package),
            (new UnsupportedSchemeException($dsn))->getMessage()
        );
    }

    public static function messageWhereSchemeIsPartOfSchemeToPackageMapProvider(): \Generator
    {
        yield ['ahasend', 'symfony/aha-send-mailer'];
        yield ['azure', 'symfony/azure-mailer'];
        yield ['brevo', 'symfony/brevo-mailer'];
        yield ['gmail', 'symfony/google-mailer'];
        yield ['infobip', 'symfony/infobip-mailer'];
        yield ['mailersend', 'symfony/mailersend-mailer'];
        yield ['mailgun', 'symfony/mailgun-mailer'];
        yield ['mailjet', 'symfony/mailjet-mailer'];
        yield ['mailomat', 'symfony/mailomat-mailer'];
        yield ['mailpace', 'symfony/mail-pace-mailer'];
        yield ['mandrill', 'symfony/mailchimp-mailer'];
        yield ['postal', 'symfony/postal-mailer'];
        yield ['postmark', 'symfony/postmark-mailer'];
        yield ['mailtrap', 'symfony/mailtrap-mailer'];
        yield ['resend', 'symfony/resend-mailer'];
        yield ['scaleway', 'symfony/scaleway-mailer'];
        yield ['sendgrid', 'symfony/sendgrid-mailer'];
        yield ['ses', 'symfony/amazon-mailer'];
        yield ['sweego', 'symfony/sweego-mailer'];
    }

    /**
     * @dataProvider messageWhereSchemeIsNotPartOfSchemeToPackageMapProvider
     */
    public function testMessageWhereSchemeIsNotPartOfSchemeToPackageMap(string $expected, Dsn $dsn, ?string $name, array $supported)
    {
        $this->assertSame(
            $expected,
            (new UnsupportedSchemeException($dsn, $name, $supported))->getMessage()
        );
    }

    public static function messageWhereSchemeIsNotPartOfSchemeToPackageMapProvider(): \Generator
    {
        yield [
            'The "somethingElse" scheme is not supported.',
            new Dsn('somethingElse', 'localhost'),
            null,
            [],
        ];

        yield [
            'The "somethingElse" scheme is not supported.',
            new Dsn('somethingElse', 'localhost'),
            'foo',
            [],
        ];

        yield [
            'The "somethingElse" scheme is not supported; supported schemes for mailer "one" are: "one", "two".',
            new Dsn('somethingElse', 'localhost'),
            'one',
            ['one', 'two'],
        ];
    }
}
