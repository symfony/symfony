<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseJwtTransport;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseOptions;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Cesur APAYDIN <https://github.com/cesurapp>
 */
final class FirebaseJwtTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): FirebaseJwtTransport
    {
        return new FirebaseJwtTransport([
            'project_id' => 'test_project',
            'client_email' => 'firebase-adminsdk-test@test.iam.gserviceaccount.com',
            'private_key_id' => 'sdas7d6a8ds6ds78a',
            'private_key' => "-----BEGIN RSA PRIVATE KEY-----\nMIICWwIBAAKBgGN4fgq4BFQwjK7kzWUYSFE1ryGIBtUScY5TqLY2BAROBnZS+SIa\nH4VcZJStPUwjtsVxJTf57slhMM5FbAOQkWFMmRlHGWc7EZy6UMMvP8FD21X3Ty9e\nZzJ/Be30la1Uy7rechBh3RN+Y3rSKV+gDmsjdo5/4Jekj4LfluDXbwVJAgMBAAEC\ngYA5SqY2IEUGBKyS81/F8ZV9iNElHAbrZGMZWeAbisMHg7U/I40w8iDjnBKme52J\npCxaTk/kjMTXIm6M7/lFmFfTHgl5WLCimu2glMyKFM2GBYX/cKx9RnI36q3uJYml\n1G1f2H7ALurisenEqMaq8bdyApd/XNqcijogfsZ1K/irTQJBAKEQFkqNDgwUgAwr\njhG/zppl5yEJtP+Pncp/2t/s6khk0q8N92xw6xl8OV/ww+rwlJB3IKVKw903LztQ\nP1D3zpMCQQCeGlOvMx9XxiktNIkdXekGP/bFUR9/u0ABaYl9valZ2B3yZzujJJHV\n0EtyKGorT39wWhWY7BI8NTYgivCIWGozAkEAhMnOlwhUXIFKUL5YEyogHAuH0yU9\npLWzUhC3U4bwYV8+lDTfmPg/3HMemorV/Az9b13H/H73nJqyxiQTD54/IQJAZUX/\n7O4WWac5oRdR7VnGdpZqgCJixvMvILh1tfHTlRV2uVufO/Wk5Q00BsAUogGeZF2Q\nEBDH7YE4VsgpI21fOQJAJdSB7mHvStlYCQMEAYWCWjk+NRW8fzZCkQkqzOV6b9dw\nDFp6wp8aLw87hAHUz5zXTCRYi/BpvDhfP6DDT2sOaw==\n-----END RSA PRIVATE KEY-----"
        ], $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['firebase-jwt://fcm.googleapis.com/v1/projects/test_project/messages:send', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new DummyMessage()];
    }

    /**
     * @dataProvider sendWithErrorThrowsExceptionProvider
     */
    public function testSendWithErrorThrowsTransportException(ResponseInterface $response)
    {
        $this->expectException(TransportException::class);

        $client = new MockHttpClient(static fn (): ResponseInterface => $response);
        $options = new class('recipient-id', []) extends FirebaseOptions {};

        $transport = self::createTransport($client);

        $transport->send(new ChatMessage('Hello!', $options));
    }

    public static function sendWithErrorThrowsExceptionProvider(): iterable
    {
        yield [new MockResponse(
            json_encode(['results' => [['error' => 'testErrorCode']]]),
            ['response_headers' => ['content-type' => ['application/json']], 'http_code' => 200]
        )];

        yield [new MockResponse(
            json_encode(['results' => [['error' => 'testErrorCode']]]),
            ['response_headers' => ['content-type' => ['application/json']], 'http_code' => 400]
        )];
    }
}
