<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postmark\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class PostmarkApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(PostmarkApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new PostmarkApiTransport('KEY'),
                'postmark+api://api.postmarkapp.com',
            ],
            [
                (new PostmarkApiTransport('KEY'))->setHost('example.com'),
                'postmark+api://example.com',
            ],
            [
                (new PostmarkApiTransport('KEY'))->setHost('example.com')->setPort(99),
                'postmark+api://example.com:99',
            ],
        ];
    }

    public function testSend()
    {
        $email = new Email();
        $email->from('foo@example.com')
            ->to('bar@example.com')
            ->bcc('baz@example.com')
            ->subject('testing email')
            ->text('content');
        $email->getHeaders()->addTextHeader('Tag', 'example-tag');

        $response = $this->createMock(ResponseInterface::class);

        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response
            ->expects($this->once())
            ->method('toArray')
            ->willReturn(['ErrorCode' => 0, 'Message' => 'OK', 'MessageID' => '123', 'To' => 'bar@example.com']);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with('POST', 'https://api.postmarkapp.com/email', [
                'json' => [
                    'From' => 'foo@example.com',
                    'To' => 'bar@example.com',
                    'Cc' => '',
                    'Bcc' => 'baz@example.com',
                    'ReplyTo' => '',
                    'Subject' => 'testing email',
                    'TextBody' => 'content',
                    'HtmlBody' => null,
                    'Attachments' => [],
                    'Tag' => 'example-tag',
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Postmark-Server-Token' => 'foo',
                ],
            ])
            ->willReturn($response);

        $mailer = new PostmarkApiTransport('foo', $httpClient);
        $sentMessage = $mailer->send($email);

        $this->assertSame('123', $sentMessage->getMessageId());
    }
}
