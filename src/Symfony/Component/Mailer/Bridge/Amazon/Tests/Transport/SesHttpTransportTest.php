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
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesHttpTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @group legacy
 */
class SesHttpTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(SesHttpTransport $transport, string $expected)
    {
        self::assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new SesHttpTransport('ACCESS_KEY', 'SECRET_KEY'),
                'ses+https://ACCESS_KEY@email.eu-west-1.amazonaws.com',
            ],
            [
                new SesHttpTransport('ACCESS_KEY', 'SECRET_KEY', 'us-east-1'),
                'ses+https://ACCESS_KEY@email.us-east-1.amazonaws.com',
            ],
            [
                (new SesHttpTransport('ACCESS_KEY', 'SECRET_KEY'))->setHost('example.com'),
                'ses+https://ACCESS_KEY@example.com',
            ],
            [
                (new SesHttpTransport('ACCESS_KEY', 'SECRET_KEY'))->setHost('example.com')->setPort(99),
                'ses+https://ACCESS_KEY@example.com:99',
            ],
        ];
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            self::assertSame('POST', $method);
            self::assertSame('https://email.eu-west-1.amazonaws.com:8984/', $url);
            self::assertStringContainsString('AWS3-HTTPS AWSAccessKeyId=ACCESS_KEY,Algorithm=HmacSHA256,Signature=', $options['headers'][0] ?? $options['request_headers'][0]);

            parse_str($options['body'], $body);

            self::assertArrayHasKey('Destinations_member_1', $body);
            self::assertSame('saif.gmati@symfony.com', $body['Destinations_member_1']);
            self::assertArrayHasKey('Destinations_member_2', $body);
            self::assertSame('jeremy@derusse.com', $body['Destinations_member_2']);

            $content = base64_decode($body['RawMessage_Data']);

            self::assertStringContainsString('Hello!', $content);
            self::assertStringContainsString('Saif Eddin <saif.gmati@symfony.com>', $content);
            self::assertStringContainsString('Fabien <fabpot@symfony.com>', $content);
            self::assertStringContainsString('Hello There!', $content);

            self::assertSame('aws-configuration-set-name', $body['ConfigurationSetName']);
            self::assertSame('aws-source-arn', $body['FromEmailAddressIdentityArn']);

            $xml = '<SendEmailResponse xmlns="https://email.amazonaws.com/doc/2010-03-31/">
  <SendRawEmailResult>
    <MessageId>foobar</MessageId>
  </SendRawEmailResult>
</SendEmailResponse>';

            return new MockResponse($xml, [
                'http_code' => 200,
            ]);
        });
        $transport = new SesHttpTransport('ACCESS_KEY', 'SECRET_KEY', null, $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->bcc(new Address('jeremy@derusse.com', 'Jérémy Derussé'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $mail->getHeaders()->addTextHeader('X-SES-CONFIGURATION-SET', 'aws-configuration-set-name');
        $mail->getHeaders()->addTextHeader('X-SES-SOURCE-ARN', 'aws-source-arn');

        $message = $transport->send($mail);

        self::assertSame('foobar', $message->getMessageId());
    }

    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $xml = "<SendEmailResponse xmlns=\"https://email.amazonaws.com/doc/2010-03-31/\">
                <Error>
                    <Message>i'm a teapot</Message>
                    <Code>418</Code>
                </Error>
            </SendEmailResponse>";

            return new MockResponse($xml, [
                'http_code' => 418,
            ]);
        });
        $transport = new SesHttpTransport('ACCESS_KEY', 'SECRET_KEY', null, $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        self::expectException(HttpTransportException::class);
        self::expectExceptionMessage('Unable to send an email: i\'m a teapot (code 418).');
        $transport->send($mail);
    }
}
