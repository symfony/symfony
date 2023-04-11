<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Response;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Test methods from Symfony\Component\HttpClient\Response\*ResponseTrait.
 */
class MockResponseTest extends TestCase
{
    public function testTotalTimeShouldBeSimulatedWhenNotProvided()
    {
        $response = new MockResponse('body');
        $response = MockResponse::fromRequest('GET', 'https://example.com/file.txt', [], $response);

        $this->assertNotNull($response->getInfo('total_time'));
        $this->assertGreaterThan(0.0, $response->getInfo('total_time'));
    }

    public function testTotalTimeShouldNotBeSimulatedWhenProvided()
    {
        $totalTime = 4.2;
        $response = new MockResponse('body', ['total_time' => $totalTime]);
        $response = MockResponse::fromRequest('GET', 'https://example.com/file.txt', [], $response);

        $this->assertEquals($totalTime, $response->getInfo('total_time'));
    }

    public function testToArray()
    {
        $data = ['color' => 'orange', 'size' => 42];
        $response = new MockResponse(json_encode($data));
        $response = MockResponse::fromRequest('GET', 'https://example.com/file.json', [], $response);

        $this->assertSame($data, $response->toArray());
    }

    /**
     * @dataProvider toArrayErrors
     */
    public function testToArrayError($content, $responseHeaders, $message)
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage($message);

        $response = new MockResponse($content, ['response_headers' => $responseHeaders]);
        $response = MockResponse::fromRequest('GET', 'https://example.com/file.json', [], $response);
        $response->toArray();
    }

    public function testUrlHttpMethodMockResponse()
    {
        $responseMock = new MockResponse(json_encode(['foo' => 'bar']));
        $url = 'https://example.com/some-endpoint';
        $response = MockResponse::fromRequest('GET', $url, [], $responseMock);

        $this->assertSame('GET', $response->getInfo('http_method'));
        $this->assertSame('GET', $responseMock->getRequestMethod());

        $this->assertSame($url, $response->getInfo('url'));
        $this->assertSame($url, $responseMock->getRequestUrl());
    }

    public static function toArrayErrors()
    {
        yield [
            'content' => '',
            'responseHeaders' => [],
            'message' => 'Response body is empty.',
        ];

        yield [
            'content' => 'not json',
            'responseHeaders' => [],
            'message' => 'Syntax error for "https://example.com/file.json".',
        ];

        yield [
            'content' => '[1,2}',
            'responseHeaders' => [],
            'message' => 'State mismatch (invalid or malformed JSON) for "https://example.com/file.json".',
        ];

        yield [
            'content' => '"not an array"',
            'responseHeaders' => [],
            'message' => 'JSON content was expected to decode to an array, "string" returned for "https://example.com/file.json".',
        ];

        yield [
            'content' => '8',
            'responseHeaders' => [],
            'message' => 'JSON content was expected to decode to an array, "int" returned for "https://example.com/file.json".',
        ];
    }

    public function testErrorIsTakenIntoAccountInInitialization()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('ccc error');

        MockResponse::fromRequest('GET', 'https://symfony.com', [], new MockResponse('', [
            'error' => 'ccc error',
        ]))->getStatusCode();
    }

    public function testCancelingAMockResponseNotIssuedByMockHttpClient()
    {
        $mockResponse = new MockResponse();
        $mockResponse->cancel();

        $this->assertTrue($mockResponse->getInfo('canceled'));
    }

    public function testMustBeIssuedByMockHttpClient()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MockResponse instances must be issued by MockHttpClient before processing.');

        (new MockResponse())->getContent();
    }
}
