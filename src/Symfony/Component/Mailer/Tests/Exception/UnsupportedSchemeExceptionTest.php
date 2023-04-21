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
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Bridge\Infobip\Transport\InfobipTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillTransportFactory;
use Symfony\Component\Mailer\Bridge\MailerSend\Transport\MailerSendTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailjet\Transport\MailjetTransportFactory;
use Symfony\Component\Mailer\Bridge\OhMySmtp\Transport\OhMySmtpTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendinblue\Transport\SendinblueTransportFactory;
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
            GmailTransportFactory::class => false,
            InfobipTransportFactory::class => false,
            MailerSendTransportFactory::class => false,
            MailgunTransportFactory::class => false,
            MailjetTransportFactory::class => false,
            MandrillTransportFactory::class => false,
            OhMySmtpTransportFactory::class => false,
            PostmarkTransportFactory::class => false,
            SendgridTransportFactory::class => false,
            SendinblueTransportFactory::class => false,
            SesTransportFactory::class => false,
        ]);
    }

    /**
     * @dataProvider messageWhereSchemeIsPartOfSchemeToPackageMapProvider
     */
    public function testMessageWhereSchemeIsPartOfSchemeToPackageMap(string $scheme, string $package)
    {
        $dsn = new Dsn($scheme, 'localhost');

        $this->assertSame(
            sprintf('Unable to send emails via "%s" as the bridge is not installed. Try running "composer require %s".', $scheme, $package),
            (new UnsupportedSchemeException($dsn))->getMessage()
        );
    }

    public static function messageWhereSchemeIsPartOfSchemeToPackageMapProvider(): \Generator
    {
        yield ['gmail', 'symfony/google-mailer'];
        yield ['infobip', 'symfony/infobip-mailer'];
        yield ['mailersend', 'symfony/mailersend-mailer'];
        yield ['mailgun', 'symfony/mailgun-mailer'];
        yield ['mailjet', 'symfony/mailjet-mailer'];
        yield ['mandrill', 'symfony/mailchimp-mailer'];
        yield ['ohmysmtp', 'symfony/oh-my-smtp-mailer'];
        yield ['postmark', 'symfony/postmark-mailer'];
        yield ['sendgrid', 'symfony/sendgrid-mailer'];
        yield ['sendinblue', 'symfony/sendinblue-mailer'];
        yield ['ses', 'symfony/amazon-mailer'];
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
