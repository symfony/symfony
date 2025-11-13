<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport\Smtp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\Auth\CramMd5Authenticator;
use Symfony\Component\Mailer\Transport\Smtp\Auth\LoginAuthenticator;
use Symfony\Component\Mailer\Transport\Smtp\Auth\XOAuth2Authenticator;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

class EsmtpTransportTest extends TestCase
{
    public function testToString()
    {
        $t = new EsmtpTransport();
        $this->assertEquals('smtp://localhost', (string) $t);

        $t = new EsmtpTransport('example.com');
        if (\defined('OPENSSL_VERSION_NUMBER')) {
            $this->assertEquals('smtps://example.com', (string) $t);
        } else {
            $this->assertEquals('smtp://example.com', (string) $t);
        }

        $t = new EsmtpTransport('example.com', 2525);
        $this->assertEquals('smtp://example.com:2525', (string) $t);

        $t = new EsmtpTransport('example.com', 0, true);
        $this->assertEquals('smtps://example.com', (string) $t);

        $t = new EsmtpTransport('example.com', 0, false);
        $this->assertEquals('smtp://example.com', (string) $t);

        $t = new EsmtpTransport('example.com', 466, true);
        $this->assertEquals('smtps://example.com:466', (string) $t);
    }

    public function testExtensibility()
    {
        $stream = new DummyStream();
        $transport = new CustomEsmtpTransport(stream: $stream);

        $message = new Email();
        $message->from('sender@example.org');
        $message->addTo('recipient@example.org');
        $message->text('.');

        $transport->send($message);

        $this->assertContains("MAIL FROM:<sender@example.org> RET=HDRS\r\n", $stream->getCommands());
        $this->assertContains("RCPT TO:<recipient@example.org> NOTIFY=FAILURE\r\n", $stream->getCommands());
    }

    public function testSmtpUtf8()
    {
        $stream = new DummyStream();
        $transport = new SmtpUtf8EsmtpTransport(stream: $stream);

        $message = new Email();
        $message->from('info@dømi.fo');
        $message->addTo('dømi@dømi.fo');
        $message->text('.');

        $transport->send($message);

        $this->assertContains("MAIL FROM:<info@xn--dmi-0na.fo> SMTPUTF8\r\n", $stream->getCommands());
        $this->assertContains("RCPT TO:<dømi@xn--dmi-0na.fo>\r\n", $stream->getCommands());
    }

    public function testMissingSmtpUtf8()
    {
        $stream = new DummyStream();
        $transport = new EsmtpTransport(stream: $stream);

        $message = new Email();
        $message->from('info@dømi.fo');
        $message->addTo('dømi@dømi.fo');
        $message->text('.');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid addresses: non-ASCII characters not supported in local-part of email.');
        $transport->send($message);
    }

    public function testSmtpUtf8FallbackToIDN()
    {
        $stream = new DummyStream();
        $transport = new EsmtpTransport(stream: $stream);

        $message = new Email();
        $message->from('info@dømi.fo'); // UTF8 only in the domain
        $message->addTo('example@example.com');
        $message->text('.');

        $transport->send($message);

        $this->assertContains("MAIL FROM:<info@xn--dmi-0na.fo>\r\n", $stream->getCommands());
        $this->assertContains("RCPT TO:<example@example.com>\r\n", $stream->getCommands());
    }

