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
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesHttpAsyncAwsTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SesHttpAsyncAwsTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(SesHttpAsyncAwsTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData()
    {
        return [
            [
                new SesHttpAsyncAwsTransport(new SesClient(Configuration::create(['accessKeyId' => 'ACCESS_KEY', 'accessKeySecret' => 'SECRET_KEY']))),
                'ses+https://ACCESS_KEY@us-east-1',
            ],
            [
                new SesHttpAsyncAwsTransport(new SesClient(Configuration::create(['accessKeyId' => 'ACCESS_KEY', 'accessKeySecret' => 'SECRET_KEY', 'region' => 'us-west-1']))),
                'ses+https://ACCESS_KEY@us-west-1',
            ],
            [
                new SesHttpAsyncAwsTransport(new SesClient(Configuration::create(['accessKeyId' => 'ACCESS_KEY', 'accessKeySecret' => 'SECRET_KEY', 'endpoint' => 'https://example.com']))),
                'ses+https://ACCESS_KEY@example.com',
            ],
            [
                new SesHttpAsyncAwsTransport(new SesClient(Configuration::create(['accessKeyId' => 'ACCESS_KEY', 'accessKeySecret' => 'SECRET_KEY', 'endpoint' => 'https://example.com:99']))),
                'ses+https://ACCESS_KEY@example.com:99',
            ],
            [
                new SesHttpAsyncAwsTransport(new SesClient(Configuration::create(['accessKeyId' => 'ACCESS_KEY', 'accessKeySecret' => 'SECRET_KEY', 'sessionToken' => 'SESSION_TOKEN']))),
                'ses+https://ACCESS_KEY@us-east-1',
            ],
            [
                new SesHttpAsyncAwsTransport(new SesClient(Configuration::create(['accessKeyId' => 'ACCESS_KEY', 'accessKeySecret' => 'SECRET_KEY', 'region' => 'us-west-1', 'sessionToken' => 'SESSION_TOKEN']))),
                'ses+https://ACCESS_KEY@us-west-1',
            ],
            [
                new SesHttpAsyncAwsTransport(new SesClient(Configuration::create(['accessKeyId' => 'ACCESS_KEY', 'accessKeySecret' => 'SECRET_KEY', 'endpoint' => 'https://example.com', 'sessionToken' => 'SESSION_TOKEN']))),
                'ses+https://ACCESS_KEY@example.com',
            ],
            [
                new SesHttpAsyncAwsTransport(new SesClient(Configuration::create(['accessKeyId' => 'ACCESS_KEY', 'accessKeySecret' => 'SECRET_KEY', 'endpoint' => 'https://example.com:99', 'sessionToken' => 'SESSION_TOKEN']))),
                'ses+https://ACCESS_KEY@example.com:99',
            ],
        ];
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://email.us-east-1.amazonaws.com/v2/email/outbound-emails', $url);

            $body = json_decode($options['body'], true);
            $content = base64_decode($body['Content']['Raw']['Data']);

            $this->assertStringContainsString('Hello!', $content);
            $this->assertStringContainsString('Saif Eddin <saif.gmati@symfony.com>', $content);
            $this->assertStringContainsString('Fabien <fabpot@symfony.com>', $content);
            $this->assertStringContainsString('Hello There!', $content);
            $this->assertSame('aws-configuration-set-name', $body['ConfigurationSetName']);
            $this->assertSame('aws-source-arn', $body['FromEmailAddressIdentityArn']);
            $this->assertSame([['Name' => 'tagName1', 'Value' => 'tag Value1'], ['Name' => 'tagName2', 'Value' => 'tag Value2']], $body['EmailTags']);

            $json = '{"MessageId": "foobar"}';

            return new MockResponse($json, [
                'http_code' => 200,
            ]);
        });

        $transport = new SesHttpAsyncAwsTransport(new SesClient(Configuration::create([]), new NullProvider(), $client));

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $mail->getHeaders()->addTextHeader('X-SES-CONFIGURATION-SET', 'aws-configuration-set-name');
        $mail->getHeaders()->addTextHeader('X-SES-SOURCE-ARN', 'aws-source-arn');
        $mail->getHeaders()->add(new MetadataHeader('tagName1', 'tag Value1'));
        $mail->getHeaders()->add(new MetadataHeader('tagName2', 'tag Value2'));

        $message = $transport->send($mail);

        $this->assertSame('foobar', $message->getMessageId());
    }

    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $json = json_encode([
                'message' => 'i\'m a teapot',
                'type' => 'sender',
            ]);

            return new MockResponse($json, [
                'http_code' => 418,
                'response_headers' => [
                    'x-amzn-errortype' => '418',
                ],
            ]);
        });

        $transport = new SesHttpAsyncAwsTransport(new SesClient(Configuration::create([]), new NullProvider(), $client));

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
