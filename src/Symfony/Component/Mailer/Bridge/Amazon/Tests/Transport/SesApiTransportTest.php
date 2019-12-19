<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SesApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(SesApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new SesApiTransport('ACCESS_KEY', 'SECRET_KEY'),
                'ses+api://ACCESS_KEY@email.eu-west-1.amazonaws.com',
            ],
            [
                new SesApiTransport('ACCESS_KEY', 'SECRET_KEY', 'us-east-1'),
                'ses+api://ACCESS_KEY@email.us-east-1.amazonaws.com',
            ],
            [
                (new SesApiTransport('ACCESS_KEY', 'SECRET_KEY'))->setHost('example.com'),
                'ses+api://ACCESS_KEY@example.com',
            ],
            [
                (new SesApiTransport('ACCESS_KEY', 'SECRET_KEY'))->setHost('example.com')->setPort(99),
                'ses+api://ACCESS_KEY@example.com:99',
            ],
        ];
    }

    public function testSendEmail()
    {
        $awsApiCallback = function ($method, $url, $options) {
            $requestParts = [];
            parse_str($options['body'], $requestParts);

            $this->assertEquals('POST', $method);
            $this->assertEquals('https://email.eu-west-1.amazonaws.com/', $url);

            $this->assertEquals($requestParts['Source'], 'sender@example.tld');
            $this->assertArrayNotHasKey('Destinations.member', $requestParts);

            return new MockResponse($this->createMockAwsSendRawEmailResponse());
        };

        $httpClient = new MockHttpClient($awsApiCallback);
        $transport = new SesApiTransport('ACCESS_KEY', 'SECRET_KEY', 'eu-west-1', $httpClient);

        $email = (new Email())
            ->from('sender@example.tld')
            ->to('recipient-to@example.tld')
            ->subject('Test Mail')
            ->text('Text content of the test mail')
            ->html('<p>HTML content of the test mail</p>');

        $transport->send($email);
    }

    public function testSendEmailWithEnvelopeOverride()
    {
        $awsApiCallback = function ($method, $url, $options) {
            $requestParts = [];
            parse_str($options['body'], $requestParts);

            $this->assertEquals('POST', $method);
            $this->assertEquals('https://email.eu-west-1.amazonaws.com/', $url);

            $this->assertEquals($requestParts['Source'], 'envelope-sender@example.tld');
            $this->assertArrayHasKey('Destinations_member', $requestParts);
            $this->assertEquals($requestParts['Destinations_member'], [
                'envelope-recipient-1@example.tld',
                'envelope-recipient-2@example.tld',
            ]);

            return new MockResponse($this->createMockAwsSendRawEmailResponse());
        };

        $httpClient = new MockHttpClient($awsApiCallback);
        $transport = new SesApiTransport('ACCESS_KEY', 'SECRET_KEY', 'eu-west-1', $httpClient);

        $email = (new Email())
            ->from('sender@example.tld')
            ->to('recipient-to@example.tld')
            ->subject('Test Mail')
            ->text('Text content of the test mail')
            ->html('<p>HTML content of the test mail</p>');

        $envelope = new Envelope(Address::create('envelope-sender@example.tld'), [
            Address::create('envelope-recipient-1@example.tld'),
            Address::create('envelope-recipient-2@example.tld'),
        ]);

        $transport->send($email, $envelope);
    }

    private function createMockAwsSendRawEmailResponse(): string
    {
        return <<<XML
        <SendRawEmailResponse xmlns="http://ses.amazonaws.com/doc/2010-12-01/">
          <SendRawEmailResult>
            <MessageId>0102016f1eb7661b-4294e7da-5d64-45c2-8998-e8ade5468d95-000000</MessageId>
          </SendRawEmailResult>
          <ResponseMetadata>
            <RequestId>dc4fb17f-0320-428b-8c03-62f9ec9b98ba</RequestId>
          </ResponseMetadata>
        </SendRawEmailResponse>
        XML;
    }
}
