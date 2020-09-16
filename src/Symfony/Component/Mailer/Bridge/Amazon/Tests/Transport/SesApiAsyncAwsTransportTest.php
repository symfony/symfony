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

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\NullProvider;
use AsyncAws\Ses\SesClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesApiAsyncAwsTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SesApiAsyncAwsTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(SesApiAsyncAwsTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new SesApiAsyncAwsTransport(new SesClient(Configuration::create(['accessKeyId' => 'ACCESS_KEY', 'accessKeySecret' => 'SECRET_KEY']))),
                'ses+api://ACCESS_KEY@us-east-1',
            ],
            [
                new SesApiAsyncAwsTransport(new SesClient(Configuration::create(['accessKeyId' => 'ACCESS_KEY', 'accessKeySecret' => 'SECRET_KEY', 'region' => 'us-west-1']))),
                'ses+api://ACCESS_KEY@us-west-1',
            ],
            [
                new SesApiAsyncAwsTransport(new SesClient(Configuration::create(['accessKeyId' => 'ACCESS_KEY', 'accessKeySecret' => 'SECRET_KEY', 'endpoint' => 'https://example.com']))),
                'ses+api://ACCESS_KEY@example.com',
            ],
            [
                new SesApiAsyncAwsTransport(new SesClient(Configuration::create(['accessKeyId' => 'ACCESS_KEY', 'accessKeySecret' => 'SECRET_KEY', 'endpoint' => 'https://example.com:99']))),
                'ses+api://ACCESS_KEY@example.com:99',
            ],
        ];
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://email.us-east-1.amazonaws.com/v2/email/outbound-emails', $url);

            $content = json_decode($options['body'], true);

            $this->assertSame('Hello!', $content['Content']['Simple']['Subject']['Data']);
            $this->assertSame('Saif Eddin <saif.gmati@symfony.com>', $content['Destination']['ToAddresses'][0]);
            $this->assertSame('Fabien <fabpot@symfony.com>', $content['FromEmailAddress']);
            $this->assertSame('Hello There!', $content['Content']['Simple']['Body']['Text']['Data']);
            $this->assertSame('<b>Hello There!</b>', $content['Content']['Simple']['Body']['Html']['Data']);
            $this->assertSame(['replyto-1@example.com', 'replyto-2@example.com'], $content['ReplyToAddresses']);
            $this->assertSame('aws-configuration-set-name', $content['ConfigurationSetName']);
            $this->assertSame('bounces@example.com', $content['FeedbackForwardingEmailAddress']);

            $json = '{"MessageId": "foobar"}';

            return new MockResponse($json, [
                'http_code' => 200,
            ]);
        });

        $transport = new SesApiAsyncAwsTransport(new SesClient(Configuration::create([]), new NullProvider(), $client));

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!')
            ->html('<b>Hello There!</b>')
            ->replyTo(new Address('replyto-1@example.com'), new Address('replyto-2@example.com'))
            ->returnPath(new Address('bounces@example.com'));

        $mail->getHeaders()->addTextHeader('X-SES-CONFIGURATION-SET', 'aws-configuration-set-name');

        $message = $transport->send($mail);

        $this->assertSame('foobar', $message->getMessageId());
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

        $transport = new SesApiAsyncAwsTransport(new SesClient(Configuration::create([]), new NullProvider(), $client));

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: i\'m a teapot (code 418).');
        $transport->send($mail);
    }
}