    public function testConstructorWithDefaultAuthenticators()
    {
        $stream = new DummyStream();
        $transport = new EsmtpTransport(stream: $stream);
        $transport->setUsername('testuser');
        $transport->setPassword('p4ssw0rd');

        $message = new Email();
        $message->from('sender@example.org');
        $message->addTo('recipient@example.org');
        $message->text('.');

        try {
            $transport->send($message);
            $this->fail('Symfony\Component\Mailer\Exception\TransportException to be thrown');
        } catch (TransportException $e) {
            $this->assertStringStartsWith('Failed to authenticate on SMTP server with username "testuser" using the following authenticators: "CRAM-MD5", "LOGIN", "PLAIN", "XOAUTH2".', $e->getMessage());
        }

        $this->assertEquals(
            [
                "EHLO [127.0.0.1]\r\n",
                // S: 250 localhost
                // S: 250-AUTH PLAIN LOGIN CRAM-MD5 XOAUTH2
                "AUTH CRAM-MD5\r\n",
                // S: 334 PDAxMjM0NTY3ODkuMDEyMzQ1NjdAc3ltZm9ueT4=
                "dGVzdHVzZXIgNTdlYzg2ODM5OWZhZThjY2M5OWFhZGVjZjhiZTAwNmY=\r\n",
                // S: 535 5.7.139 Authentication unsuccessful
                "RSET\r\n",
                // S: 250 2.0.0 Resetting
                "AUTH LOGIN\r\n",
                // S: 334 VXNlcm5hbWU6
                "dGVzdHVzZXI=\r\n",
                // S: 334 UGFzc3dvcmQ6
                "cDRzc3cwcmQ=\r\n",
                // S: 535 5.7.139 Authentication unsuccessful
                "RSET\r\n",
                // S: 250 2.0.0 Resetting
                "AUTH PLAIN dGVzdHVzZXIAdGVzdHVzZXIAcDRzc3cwcmQ=\r\n",
                // S: 535 5.7.139 Authentication unsuccessful
                "RSET\r\n",
                // S: 250 2.0.0 Resetting
                "AUTH XOAUTH2 dXNlcj10ZXN0dXNlcgFhdXRoPUJlYXJlciBwNHNzdzByZAEB\r\n",
                // S: 535 5.7.139 Authentication unsuccessful
                "RSET\r\n",
                // S: 250 2.0.0 Resetting
            ],
            $stream->getCommands()
        );
    }

    public function testConstructorWithRedefinedAuthenticators()
    {
        $stream = new DummyStream();
        $transport = new EsmtpTransport(
            stream: $stream,
            authenticators: [new CramMd5Authenticator(), new LoginAuthenticator()]
        );
        $transport->setUsername('testuser');
        $transport->setPassword('p4ssw0rd');

        $message = new Email();
        $message->from('sender@example.org');
        $message->addTo('recipient@example.org');
        $message->text('.');

        try {
            $transport->send($message);
            $this->fail('Symfony\Component\Mailer\Exception\TransportException to be thrown');
        } catch (TransportException $e) {
            $this->assertStringStartsWith('Failed to authenticate on SMTP server with username "testuser" using the following authenticators: "CRAM-MD5", "LOGIN".', $e->getMessage());
        }

        $this->assertEquals(
            [
                "EHLO [127.0.0.1]\r\n",
                // S: 250 localhost
                // S: 250-AUTH PLAIN LOGIN CRAM-MD5 XOAUTH2
                "AUTH CRAM-MD5\r\n",
                // S: 334 PDAxMjM0NTY3ODkuMDEyMzQ1NjdAc3ltZm9ueT4=
                "dGVzdHVzZXIgNTdlYzg2ODM5OWZhZThjY2M5OWFhZGVjZjhiZTAwNmY=\r\n",
                // S: 535 5.7.139 Authentication unsuccessful
                "RSET\r\n",
                // S: 250 2.0.0 Resetting
                "AUTH LOGIN\r\n",
                // S: 334 VXNlcm5hbWU6
                "dGVzdHVzZXI=\r\n",
                // S: 334 UGFzc3dvcmQ6
                "cDRzc3cwcmQ=\r\n",
                // S: 535 5.7.139 Authentication unsuccessful
                "RSET\r\n",
                // S: 250 2.0.0 Resetting
            ],
            $stream->getCommands()
        );
    }

