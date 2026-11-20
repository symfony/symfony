<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendora\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Sendora\Transport\SendoraSmtpTransport;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mime\Email;

class SendoraSmtpTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(SendoraSmtpTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): iterable
    {
        yield [
            new SendoraSmtpTransport('KEY'),
            'smtps://smtp.sendora.se',
        ];

        yield 'port 587 is STARTTLS' => [
            new SendoraSmtpTransport('KEY', null, 587),
            'smtp://smtp.sendora.se:587',
        ];

        yield [
            new SendoraSmtpTransport('KEY', 'smtp.example.com'),
            'smtps://smtp.example.com',
        ];
    }

    public function testTheKeyIsBothTheUsernameAndThePassword()
    {
        $transport = new SendoraSmtpTransport('KEY');

        $this->assertSame('KEY', $transport->getUsername());
        $this->assertSame('KEY', $transport->getPassword());
        $this->assertTrue($transport->isTlsRequired());
    }

    public function testTrackingHeader()
    {
        $email = new Email();
        $email->getHeaders()->add(new TrackingHeader(false, true));
        $this->addSendoraHeaders($email);

        $this->assertSame('X-Sendora-Track-Opens: false', $email->getHeaders()->get('X-Sendora-Track-Opens')->toString());
        $this->assertSame('X-Sendora-Track-Links: true', $email->getHeaders()->get('X-Sendora-Track-Links')->toString());
        $this->assertFalse($email->getHeaders()->has(TrackingHeader::NAME));
    }

    public function testTrackingHeaderLeavesTheOtherKindToTheStream()
    {
        $email = new Email();
        $email->getHeaders()->add(new TrackingHeader(null, false));
        $this->addSendoraHeaders($email);

        $this->assertFalse($email->getHeaders()->has('X-Sendora-Track-Opens'));
        $this->assertSame('X-Sendora-Track-Links: false', $email->getHeaders()->get('X-Sendora-Track-Links')->toString());
    }

    public function testExplicitSendoraTrackingHeaderWinsOverTrackingHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('X-Sendora-Track-Opens', 'false');
        $email->getHeaders()->add(new TrackingHeader(true, false));
        $this->addSendoraHeaders($email);

        $this->assertSame(1, iterator_count($email->getHeaders()->all('X-Sendora-Track-Opens')));
        $this->assertSame('X-Sendora-Track-Opens: false', $email->getHeaders()->get('X-Sendora-Track-Opens')->toString());
        $this->assertSame('X-Sendora-Track-Links: false', $email->getHeaders()->get('X-Sendora-Track-Links')->toString());
    }

    public function testPlainTextTrackingHeaderIsResolved()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader(TrackingHeader::NAME, 'opens=false; clicks=false');
        $this->addSendoraHeaders($email);

        $this->assertSame('X-Sendora-Track-Opens: false', $email->getHeaders()->get('X-Sendora-Track-Opens')->toString());
        $this->assertSame('X-Sendora-Track-Links: false', $email->getHeaders()->get('X-Sendora-Track-Links')->toString());
        $this->assertFalse($email->getHeaders()->has(TrackingHeader::NAME));
    }

    public function testStream()
    {
        $email = new Email();
        $this->addSendoraHeaders($email, (new SendoraSmtpTransport('KEY'))->setStream('3d1a2b4c-5e6f-4a7b-8c9d-0e1f2a3b4c5d'));

        $this->assertSame('X-Sendora-Stream: 3d1a2b4c-5e6f-4a7b-8c9d-0e1f2a3b4c5d', $email->getHeaders()->get('X-Sendora-Stream')->toString());
    }

    public function testExplicitStreamHeaderWinsOverTheTransportsStream()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('X-Sendora-Stream', '8a0c2e4a-6c8e-4a0c-8e4a-6c8e0a2c4e6a');
        $this->addSendoraHeaders($email, (new SendoraSmtpTransport('KEY'))->setStream('3d1a2b4c-5e6f-4a7b-8c9d-0e1f2a3b4c5d'));

        $this->assertSame(1, iterator_count($email->getHeaders()->all('X-Sendora-Stream')));
        $this->assertSame('X-Sendora-Stream: 8a0c2e4a-6c8e-4a0c-8e4a-6c8e0a2c4e6a', $email->getHeaders()->get('X-Sendora-Stream')->toString());
    }

    public static function getReplies(): iterable
    {
        yield 'a live server' => ["250 2.0.0 OK: queued as 5f432ffd-c005-499b-aa3a-5b8a088ea20e\r\n", '5f432ffd-c005-499b-aa3a-5b8a088ea20e'];
        yield 'a test server' => ["250 2.0.0 OK: accepted as 5f432ffd-c005-499b-aa3a-5b8a088ea20e on a test server; nothing is delivered\r\n", '5f432ffd-c005-499b-aa3a-5b8a088ea20e'];
        yield 'another reply' => ["250 2.0.0 OK\r\n", ''];
    }

    #[DataProvider('getReplies')]
    public function testTheMessageIdIsSendorasId(string $reply, string $messageId)
    {
        $method = new \ReflectionMethod(SendoraSmtpTransport::class, 'parseMessageId');

        $this->assertSame($messageId, $method->invoke(new SendoraSmtpTransport('KEY'), $reply));
    }

    private function addSendoraHeaders(Email $email, ?SendoraSmtpTransport $transport = null): void
    {
        $method = new \ReflectionMethod(SendoraSmtpTransport::class, 'addSendoraHeaders');
        $method->invoke($transport ?? new SendoraSmtpTransport('KEY'), $email->getHeaders());
    }
}
