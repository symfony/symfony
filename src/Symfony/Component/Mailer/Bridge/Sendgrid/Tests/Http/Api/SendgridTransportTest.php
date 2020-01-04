<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Tests\Http\Api;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Sendgrid\Http\Api\SendgridTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SendgridTransportTest extends TestCase
{
    public function testSend()
    {
        $email = new Email();
        $email->from('foo@example.com')
            ->to('bar@example.com')
            ->bcc('baz@example.com');

        $response = $this->createMock(ResponseInterface::class);

        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(202);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with('POST', 'https://api.sendgrid.com/v3/mail/send', [
                'json' => [
                    'personalizations' => [
                        [
                            'to' => [['email' => 'bar@example.com']],
                            'subject' => null,
                            'bcc' => [['email' => 'baz@example.com']],
                        ],
                    ],
                    'from' => ['email' => 'foo@example.com'],
                    'content' => [],
                ],
                'auth_bearer' => 'foo',
            ])
            ->willReturn($response);

        $mailer = new SendgridTransport('foo', $httpClient);

        $mailer->send($email);
    }

    public function testLineBreaksInEncodedAttachment()
    {
        $email = new Email();
        $email->from('foo@example.com')
            ->to('bar@example.com')
            // even if content doesn't include new lines, the base64 encoding performed later may add them
            ->attach('Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod', 'lorem.txt');

        $response = $this->createMock(ResponseInterface::class);

        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(202);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with('POST', 'https://api.sendgrid.com/v3/mail/send', [
                'json' => [
                    'personalizations' => [
                        [
                            'to' => [['email' => 'bar@example.com']],
                            'subject' => null,
                        ],
                    ],
                    'from' => ['email' => 'foo@example.com'],
                    'content' => [],
                    'attachments' => [
                        [
                            'content' => 'TG9yZW0gaXBzdW0gZG9sb3Igc2l0IGFtZXQsIGNvbnNlY3RldHVyIGFkaXBpc2NpbmcgZWxpdCwgc2VkIGRvIGVpdXNtb2Q=',
                            'filename' => 'lorem.txt',
                            'type' => 'application/octet-stream',
                            'disposition' => 'attachment',
                        ],
                    ],
                ],
                'auth_bearer' => 'foo',
            ])
            ->willReturn($response);

        $mailer = new SendgridTransport('foo', $httpClient);

        $mailer->send($email);
    }
}
