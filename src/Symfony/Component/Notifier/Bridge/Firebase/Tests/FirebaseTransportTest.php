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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseOptions;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 * @author Vojtech Smejkal <https://vojtechsmejkal.cz>
 */
final class FirebaseTransportTest extends TransportTestCase
{
    private const PRIVATE_KEY = "-----BEGIN PRIVATE KEY-----\nMIIBVAIBADANBgkqhkiG9w0BAQEFAASCAT4wggE6AgEAAkEAmU2f/GKCLuvw8NAl\nbqJW5RxhMrUrcampGQxz2F2OT3fqoyKBhAGzNhxbgPZYDeXp7WNNLTk9WLT7sDNM\ndjVUuQIDAQABAkAtOTX52QF4YAfKskxoj6E8oxuVPtabCCanCgJekHK7xDpYpYre\ncvxoPhw0c4McZiFoBOlr0TqyY9qpDWXDHLDFAiEAy9jNU/N438WrurUPuOfyqIYx\n25NJWQ7sgjfDJBMVHO8CIQDAhmed4Uih7QKZAMPeRayFeemdjcXNmN4wp/YjiLZ4\n1wIgaTWnnBnAnDYo0T+cMsI8QvCoEP0u0TFbrkXbiOX0cq8CIQCrg9GxrG75mt1y\nk2TrkuS0cLy4GQJ8PFDNxgSY+YWeNwIgavjv+v6MgyLrMTuZsAd67+5Z5axjdJL8\nbwLzq+QOXk8=\n-----END PRIVATE KEY-----\n";

    public static function createTransport(?HttpClientInterface $client = null): FirebaseTransport
    {
        return new FirebaseTransport('', 'test_project_id', 'firebase-adminsdk-test@test-project.iam.gserviceaccount.com', 'private_key_id', self::PRIVATE_KEY, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['firebase://fcm.googleapis.com', self::createTransport()];
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

    #[DataProvider('sendWithErrorThrowsExceptionProvider')]
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
            json_encode(['error' => ['message' => 'testErrorCode']]),
            ['response_headers' => ['content-type' => ['application/json']], 'http_code' => 200]
        )];

        yield [new MockResponse(
            json_encode(['error' => ['message' => 'testErrorCode']]),
            ['response_headers' => ['content-type' => ['application/json']], 'http_code' => 400]
        )];
    }
}