    public function testSetAuthenticators()
    {
        $stream = new DummyStream();
        $transport = new EsmtpTransport(stream: $stream);
        $transport->setUsername('testuser');
        $transport->setPassword('p4ssw0rd');
        $transport->setAuthenticators([new XOAuth2Authenticator()]);

        $message = new Email();
        $message->from('sender@example.org');
        $message->addTo('recipient@example.org');
        $message->text('.');

        try {
            $transport->send($message);
            $this->fail('Symfony\Component\Mailer\Exception\TransportException to be thrown');
        } catch (TransportException $e) {
            $this->assertStringStartsWith('Failed to authenticate on SMTP server with username "testuser" using the following authenticators: "XOAUTH2".', $e->getMessage());
        }

        $this->assertEquals(
            [
                "EHLO [127.0.0.1]\r\n",
                // S: 250 localhost
                // S: 250-AUTH PLAIN LOGIN CRAM-MD5 XOAUTH2
                "AUTH XOAUTH2 dXNlcj10ZXN0dXNlcgFhdXRoPUJlYXJlciBwNHNzdzByZAEB\r\n",
                // S: 535 5.7.139 Authentication unsuccessful
                "RSET\r\n",
                // S: 250 2.0.0 Resetting
            ],
            $stream->getCommands()
        );
    }

    public function testConstructorWithEmptyAuthenticator()
    {
        $stream = new DummyStream();
        $transport = new EsmtpTransport(stream: $stream);
        $transport->setUsername('testuser');
        $transport->setPassword('p4ssw0rd');
        $transport->setAuthenticators([]); // if no authenticators defined, then there needs to be a TransportException

        $message = new Email();
        $message->from('sender@example.org');
        $message->addTo('recipient@example.org');
        $message->text('.');

        try {
            $transport->send($message);
            $this->fail('Symfony\Component\Mailer\Exception\TransportException to be thrown');
        } catch (TransportException $e) {
            $this->assertStringStartsWith('Failed to find an authenticator supported by the SMTP server, which currently supports: "plain", "login", "cram-md5", "xoauth2".', $e->getMessage());
            $this->assertEquals(504, $e->getCode());
        }
    }

    public function testSocketTimeout()
    {
        $stream = new DummyStream();
        $transport = new EsmtpTransport(stream: $stream);
        $transport->setUsername('testuser');
        $transport->setPassword('timedout');
        $transport->setAuthenticators([new LoginAuthenticator()]);

        $message = new Email();
        $message->from('sender@example.org');
        $message->addTo('recipient@example.org');
        $message->text('.');

        try {
            $transport->send($message);
            $this->fail('Symfony\Component\Mailer\Exception\TransportException to be thrown');
        } catch (TransportException $e) {
            $this->assertStringStartsWith('Connection to "localhost" timed out.', $e->getMessage());
        }

        $this->assertEquals(
            [
                "EHLO [127.0.0.1]\r\n",
                // S: 250 localhost
                // S: 250-AUTH PLAIN LOGIN CRAM-MD5 XOAUTH2
                "AUTH LOGIN\r\n",
                // S: 334 VXNlcm5hbWU6
                "dGVzdHVzZXI=\r\n",
                // S: 334 UGFzc3dvcmQ6
                "dGltZWRvdXQ=\r\n",
            ],
            $stream->getCommands()
        );
    }

    public function testRequireTls()
    {
        $stream = new DummyStream();
        $transport = new EsmtpTransport(stream: $stream);
        $transport->setRequireTls(true);

        $message = new Email();
        $message->from('sender@example.org');
        $message->addTo('recipient@example.org');
        $message->text('.');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('TLS required but neither TLS or STARTTLS are in use.');

        $transport->send($message);
    }
}

class CustomEsmtpTransport extends EsmtpTransport
{
    public function executeCommand(string $command, array $codes): string
    {
        $command = match (true) {
            str_starts_with($command, 'MAIL FROM:') && isset($this->getCapabilities()['DSN']) => substr_replace($command, ' RET=HDRS', -2, 0),
            str_starts_with($command, 'RCPT TO:') && isset($this->getCapabilities()['DSN']) => substr_replace($command, ' NOTIFY=FAILURE', -2, 0),
            default => $command,
        };

        $response = parent::executeCommand($command, $codes);

        if (str_starts_with($command, 'EHLO ')) {
            $response .= "250 DSN\r\n";
        }

        return $response;
    }
}

class SmtpUtf8EsmtpTransport extends EsmtpTransport
{
    public function executeCommand(string $command, array $codes): string
    {
        $response = parent::executeCommand($command, $codes);

        if (str_starts_with($command, 'EHLO ')) {
            $response .= "250 SMTPUTF8\r\n";
        }

        return $response;
    }
}
