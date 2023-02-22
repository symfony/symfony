<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Infobip\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Infobip\Transport\InfobipApiTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class InfobipApiTransportTest extends TestCase
{
    protected const KEY = 'k3y';

    private MockResponse $response;
    private MockHttpClient $httpClient;
    private InfobipApiTransport $transport;

    protected function setUp(): void
    {
        $this->response = new MockResponse('{}');
        $this->httpClient = new class(fn () => $this->response) extends MockHttpClient {
            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                // The only purpose of this method override is to record the request body as a string
                // It's impossible to get the generated body when using a generator as a body
                if (isset($options['body']) && $options['body'] instanceof \Generator) {
                    $body = '';
                    foreach ($options['body'] as $data) {
                        $body .= $data;
                    }
                    $options['body'] = $body;
                }

                return parent::request($method, $url, $options);
            }
        };
        $this->transport = new InfobipApiTransport(self::KEY, $this->httpClient);
        $this->transport->setHost('99999.api.infobip.com');
    }

    protected function tearDown(): void
    {
        unset($this->response, $this->httpClient, $this->transport);
    }

    public function testToString()
    {
        $this->assertSame('infobip+api://99999.api.infobip.com', (string) $this->transport);
    }

    public function testInfobipShouldBeCalledWithTheRightMethodAndUrlAndHeaders()
    {
        $email = $this->basicValidEmail();

        $this->transport->send($email);

        $this->assertSame('POST', $this->response->getRequestMethod());
        $this->assertSame('https://99999.api.infobip.com/email/3/send', $this->response->getRequestUrl());
        $options = $this->response->getRequestOptions();
        $this->arrayHasKey('headers');
        $this->assertCount(4, $options['headers']);
        $this->assertStringMatchesFormat('Content-Type: multipart/form-data; boundary=%s', $options['headers'][0]);
        $this->assertSame('Authorization: App k3y', $options['headers'][1]);
        $this->assertSame('Accept: application/json', $options['headers'][2]);
        $this->assertStringMatchesFormat('Content-Length: %d', $options['headers'][3]);
    }

    public function testSendMinimalEmailShouldCalledInfobipWithTheRightParameters()
    {
        $email = (new Email())
            ->subject('Subject of the email')
            ->from('from@example.com')
            ->to('to@example.com')
            ->text('Some text')
        ;

        $this->transport->send($email);

        $options = $this->response->getRequestOptions();
        $this->arrayHasKey('body');
        $this->assertStringMatchesFormat(<<<'TXT'
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="from"

            from@example.com
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="subject"

            Subject of the email
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="to"

            to@example.com
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="text"

            Some text
            --%s--
            TXT,
            $options['body']
        );
    }

    public function testSendFullEmailShouldCalledInfobipWithTheRightParameters()
    {
        $email = (new Email())
            ->subject('Subject of the email')
            ->from('From <from@example.com>')
            ->to('to1@example.com', 'to2@example.com')
            ->text('Some text')
            ->html('<html><p>Hello!</p></html>')
            ->bcc('bcc@example.com')
            ->cc('cc@example.com')
            ->date(new \DateTimeImmutable('2022-04-28 14:00.00', new \DateTimeZone('UTC')))
            ->replyTo('replyTo@example.com')
        ;

        $this->transport->send($email);

        $options = $this->response->getRequestOptions();
        $this->arrayHasKey('body');
        $this->assertStringMatchesFormat(<<<'TXT'
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="from"

            "From" <from@example.com>
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="subject"

            Subject of the email
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="to"

            to1@example.com
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="to"

            to2@example.com
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="cc"

            cc@example.com
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="bcc"

            bcc@example.com
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="replyto"

            replyTo@example.com
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="text"

            Some text
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="HTML"

            <html><p>Hello!</p></html>
            --%s--
            TXT,
            $options['body']
        );
    }

    public function testSendEmailWithAttachmentsShouldCalledInfobipWithTheRightParameters()
    {
        $email = $this->basicValidEmail()
            ->text('foobar')
            ->addPart(new DataPart('some attachment', 'attachment.txt', 'text/plain'))
            ->addPart((new DataPart('some inline attachment', 'inline.txt', 'text/plain'))->asInline())
        ;

        $this->transport->send($email);

        $options = $this->response->getRequestOptions();
        $this->arrayHasKey('body');
        $this->assertStringMatchesFormat(<<<'TXT'
            %a
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="text"

            foobar
            --%s
            Content-Type: text/plain
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="attachment"; filename="attachment.txt"

            some attachment
            --%s
            Content-Type: text/plain
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="inlineImage"; filename="inline.txt"

            some inline attachment
            --%s--
            TXT,
            $options['body']
        );
    }

    public function testSendEmailWithHeadersShouldCalledInfobipWithTheRightParameters()
    {
        $email = $this->basicValidEmail();
        $email->getHeaders()
            ->addTextHeader('X-Infobip-IntermediateReport', 'true')
            ->addTextHeader('X-Infobip-NotifyUrl', 'https://foo.bar')
            ->addTextHeader('X-Infobip-NotifyContentType', 'application/json')
            ->addTextHeader('X-Infobip-MessageId', 'RANDOM-CUSTOM-ID');

        $this->transport->send($email);

        $options = $this->response->getRequestOptions();
        $this->arrayHasKey('body');
        $this->assertStringMatchesFormat(<<<'TXT'
            %a
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="intermediateReport"

            true
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="notifyUrl"

            https://foo.bar
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="notifyContentType"

            application/json
            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: 8bit
            Content-Disposition: form-data; name="messageId"

            RANDOM-CUSTOM-ID
            --%s--
            TXT,
            $options['body']
        );
    }

    public function testSendMinimalEmailWithSuccess()
    {
        $email = (new Email())
            ->subject('Subject of the email')
            ->from('from@example.com')
            ->to('to@example.com')
            ->text('Some text')
        ;

        $sentMessage = $this->transport->send($email);

        $this->assertInstanceOf(SentMessage::class, $sentMessage);
        $this->assertStringMatchesFormat(
            <<<'TXT'
            Subject: Subject of the email
            From: from@example.com
            To: to@example.com
            Message-ID: <%x@example.com>
            MIME-Version: %f
            Date: %s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: quoted-printable

            Some text
            TXT,
            $sentMessage->toString()
        );
    }

    public function testSendFullEmailWithSuccess()
    {
        $email = (new Email())
            ->subject('Subject of the email')
            ->from('From <from@example.com>')
            ->to('to1@example.com', 'to2@example.com')
            ->text('Some text')
            ->html('<html><p>Hello!</p></html>')
            ->bcc('bcc@example.com')
            ->cc('cc@example.com')
            ->date(new \DateTimeImmutable('2022-04-28 14:00.00', new \DateTimeZone('UTC')))
            ->replyTo('replyTo@example.com')
        ;

        $sentMessage = $this->transport->send($email);

        $this->assertInstanceOf(SentMessage::class, $sentMessage);
        $this->assertStringMatchesFormat(
            <<<'TXT'
            Subject: Subject of the email
            From: From <from@example.com>
            To: to1@example.com, to2@example.com
            Cc: cc@example.com
            Date: Thu, 28 Apr 2022 14:00:00 +0000
            Reply-To: replyTo@example.com
            Message-ID: <%x@example.com>
            MIME-Version: 1.0
            Content-Type: multipart/alternative; boundary=%s

            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: quoted-printable

            Some text
            --%s
            Content-Type: text/html; charset=utf-8
            Content-Transfer-Encoding: quoted-printable

            <html><p>Hello!</p></html>
            --%s--
            TXT,
            $sentMessage->toString()
        );
        $this->assertInstanceOf(Email::class, $sentMessage->getOriginalMessage());
        $this->assertEquals([new Address('bcc@example.com')], $sentMessage->getOriginalMessage()->getBcc());
    }

    public function testSendEmailWithAttachmentsWithSuccess()
    {
        $email = $this->basicValidEmail()
            ->text('foobar')
            ->addPart(new DataPart('some attachment', 'attachment.txt', 'text/plain'))
            ->addPart((new DataPart('some inline attachment', 'inline.txt', 'text/plain'))->asInline())
        ;

        $sentMessage = $this->transport->send($email);

        $this->assertInstanceOf(SentMessage::class, $sentMessage);
        $this->assertStringMatchesFormat(
            <<<'TXT'
            %a
            Content-Type: multipart/mixed; boundary=%s

            --%s
            Content-Type: text/plain; charset=utf-8
            Content-Transfer-Encoding: quoted-printable

            foobar
            --%s
            Content-Type: text/plain; name=attachment.txt
            Content-Transfer-Encoding: base64
            Content-Disposition: attachment; name=attachment.txt;
             filename=attachment.txt

            c29tZSBhdHRhY2htZW50
            --%s
            Content-Type: text/plain; name=inline.txt
            Content-Transfer-Encoding: base64
            Content-Disposition: inline; name=inline.txt; filename=inline.txt

            c29tZSBpbmxpbmUgYXR0YWNobWVudA==
            --%s--
            TXT,
            $sentMessage->toString()
        );
    }

    public function testSendEmailWithHeadersWithSuccess()
    {
        $email = $this->basicValidEmail();
        $email->getHeaders()
            ->addTextHeader('X-Infobip-IntermediateReport', 'true')
            ->addTextHeader('X-Infobip-NotifyUrl', 'https://foo.bar')
            ->addTextHeader('X-Infobip-NotifyContentType', 'application/json')
            ->addTextHeader('X-Infobip-MessageId', 'RANDOM-CUSTOM-ID');

        $sentMessage = $this->transport->send($email);

        $this->assertInstanceOf(SentMessage::class, $sentMessage);
        $this->assertStringMatchesFormat(
            <<<'TXT'
            %a
            X-Infobip-IntermediateReport: true
            X-Infobip-NotifyUrl: https://foo.bar
            X-Infobip-NotifyContentType: application/json
            X-Infobip-MessageId: RANDOM-CUSTOM-ID
            %a
            TXT,
            $sentMessage->toString()
        );
    }

    public function testSentMessageShouldCaptureInfobipMessageId()
    {
        $this->response = new MockResponse('{"messages": [{"messageId": "somexternalMessageId0"}]}');
        $email = $this->basicValidEmail();

        $sentMessage = $this->transport->send($email);

        $this->assertSame('somexternalMessageId0', $sentMessage->getMessageId());
    }

    public function testInfobipResponseShouldNotBeEmpty()
    {
        $this->response = new MockResponse();
        $email = $this->basicValidEmail();

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: ""');

        $this->transport->send($email);
    }

    public function testInfobipResponseShouldBeStatusCode200()
    {
        $this->response = new MockResponse('{"requestError": {"serviceException": {"messageId": "string","text": "string"}}}', ['http_code' => 400]);
        $email = $this->basicValidEmail();

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: "{"requestError": {"serviceException": {"messageId": "string","text": "string"}}}" (code 400)');

        $this->transport->send($email);
    }

    public function testInfobipHttpConnectionFailed()
    {
        $this->response = new MockResponse('', ['error' => 'Test error']);
        $email = $this->basicValidEmail();

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Could not reach the remote Infobip server.');
        $this->transport->send($email);
    }

    private function basicValidEmail(): Email
    {
        return (new Email())
            ->subject('Email sent')
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->text('foobar');
    }
}
