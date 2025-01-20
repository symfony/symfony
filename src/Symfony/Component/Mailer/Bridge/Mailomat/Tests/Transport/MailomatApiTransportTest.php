<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailomat\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Bridge\Mailomat\Transport\MailomatApiTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MailomatApiTransportTest extends TestCase
{
    private const KEY = 'K3Y';

    /**
     * @dataProvider getTransportData
     */
    public function testToString(MailomatApiTransport $transport, string $expected): void
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): iterable
    {
        yield [
            new MailomatApiTransport(self::KEY),
            'mailomat+api://api.mailomat.swiss',
        ];

        yield [
            (new MailomatApiTransport(self::KEY))->setHost('example.com'),
            'mailomat+api://example.com',
        ];

        yield [
            (new MailomatApiTransport(self::KEY))->setHost('example.com')->setPort(99),
            'mailomat+api://example.com:99',
        ];
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.mailomat.swiss/message', $url);
            $this->assertContains('Authorization: Bearer '.self::KEY, $options['headers']);
            $this->assertContains('Content-Type: application/json', $options['headers']);
            $this->assertContains('Accept: application/json', $options['headers']);

            $body = json_decode($options['body'], true);
            $this->assertSame('from@mailomat.swiss', $body['from']['email']);
            $this->assertSame('From Doe', $body['from']['name']);

            $this->assertSame('to@mailomat.swiss', $body['to'][0]['email']);
            $this->assertSame('To Doe', $body['to'][0]['name']);
            $this->assertSame('to-simple@mailomat.swiss', $body['to'][1]['email']);

            $this->assertSame('cc@mailomat.swiss', $body['cc'][0]['email']);
            $this->assertSame('Cc Doe', $body['cc'][0]['name']);
            $this->assertSame('cc-simple@mailomat.swiss', $body['cc'][1]['email']);

            $this->assertSame('bcc@mailomat.swiss', $body['bcc'][0]['email']);
            $this->assertSame('Bcc Doe', $body['bcc'][0]['name']);
            $this->assertSame('bcc-simple@mailomat.swiss', $body['bcc'][1]['email']);

            $this->assertSame('replyto@mailomat.swiss', $body['replyTo'][0]['email']);
            $this->assertSame('ReplyTo Doe', $body['replyTo'][0]['name']);
            $this->assertSame('replyto-simple@mailomat.swiss', $body['replyTo'][1]['email']);

            $this->assertSame('Hello!', $body['subject']);
            $this->assertSame('Hello There!', $body['text']);
            $this->assertSame('<p>Hello There!</p>', $body['html']);

            return new JsonMockResponse(['messageUuid' => 'foobar'], [
                'http_code' => 202,
            ]);
        });

        $transport = new MailomatApiTransport(self::KEY, $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->from(new Address('from@mailomat.swiss', 'From Doe'))
            ->to(new Address('to@mailomat.swiss', 'To Doe'), 'to-simple@mailomat.swiss')
            ->cc(new Address('cc@mailomat.swiss', 'Cc Doe'), 'cc-simple@mailomat.swiss')
            ->bcc(new Address('bcc@mailomat.swiss', 'Bcc Doe'), 'bcc-simple@mailomat.swiss')
            ->replyTo(new Address('replyto@mailomat.swiss', 'ReplyTo Doe'), 'replyto-simple@mailomat.swiss')
            ->text('Hello There!')
            ->html('<p>Hello There!</p>');

        $message = $transport->send($mail);

        $this->assertSame('foobar', $message->getMessageId());
    }

    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(static fn (string $method, string $url, array $options): ResponseInterface => new JsonMockResponse(
            [
                'status' => 422,
                'violations' => [
                    [
                        'propertyPath' => '',
                        'message' => 'You must specify either text or html',
                    ],
                    [
                        'propertyPath' => 'from',
                        'message' => 'Dieser Wert sollte nicht null sein.',
                    ],
                    [
                        'propertyPath' => 'to[1].email',
                        'message' => 'Dieser Wert sollte nicht leer sein.',
                    ],
                    [
                        'propertyPath' => 'subject',
                        'message' => 'Dieser Wert sollte nicht leer sein.',
                    ],
                ],
            ], [
                'http_code' => 422,
            ]));
        $transport = new MailomatApiTransport(self::KEY, $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('to@mailomat.swiss', 'To Doe'))
            ->from(new Address('from@mailomat.swiss', 'From Doe'))
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: You must specify either text or html; (from) Dieser Wert sollte nicht null sein.; (to[1].email) Dieser Wert sollte nicht leer sein.; (subject) Dieser Wert sollte nicht leer sein. (code 422)');
        $transport->send($mail);
    }
}
