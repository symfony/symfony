<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SpotHit\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Notifier\Bridge\SpotHit\SpotHitTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SpotHitTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): SpotHitTransport
    {
        return (new SpotHitTransport('api_token', 'MyCompany', $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['spothit://host.test?from=MyCompany', self::createTransport()];
        yield ['spothit://host.test?from=MyCompany&smslong=1', self::createTransport()->setSmsLong(true)];
        yield ['spothit://host.test?from=MyCompany&smslongnbr=3', self::createTransport()->setLongNBr(3)];
        yield ['spothit://host.test?from=MyCompany&smslong=1&smslongnbr=3', self::createTransport()->setSmsLong(true)->setLongNBr(3)];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new SmsMessage('+33611223344', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function testShouldSendAMessageUsingTheSpotHitAPI()
    {
        $expectedRequest = [
            function ($method, $url, $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://www.spot-hit.fr/api/envoyer/sms', $url);
                $this->assertSame('key=&destinataires=0611223344&type=premium&message=Hello%21&expediteur=', $options['body']);

                return new JsonMockResponse([
                    'resultat' => ['success' => 'true'],
                    'id' => '???',
                ]);
            },
        ];

        $client = new MockHttpClient($expectedRequest);
        $transport = new SpotHitTransport('', '', $client, null);
        $transport->send(new SmsMessage('0611223344', 'Hello!'));
    }

    public static function argumentsProvider(): \Generator
    {
        yield [
            static function (SpotHitTransport $transport) { $transport->setSmsLong(true); },
            static function (array $bodyArguments) { self::assertSame('1', $bodyArguments['smslong']); },
        ];

        yield [
            static function (SpotHitTransport $transport) { $transport->setLongNBr(3); },
            static function (array $bodyArguments) { self::assertSame('3', $bodyArguments['smslongnbr']); },
        ];

        yield [
            static function (SpotHitTransport $transport) {
                $transport->setSmsLong(true);
                $transport->setLongNBr(3);
            },
            static function (array $bodyArguments) {
                self::assertSame('1', $bodyArguments['smslong']);
                self::assertSame('3', $bodyArguments['smslongnbr']);
            },
        ];
    }

    /**
     * @dataProvider argumentsProvider
     */
    public function testShouldForwardArgumentToRequest($setupTransport, $assertions)
    {
        $expectedRequest = [
            function ($method, $url, $options) use ($assertions) {
                $bodyFields = [];
                parse_str($options['body'], $bodyFields);
                $assertions($bodyFields);

                return new JsonMockResponse([
                    'resultat' => ['success' => 'true'],
                    'id' => '???',
                ]);
            },
        ];

        $client = new MockHttpClient($expectedRequest);
        $transport = new SpotHitTransport('', '', $client, null);
        $setupTransport($transport);
        $transport->send(new SmsMessage('0611223344', 'Hello!'));
    }
}
