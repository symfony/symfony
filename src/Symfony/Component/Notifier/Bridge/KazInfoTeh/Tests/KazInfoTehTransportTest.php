<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\KazInfoTeh\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\KazInfoTeh\KazInfoTehTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Egor Taranov <dev@taranovegor.com>
 */
final class KazInfoTehTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): TransportInterface
    {
        return (new KazInfoTehTransport('username', 'password', 'sender', $client ?? new MockHttpClient()))->setHost('test.host');
    }

    public static function toStringProvider(): iterable
    {
        yield ['kaz-info-teh://test.host?sender=sender', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('77000000000', 'KazInfoTeh!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('420000000000', 'KazInfoTeh!')];

        yield [new DummyMessage()];
    }

    public function createClient(int $statusCode, string $content): HttpClientInterface
    {
        return new MockHttpClient(new MockResponse($content, ['http_code' => $statusCode]));
    }

    public static function responseProvider(): iterable
    {
        $responses = [
            ['status' => 200, 'content' => '<?xml version="1.0" encoding="utf-8" ?><acceptreport><statuscode>1</statuscode><statusmessage>Status code is not valid</statusmessage></acceptreport>', 'error_message' => 'Unable to send the SMS: "Status code is not valid".'],
            ['status' => 200, 'content' => '{"message": Response not in XML format}', 'error_message' => 'Unable to send the SMS: "Couldn\'t read response".'],
            ['status' => 200, 'content' => '<?xml version="1.0" encoding="utf-8" ?><acceptreport><errorcode>1</errorcode><errormessage>Error code is not valid</errormessage></acceptreport>', 'error_message' => 'Unable to send the SMS: "Error code is not valid".'],
            ['status' => 500, 'content' => '<?xml version="1.0" encoding="utf-8" ?><acceptreport><errorcode>1</errorcode><errormessage>Something went wrong</errormessage></acceptreport>', 'error_message' => 'Unable to send the SMS: "Something went wrong".'],
            ['status' => 500, 'content' => '', 'error_message' => 'Unable to send the SMS: "Couldn\'t read response".'],
        ];

        foreach ($responses as $response) {
            yield [$response['status'], $response['content'], $response['error_message']];
        }
    }

    /**
     * @dataProvider responseProvider
     */
    public function testThrowExceptionWhenMessageWasNotSent(int $statusCode, string $content, string $errorMessage)
    {
        $client = $this->createClient($statusCode, $content);
        $transport = $this->createTransport($client);
        $message = new SmsMessage('77000000000', 'Hello, bug!');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage($errorMessage);

        $transport->send($message);
    }
}
