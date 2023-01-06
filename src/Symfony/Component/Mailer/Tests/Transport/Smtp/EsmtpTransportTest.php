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
