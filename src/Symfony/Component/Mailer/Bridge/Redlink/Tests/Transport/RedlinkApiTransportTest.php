<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Redlink\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Redlink\Transport\RedlinkApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mateusz Żyła <https://github.com/plotkabytes>
 */
class RedlinkApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(RedlinkApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData()
    {
        yield [
            new RedlinkApiTransport('API_KEY', 'APP_KEY', '1.test.smtp'),
            'redlink+api://api.redlink.pl',
        ];

        yield [
            (new RedlinkApiTransport('API_KEY', 'APP_KEY', '1.test.smtp'))->setHost('example.com'),
            'redlink+api://example.com',
        ];

        yield [
            (new RedlinkApiTransport('API_KEY', 'APP_KEY', '1.test.smtp'))->setHost('example.com')->setPort(99),
            'redlink+api://example.com:99',
        ];
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.redlink.pl/v2.1/email', $url);

            return new MockResponse(json_encode(
                [
                    'meta' => [
                        'numberOfErrors' => 0,
                        'numberOfData' => 1,
                        'status' => 200,
                        'uniqId' => '00d928f759',
                    ],
                    'data' => [
                        [
                            'externalId' => 'test',
                        ],
                    ],
                ]
            ), [
                'http_code' => 200,
            ]);
        });
        $transport = new RedlinkApiTransport('API_TOKEN', 'APP_TOKEN', '1.test.smtp', null, $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('test@symfony.com', 'Test user'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello here!')
            ->html('Hello there!')
            ->addCc('foo@bar.fr')
            ->addBcc('foo@bar.fr')
            ->addReplyTo('foo@bar.fr')
            ->addPart(new DataPart('body'));

        $message = $transport->send($mail);

        $this->assertSame('test', $message->getMessageId());
    }

    public function testIncludeTagsAndHeadersInPayload()
    {
        $email = new Email();
        $email->getHeaders()
            ->add(new MetadataHeader('custom', 'test'))
            ->add(new TagHeader('TagInHeaders'))
            ->addTextHeader('templateId', 1)
            ->addParameterizedHeader('vars', 'foo@example.com', ['x' => 'test'])
            ->addParameterizedHeader('vars', 'foo1@example.com', ['x1' => 'test1'])
            ->addTextHeader('foo', 'bar');

        $envelope = new Envelope(new Address('bar@example.com', 'Bar'), [
            new Address('foo@example.com', 'Foo'),
            new Address('foo1@example.com', 'Foo1'),
        ]);

        $transport = new RedlinkApiTransport('API_TOKEN', 'APP_TOKEN', '1.test.smtp');
        $method = new \ReflectionMethod(RedlinkApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('X-Custom', $payload['headers']);
        $this->assertEquals('test', $payload['headers']['X-Custom']);

        $this->assertArrayHasKey('tags', $payload);
        $this->assertEquals('TagInHeaders', current($payload['tags']));
        $this->assertEquals(1, $payload['content']['templateId']);

        foreach ($payload['to'] as $item) {
            if ('foo@example.com' === $item['email']) {
                $this->assertEquals('test', $item['vars']['x']);
            }

            if ('foo1@example.com' === $item['email']) {
                $this->assertEquals('test1', $item['vars']['x1']);
            }
        }

        $this->assertArrayHasKey('foo', $payload['headers']);
        $this->assertEquals('bar', $payload['headers']['foo']);
    }

    public function testConvertAddresses()
    {
        $transport = new RedlinkApiTransport('API_TOKEN', 'APP_TOKEN', '1.test.smtp');
        $method = new \ReflectionMethod(RedlinkApiTransport::class, 'convertAddresses');
        $result = $method->invoke($transport, [
            new Address('bar@example.com', 'Bar'),
            new Address('bar1@example.com', 'Bar1'),
        ]);

        $this->assertCount(2, $result);
        $this->assertEquals('bar@example.com', $result[0]['email']);
        $this->assertEquals('Bar', $result[0]['name']);

        $this->assertEquals('bar1@example.com', $result[1]['email']);
        $this->assertEquals('Bar1', $result[1]['name']);
    }
}
